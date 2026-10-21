<?php

declare(strict_types=1);

namespace CtrlField\Data;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\FlexibleContentInterface;
use CtrlField\Fields\Contracts\NestedFieldInterface;
use CtrlField\Fields\FieldDefinition;

/**
 * Sections of a flexible content field as the site sees them. A section an
 * editor hid (the eye in its header, stored as _hidden: true) keeps its
 * values in the post but is left out of every template read.
 */
final class FlexRows
{
    public const HIDDEN = '_hidden';

    /**
     * @param  mixed $rows Stored rows of a flexible content field.
     * @return mixed       The same rows without the hidden ones.
     */
    public static function visible(mixed $rows): mixed
    {
        if (! is_array($rows)) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (mixed $row): bool => ! self::isHidden($row),
        ));
    }

    /**
     * A field's value without hidden sections at any depth: flexible fields
     * inside groups, repeater rows and other sections are cleaned too.
     */
    public static function strip(mixed $value, FieldDefinition $field): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($field instanceof FlexibleContentInterface) {
            $rows = self::visible($value);
            foreach ($rows as $i => $row) {
                if (is_array($row)) {
                    $rows[$i] = self::stripRow($row, $field->getLayoutFields(is_string($row['_layout'] ?? null) ? $row['_layout'] : ''));
                }
            }
            return $rows;
        }

        if (! $field instanceof NestedFieldInterface) {
            return $value;
        }

        if ($field->getType() === FieldType::REPEATER) {
            foreach ($value as $i => $row) {
                if (is_array($row)) {
                    $value[$i] = self::stripRow($row, $field->getFields());
                }
            }
            return $value;
        }

        return self::stripRow($value, $field->getFields());
    }

    /**
     * Every flexible field of a set of values (a REST response, all fields of a post).
     *
     * @param  array<string, mixed>       $values
     * @param  iterable<FieldDefinition>  $fields
     * @return array<string, mixed>
     */
    public static function stripAll(array $values, iterable $fields): array
    {
        foreach ($fields as $field) {
            $key = $field->getKey();
            if (array_key_exists($key, $values)) {
                $values[$key] = self::strip($values[$key], $field);
            }
        }

        return $values;
    }

    public static function isHidden(mixed $row): bool
    {
        return is_array($row) && ($row[self::HIDDEN] ?? false) === true;
    }

    /**
     * @param  array<array-key, mixed>           $row
     * @param  array<array-key, FieldDefinition> $fields
     * @return array<array-key, mixed>
     */
    private static function stripRow(array $row, array $fields): array
    {
        foreach ($fields as $sub) {
            $key = $sub->getKey();
            if (array_key_exists($key, $row)) {
                $row[$key] = self::strip($row[$key], $sub);
            }
        }

        return $row;
    }
}
