<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

/**
 * The calendar shared by Date, Date time and Time (assets/admin/src/dates.js):
 * a button showing the value in the site's (or the field's) format, × to
 * clear, and a popover with the month grid and / or hour and minute.
 */
final class DatePicker
{
    /**
     * @param 'date'|'datetime'|'time' $mode
     * @param string                   $format PHP date format for display; '' = the site's
     */
    public static function render(
        string $id,
        string $statePath,
        string $mode,
        string $format,
        ?string $min,
        ?string $max,
        int $step,
        bool $readOnly,
    ): string {
        $e      = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $p      = $e($statePath);
        $config = $e((string) wp_json_encode(['mode' => $mode, 'format' => $format, 'min' => $min, 'max' => $max, 'step' => $step], JSON_HEX_APOS | JSON_HEX_QUOT));
        $off    = $readOnly ? ' disabled' : '';
        $empty  = $e((string) wp_json_encode(match ($mode) {
            'time'     => __('Choose a time…', 'ctrlfield'),
            'datetime' => __('Choose a date and time…', 'ctrlfield'),
            default    => __('Choose a date…', 'ctrlfield'),
        }));
        $clear  = $e(__('Clear', 'ctrlfield'));
        $prev   = $e(__('Previous month', 'ctrlfield'));
        $next   = $e(__('Next month', 'ctrlfield'));
        $today  = $e($mode === 'date' ? __('Today', 'ctrlfield') : __('Now', 'ctrlfield'));
        $done   = $e(__('Done', 'ctrlfield'));
        $hour   = $e(__('Hour', 'ctrlfield'));
        $minute = $e(__('Minute', 'ctrlfield'));

        $icon = $mode === 'time'
            ? '<svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M10 6v4l3 2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>'
            : '<svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><rect x="3" y="4.5" width="14" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3 8.5h14M7 3v3M13 3v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';

        $calendar = $mode === 'time' ? '' : <<<HTML
        <div class="ctrlf-cal__head">
            <button type="button" class="ctrlf-cal__nav" @click="shift(-1)" aria-label="{$prev}" title="{$prev}">‹</button>
            <strong class="ctrlf-cal__title" x-text="title()"></strong>
            <button type="button" class="ctrlf-cal__nav" @click="shift(1)" aria-label="{$next}" title="{$next}">›</button>
        </div>
        <div class="ctrlf-cal__grid" role="grid" @keydown="key(\$event, {$p}, (v) => {$p} = v)">
            <template x-for="w in weekdays" :key="w"><span class="ctrlf-cal__weekday" x-text="w"></span></template>
            <template x-for="c in days()" :key="c.iso">
                <button type="button" class="ctrlf-cal__day" role="gridcell" :tabindex="c.iso === focusIso ? 0 : -1"
                        :class="{ 'is-out': !c.inMonth, 'is-today': isToday(c.iso), 'is-selected': c.iso === selectedIso({$p}), 'is-focus': c.iso === focusIso }"
                        :aria-selected="c.iso === selectedIso({$p})" :disabled="disabled(c.iso)"
                        @click="{$p} = pickDay({$p}, c)" x-text="c.d"></button>
            </template>
        </div>
HTML;

        $time = $mode === 'date' ? '' : <<<HTML
        <div class="ctrlf-cal__time">
            <select class="ctrlf-input" aria-label="{$hour}" :value="part({$p}, 'h')" @change="{$p} = setTime({$p}, 'h', \$event.target.value)">
                <template x-for="h in hours" :key="h"><option :value="h" x-text="String(h).padStart(2, '0')" :selected="h === part({$p}, 'h')"></option></template>
            </select>
            <span aria-hidden="true">:</span>
            <select class="ctrlf-input" aria-label="{$minute}" :value="part({$p}, 'i')" @change="{$p} = setTime({$p}, 'i', \$event.target.value)">
                <template x-for="m in minutes" :key="m"><option :value="m" x-text="String(m).padStart(2, '0')" :selected="m === part({$p}, 'i')"></option></template>
            </select>
        </div>
HTML;

        $doneBtn = $mode === 'date' ? '' : '<button type="button" class="button button-primary" @click="close()">' . $done . '</button>';

        return <<<HTML
<div class="ctrlf-date" x-data="ctrlfDate({$config})" @click.outside="open = false" @keydown.escape.stop="open && close()">
    <div class="ctrlf-date__field">
        <button type="button" id="{$id}" class="ctrlf-input ctrlf-date__button" :class="{ 'is-empty': !{$p} }"
                aria-haspopup="dialog" :aria-expanded="open"{$off} @click="toggle({$p})">
            <span x-text="{$p} ? display({$p}) : {$empty}"></span>{$icon}
        </button>
        <button type="button" class="ctrlf-picker__x" x-show="{$p}" @click="{$p} = ''" aria-label="{$clear}" title="{$clear}"{$off}>
            <svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
        </button>
    </div>
    <div class="ctrlf-cal ctrlf-cal--{$mode}" role="dialog" x-show="open" x-cloak>
        {$calendar}
        {$time}
        <div class="ctrlf-cal__foot">
            <button type="button" class="button-link" @click="{$p} = now(); if (mode === 'date') close()">{$today}</button>
            {$doneBtn}
        </div>
    </div>
</div>
HTML;
    }
}
