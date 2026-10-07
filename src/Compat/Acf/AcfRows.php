<?php

declare(strict_types=1);

namespace CtrlField\Compat\Acf;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\FlexibleContentInterface;
use CtrlField\Fields\FieldDefinition;

/**
 * have_rows() / the_row() / get_sub_field() loops, as in ACF:
 *
 *   while (have_rows('slides')) { the_row(); echo get_sub_field('title'); }
 *
 * Loops nest: inside a row, have_rows('sub_repeater') walks that row's value.
 */
final class AcfRows
{
    /**
     * @var list<array{selector: string, target: string, rows: list<mixed>, def: FieldDefinition, i: int}>
     */
    private static array $stack = [];

    public static function have(string $selector, mixed $postId = false): bool
    {
        $target = $postId === false ? '' : (string) wp_json_encode(AcfApi::target($postId, null));
        $top    = self::top();

        // The loop already running for this selector: advance or finish.
        if ($top !== null && $top['selector'] === $selector && ($postId === false || $top['target'] === $target)) {
            if ($top['i'] + 1 < count($top['rows'])) {
                return true;
            }
            array_pop(self::$stack);
            return false;
        }

        // A sub-field of the current row, or a top-level field.
        $def = null;
        $raw = null;
        if ($top !== null && $top['i'] >= 0 && $target === '') {
            $defs = self::rowDefs($top);
            if (isset($defs[$selector])) {
                $def = $defs[$selector];
                $raw = $top['rows'][$top['i']][$selector] ?? null;
            }
        }
        if ($def === null) {
            [$def, $raw] = AcfApi::rawField($selector, $postId);
        }

        if ($def === null) {
            return false;
        }

        $rows = match ($def->getType()) {
            FieldType::GROUP => is_array($raw) && $raw !== [] ? [$raw] : [],
            FieldType::REPEATER, FieldType::FLEXIBLE_CONTENT => is_array($raw) ? array_values($raw) : [],
            default => [],
        };

        if ($rows === []) {
            return false;
        }

        self::$stack[] = ['selector' => $selector, 'target' => $target, 'rows' => $rows, 'def' => $def, 'i' => -1];

        return true;
    }

    /** @return array<string, mixed>|false */
    public static function the(): array|false
    {
        $n = count(self::$stack);
        if ($n === 0) {
            return false;
        }
        self::$stack[$n - 1]['i']++;

        return self::row(true);
    }

    public static function sub(string $selector, bool $format = true): mixed
    {
        // The innermost loop first, then the loops around it.
        for ($n = count(self::$stack) - 1; $n >= 0; $n--) {
            $loop = self::$stack[$n];
            if ($loop['i'] < 0) {
                continue;
            }
            $defs = self::rowDefs($loop);
            $key  = isset($defs[$selector]) ? $selector : AcfConverter::keyFor($selector);
            if (! isset($defs[$key])) {
                continue;
            }
            $raw = $loop['rows'][$loop['i']][$key] ?? null;

            return $format ? AcfValues::format($raw, $defs[$key]) : $raw;
        }

        return null;
    }

    /** @return array<string, mixed>|false */
    public static function row(bool $format = false): array|false
    {
        $top = self::top();
        if ($top === null || $top['i'] < 0) {
            return false;
        }
        $row = is_array($top['rows'][$top['i']] ?? null) ? $top['rows'][$top['i']] : [];
        if (! $format) {
            return $row;
        }
        $out = AcfValues::formatSubs($row, self::rowDefs($top));
        if ($top['def']->getType() === FieldType::FLEXIBLE_CONTENT) {
            $out = ['acf_fc_layout' => (string) ($row['_layout'] ?? '')] + $out;
        }

        return $out;
    }

    public static function index(): int
    {
        $top = self::top();

        return $top === null ? 0 : $top['i'] + 1;
    }

    public static function layout(): string|false
    {
        $top = self::top();
        if ($top === null || $top['i'] < 0 || $top['def']->getType() !== FieldType::FLEXIBLE_CONTENT) {
            return false;
        }

        return (string) ($top['rows'][$top['i']]['_layout'] ?? '');
    }

    public static function reset(): bool
    {
        array_pop(self::$stack);

        return true;
    }

    /** For tests. */
    public static function clear(): void
    {
        self::$stack = [];
    }

    // -------------------------------------------------------------------------

    /** @return array{selector: string, target: string, rows: list<mixed>, def: FieldDefinition, i: int}|null */
    private static function top(): ?array
    {
        return self::$stack === [] ? null : self::$stack[count(self::$stack) - 1];
    }

    /**
     * Sub-field definitions of the loop's current row.
     *
     * @param  array{rows: list<mixed>, def: FieldDefinition, i: int} $loop
     * @return array<string, FieldDefinition>
     */
    private static function rowDefs(array $loop): array
    {
        $def = $loop['def'];
        if ($def instanceof FlexibleContentInterface) {
            $layout = (string) ($loop['rows'][$loop['i']]['_layout'] ?? '');
            $out    = [];
            if (in_array($layout, $def->getLayoutKeys(), true)) {
                foreach ($def->getLayoutFields($layout) as $sub) {
                    $out[$sub->getKey()] = $sub;
                }
            }
            return $out;
        }

        return AcfValues::subDefs($def);
    }
}
