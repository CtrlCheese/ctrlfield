<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

/**
 * The "table" layout of groups and repeaters: one column per sub-field, the
 * labels once as the header. Header and rows share one CSS grid template
 * (--ctrlf-cols), so columns line up; ->width() sets a column's share.
 * Tabs, accordions and messages have no place in a table and are skipped.
 */
final class SubFieldTable
{
    /**
     * @param  array<int, FieldDefinition> $fields
     * @return list<FieldDefinition>
     */
    public static function columns(array $fields): array
    {
        return array_values(array_filter($fields, static fn (FieldDefinition $f): bool => ! $f->isUiOnly()));
    }

    /**
     * grid-template-columns: a column with a width gets that share, the others
     * split what is left.
     *
     * @param list<FieldDefinition> $columns
     */
    public static function template(array $columns): string
    {
        $set   = array_filter(array_map(static fn (FieldDefinition $f) => $f->getWidth(), $columns), static fn ($w) => $w !== null);
        $free  = count($columns) - count($set);
        $rest  = $free > 0 ? max(10.0, (100 - array_sum($set)) / $free) : 0.0;
        $parts = [];
        foreach ($columns as $f) {
            $parts[] = 'minmax(0, ' . round((float) ($f->getWidth() ?? $rest), 2) . 'fr)';
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<FieldDefinition> $columns
     * @param int $lead  empty cells before the labels (a repeater's handle and index)
     * @param int $trail empty cells after them (row actions)
     */
    public static function head(array $columns, string $extraClass = '', int $lead = 0, int $trail = 0): string
    {
        $blank = '<div class="ctrlf-table__th ctrlf-table__th--blank" aria-hidden="true"></div>';
        $cells = str_repeat($blank, $lead);
        foreach ($columns as $f) {
            $ui    = AbstractRenderer::prepare($f);
            $req   = $f->isRequired() ? '<span class="ctrlf-required" aria-hidden="true">*</span>' : '';
            $instr = $ui['instructions'] !== '' ? '<span class="ctrlf-instructions">' . AbstractRenderer::instructionsHtml($ui['instructions']) . '</span>' : '';
            $cells .= '<div class="ctrlf-table__th" role="columnheader"><span class="ctrlf-label">'
                . htmlspecialchars($ui['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $req . '</span>' . $instr . '</div>';
        }

        $cells .= str_repeat($blank, $trail);

        return '<div class="ctrlf-table__head' . ($extraClass !== '' ? ' ' . $extraClass : '') . '" role="row">' . $cells . '</div>';
    }

    /**
     * One row of cells; labels stay in the markup for screen readers only.
     *
     * @param list<FieldDefinition>            $columns
     * @param callable(FieldDefinition): string $path   state path of a sub-field
     */
    public static function cells(array $columns, callable $path): string
    {
        $html = '';
        foreach ($columns as $f) {
            $html .= '<div class="ctrlf-table__td" role="cell">' . AbstractRenderer::fieldHtml($f, $path($f)) . '</div>';
        }

        return $html;
    }
}
