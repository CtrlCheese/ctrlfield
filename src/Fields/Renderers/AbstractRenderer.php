<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\Contracts\RendererInterface;
use CtrlField\Fields\FieldDefinition;

abstract class AbstractRenderer implements RendererInterface
{
    /**
     * Wraps a field input with its label and visible_when x-show directive.
     * Called by MetaBoxRenderer — renderers themselves only output the input HTML.
     */
    abstract public function render(FieldDefinition $field, string $statePath): string;

    /**
     * Escapes a value for use inside an HTML attribute (double-quote delimited).
     * Deliberately uses ENT_COMPAT (not ENT_QUOTES) so single quotes in Alpine
     * JS expressions — e.g. adminState['key'] — are preserved and remain
     * evaluable by Alpine without entity decoding.
     */
    protected function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function inputId(FieldDefinition $field): string
    {
        return 'ctrlf-' . $field->getKey();
    }

    protected function baseClasses(): string
    {
        return 'ctrlf-input';
    }

    /** Six-dot grip for drag handles (see x-ctrlf-handle in assets/admin/src/sortable.js). */
    public static function dragHandleIcon(): string
    {
        return '<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">'
            . '<circle cx="7" cy="5" r="1.5"/><circle cx="13" cy="5" r="1.5"/>'
            . '<circle cx="7" cy="10" r="1.5"/><circle cx="13" cy="10" r="1.5"/>'
            . '<circle cx="7" cy="15" r="1.5"/><circle cx="13" cy="15" r="1.5"/></svg>';
    }

    /**
     * Initial values of a new repeater row / flexible section: each sub-field's
     * default (groups nest), as ACF pre-fills them. Fields without one are null.
     *
     * @param  iterable<\CtrlField\Fields\FieldDefinition> $fields
     * @return array<string, mixed>
     */
    public static function rowDefaults(iterable $fields): array
    {
        $row = [];
        foreach ($fields as $f) {
            $type = $f->getType();
            if ($type === \CtrlField\Enums\FieldType::GROUP && $f instanceof \CtrlField\Fields\Contracts\NestedFieldInterface) {
                $row[$f->getKey()] = self::rowDefaults($f->getFields());
            } else {
                $row[$f->getKey()] = $f->hasDefault() ? $f->getDefault() : null;
            }
        }

        return $row;
    }

    /** ACF shows no title for a group without a label; other fields fall back to their key. */
    public static function labelFor(\CtrlField\Fields\FieldDefinition $f): string
    {
        $label = (string) ($f->getDefinition()['label'] ?? '');
        if ($label !== '' || $f->getType() === \CtrlField\Enums\FieldType::GROUP) {
            return $label;
        }

        return $f->getKey();
    }

    /**
     * Label / instructions of a field as they will be shown. Themes can adjust
     * or hide them per field (ctrlfield/prepare_field; ACF's acf/prepare_field
     * is bridged to it by the ACF compatibility layer).
     *
     * @return array{label: string, instructions: string, hidden: bool}
     */
    public static function prepare(\CtrlField\Fields\FieldDefinition $field, mixed $value = null): array
    {
        $ui = ['label' => self::labelFor($field), 'instructions' => $field->getInstructions(), 'hidden' => false];
        if (! function_exists('apply_filters')) {
            return $ui;
        }
        $filtered = apply_filters('ctrlfield/prepare_field', $ui, $field, $value);

        return is_array($filtered)
            ? ['label' => (string) ($filtered['label'] ?? ''), 'instructions' => (string) ($filtered['instructions'] ?? ''), 'hidden' => ! empty($filtered['hidden'])]
            : ['label' => '', 'instructions' => '', 'hidden' => true];
    }

    /** Instructions may contain links and emphasis, as in ACF. */
    public static function instructionsHtml(string $instructions): string
    {
        return function_exists('wp_kses_post') ? wp_kses_post($instructions) : htmlspecialchars($instructions, ENT_QUOTES, 'UTF-8');
    }

    /** A nested field with its label, required mark and instructions (groups, rows, sections). */
    public static function fieldHtml(\CtrlField\Fields\FieldDefinition $sub, string $path): string
    {
        $ui = self::prepare($sub); // rows are templates: no per-row value, like a new ACF row
        if ($ui['hidden']) {
            return '';
        }
        $esc      = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $label    = $ui['label'];
        $required = $sub->isRequired() ? '<span class="ctrlf-required" aria-hidden="true">*</span>' : '';
        $instr    = $ui['instructions'];
        $width    = $sub->getWidth();
        $input    = RendererRegistry::resolve($sub->getType())->render($sub, $path);

        return '<div class="ctrlf-field' . ($width !== null ? ' ctrlf-col-' . $width : '') . '">'
            . ($label !== '' || $required !== '' ? '<label class="ctrlf-label">' . $esc($label) . $required . '</label>' : '')
            . $input
            . ($instr !== '' ? '<p class="ctrlf-instructions">' . self::instructionsHtml($instr) . '</p>' : '')
            . '</div>';
    }
}
