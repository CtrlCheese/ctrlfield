<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\RangeField;

/**
 * The slider with its minimum and maximum at the ends, and the value beside
 * it as a number to type (kept inside the bounds), with ->append() as unit.
 */
final class RangeRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $min    = $field instanceof RangeField ? $field->getMin() : 0.0;
        $max    = $field instanceof RangeField ? $field->getMax() : 100.0;
        $step   = $field instanceof RangeField ? $field->getStep() : 1.0;
        $before = $field instanceof RangeField ? $field->getPrepend() : '';
        $after  = $field instanceof RangeField ? $field->getAppend() : '';
        $p      = $this->esc($statePath);
        $id     = $this->esc($this->inputId($field));
        $off    = $field->isReadOnly() ? ' disabled' : '';
        $fmt    = static fn (float $n): string => rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
        [$lo, $hi, $st] = [$this->esc($fmt($min)), $this->esc($fmt($max)), $this->esc($fmt($step))];
        $unit   = static fn (string $t): string => $t !== '' ? '<span class="ctrlf-affix__text">' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</span>' : '';

        return <<<HTML
<div class="ctrlf-range">
    <div class="ctrlf-range__track">
        <input type="range" id="{$id}" x-model.number="{$p}" min="{$lo}" max="{$hi}" step="{$st}" class="ctrlf-range__slider"{$off}
               :style="{ '--ctrlf-fill': ((({$p} ?? {$lo}) - {$lo}) / ({$hi} - {$lo}) * 100) + '%' }">
        <span class="ctrlf-range__ends" aria-hidden="true"><span>{$lo}</span><span>{$hi}</span></span>
    </div>
    <div class="ctrlf-affix ctrlf-range__value">
        {$unit($before)}<input type="number" class="ctrlf-input ctrlf-input--number" min="{$lo}" max="{$hi}" step="{$st}" inputmode="decimal"{$off}
               :value="{$p} ?? ''" @change="{$p} = \$event.target.value === '' ? null : Math.min({$hi}, Math.max({$lo}, Number(\$event.target.value))); \$event.target.value = {$p} ?? ''">{$unit($after)}
    </div>
</div>
HTML;
    }
}
