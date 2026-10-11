<?php

declare(strict_types=1);

namespace CtrlField\Fields\Conditions;

/**
 * Server-side twin of assets/admin/src/conditionEvaluator.js, so a save can
 * tell which fields the editor saw (a hidden field is not "required").
 *
 * Scopes are value arrays, innermost first: the field's own row, the rows
 * around it, then the top-level values. Each condition field is read from the
 * first scope that has the key (C7.1).
 */
final class ConditionEvaluator
{
    /** @param list<array<string, mixed>> $scopes innermost first */
    public static function isVisible(ConditionGroup $group, array $scopes): bool
    {
        $lookup = static function (string $field) use ($scopes): mixed {
            foreach ($scopes as $scope) {
                if (array_key_exists($field, $scope)) {
                    return $scope[$field];
                }
            }
            return null;
        };

        $conditions = $group->getConditions();

        return match ($group->getType()) {
            'all'   => array_reduce($conditions, static fn (bool $ok, array $c): bool => $ok && self::single($c, $lookup), true),
            'any'   => array_reduce($conditions, static fn (bool $ok, array $c): bool => $ok || self::single($c, $lookup), false),
            default => true,
        };
    }

    /**
     * @param array{0: string, 1: string, 2: mixed} $condition
     * @param callable(string): mixed               $lookup
     */
    private static function single(array $condition, callable $lookup): bool
    {
        [$field, $operator, $value] = $condition;
        $a = $lookup($field) ?? '';

        return match ($operator) {
            '=='           => self::looseEquals($a, $value),
            '!='           => ! self::looseEquals($a, $value),
            '<'            => self::comparable($a) < self::comparable($value),
            '<='           => self::comparable($a) <= self::comparable($value),
            '>'            => self::comparable($a) > self::comparable($value),
            '>='           => self::comparable($a) >= self::comparable($value),
            'contains'     => str_contains(strtolower(self::str($a)), strtolower(self::str($value))),
            'not_contains' => ! str_contains(strtolower(self::str($a)), strtolower(self::str($value))),
            'empty'        => self::isEmpty($a),
            'not_empty'    => ! self::isEmpty($a),
            'in'           => is_array($value) && in_array($a, $value, true),
            'not_in'       => is_array($value) && ! in_array($a, $value, true),
            default        => true,
        };
    }

    /** JS `==` for the values a field holds: "1" == 1, true == 1, '' == 0, true != "yes". */
    private static function looseEquals(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        if (is_string($a) && is_string($b)) {
            return $a === $b; // two strings compare as text in JS ("1" != "1.0")
        }
        if (is_scalar($a) && is_scalar($b)) {
            // A number or boolean on either side: JS converts both to numbers.
            $x = self::toNumber($a);
            $y = self::toNumber($b);
            return $x !== null && $y !== null && $x === $y;
        }

        return $a == $b;
    }

    /** JS ToNumber for scalars; null = NaN. */
    private static function toNumber(mixed $v): ?float
    {
        return match (true) {
            is_bool($v)                => $v ? 1.0 : 0.0,
            is_int($v), is_float($v)   => (float) $v,
            is_string($v) && trim($v) === '' => 0.0,
            is_string($v) && is_numeric(trim($v)) => (float) trim($v),
            default                    => null,
        };
    }

    private static function comparable(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $str = trim(self::str($value));
        if (preg_match('/^(\d{2}):(\d{2})$/', $str, $m) === 1) {
            return (float) ((int) $m[1] * 60 + (int) $m[2]);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $str) === 1) {
            $ts = strtotime($str);
            if ($ts !== false) {
                return (float) $ts * 1000;
            }
        }

        return (float) $str; // like parseFloat(str) || 0
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function isEmpty(mixed $value): bool
    {
        // JS: !value || value.length === 0 for arrays ('0' is a non-empty string)
        return $value === null || $value === '' || $value === false || $value === 0 || $value === 0.0 || $value === [];
    }
}
