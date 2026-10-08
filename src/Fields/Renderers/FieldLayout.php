<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\AccordionField;

/**
 * Lays out a flat field list the way ACF does, at any level (field group,
 * group field, repeater row, flexible section):
 *
 *  - an accordion field starts a collapsible section that runs until the next
 *    accordion or an accordion end (no explicit end needed);
 *  - inside a section, a tab field starts a tab that runs until the next tab;
 *    fields before the first tab stay above the tab bar.
 *
 * Tabs and accordions keep their open state in a small local x-data, so the
 * same markup works inside x-for rows.
 */
final class FieldLayout
{
    /**
     * @param  list<FieldDefinition> $fields
     * @return list<array{accordion: ?FieldDefinition, before: list<FieldDefinition>, tabs: list<array{tab: FieldDefinition, fields: list<FieldDefinition>}>}>
     */
    public static function sections(array $fields): array
    {
        $sections = [];
        $current  = null; // ['accordion' => ?FieldDefinition, 'fields' => list]

        foreach ($fields as $field) {
            $type = $field->getType();
            if ($type === FieldType::ACCORDION || $type === FieldType::ACCORDION_END) {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $current = $type === FieldType::ACCORDION ? ['accordion' => $field, 'fields' => []] : null;
                continue;
            }
            $current ??= ['accordion' => null, 'fields' => []];
            $current['fields'][] = $field;
        }
        if ($current !== null) {
            $sections[] = $current;
        }

        return array_map(self::tabs(...), $sections);
    }

    /**
     * @param  list<FieldDefinition>              $fields
     * @param  callable(FieldDefinition): string  $renderOne HTML of one regular field
     */
    public static function render(array $fields, callable $renderOne): string
    {
        $html = '';
        foreach (self::sections($fields) as $section) {
            $inner = self::renderTabs($section, $renderOne);
            $html .= $section['accordion'] !== null ? self::accordion($section['accordion'], $inner) : $inner;
        }

        return $html;
    }

    /**
     * @param  array{accordion: ?FieldDefinition, fields: list<FieldDefinition>} $section
     * @return array{accordion: ?FieldDefinition, before: list<FieldDefinition>, tabs: list<array{tab: FieldDefinition, fields: list<FieldDefinition>}>}
     */
    private static function tabs(array $section): array
    {
        $before    = [];
        $tabFields = []; // list<FieldDefinition> per tab, same index as $tabHeads
        $tabHeads  = [];
        foreach ($section['fields'] as $field) {
            if ($field->getType() === FieldType::TAB) {
                $tabHeads[]  = $field;
                $tabFields[] = [];
            } elseif ($tabHeads === []) {
                $before[] = $field;
            } else {
                $tabFields[count($tabFields) - 1][] = $field;
            }
        }

        $tabs = [];
        foreach ($tabHeads as $i => $head) {
            $tabs[] = ['tab' => $head, 'fields' => $tabFields[$i]];
        }

        return ['accordion' => $section['accordion'], 'before' => $before, 'tabs' => $tabs];
    }

    /**
     * @param array{accordion: ?FieldDefinition, before: list<FieldDefinition>, tabs: list<array{tab: FieldDefinition, fields: list<FieldDefinition>}>} $section
     * @param callable(FieldDefinition): string $renderOne
     */
    private static function renderTabs(array $section, callable $renderOne): string
    {
        $html = '';
        foreach ($section['before'] as $field) {
            $html .= $renderOne($field);
        }
        if ($section['tabs'] === []) {
            return $html;
        }

        $first = self::js($section['tabs'][0]['tab']->getKey());
        $nav   = '';
        $panes = '';
        foreach ($section['tabs'] as $i => $tab) {
            $k     = self::js($tab['tab']->getKey()) . '_' . $i; // tab keys can repeat (Flynt: "contentTab")
            $label = self::esc(self::label($tab['tab']));
            $nav  .= '<button type="button" role="tab" class="ctrlf-tab-btn" :class="{ \'is-active\': ctrlfTab === \'' . $k . '\' }"'
                . ' :aria-selected="ctrlfTab === \'' . $k . '\'" @click="ctrlfTab = \'' . $k . '\'">' . $label . '</button>';
            $body = '';
            foreach ($tab['fields'] as $field) {
                $body .= $renderOne($field);
            }
            $panes .= '<div class="ctrlf-tab-panel" role="tabpanel" x-show="ctrlfTab === \'' . $k . '\'">' . $body . '</div>';
        }

        // The active tab is remembered across reloads (uiState.js).
        $name = 'tab:' . $first;
        return $html . '<div class="ctrlf-tabs" x-data="{ ctrlfTab: $ctrlfUi(\'' . $name . '\', \'' . $first . '_0\') }"'
            . ' x-effect="$ctrlfUiSet(\'' . $name . '\', ctrlfTab)">'
            . '<div class="ctrlf-tabs-nav" role="tablist">' . $nav . '</div>' . $panes . '</div>';
    }

    private static function accordion(FieldDefinition $field, string $inner): string
    {
        $open = ! ($field instanceof AccordionField) || $field->isOpen() ? 'true' : 'false';

        $name = 'acc:' . self::js($field->getKey());

        return '<div class="ctrlf-accordion" x-data="{ open: $ctrlfUi(\'' . $name . '\', ' . $open . ') }"'
            . ' x-effect="$ctrlfUiSet(\'' . $name . '\', open)">'
            . '<button type="button" class="ctrlf-accordion-toggle" @click="open = !open" :aria-expanded="open">'
            . '<span>' . self::esc(self::label($field)) . '</span>'
            . '<svg class="ctrlf-accordion-icon" :class="{ \'is-open\': open }" viewBox="0 0 20 20" aria-hidden="true"><path d="M6 8l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2"/></svg>'
            . '</button>'
            . '<div class="ctrlf-accordion-content" x-show="open" x-collapse>' . $inner . '</div>'
            . '</div>';
    }

    private static function label(FieldDefinition $field): string
    {
        $label = (string) ($field->getDefinition()['label'] ?? '');

        return $label !== '' ? $label : $field->getKey();
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** For a JS string inside a double-quoted HTML attribute. */
    private static function js(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '_', $value);
    }
}
