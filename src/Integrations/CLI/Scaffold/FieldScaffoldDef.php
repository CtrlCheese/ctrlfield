<?php

declare(strict_types=1);

namespace CtrlField\Integrations\CLI\Scaffold;

use CtrlField\Enums\FieldType;
use CtrlField\Integrations\CLI\Scaffold\Exceptions\InvalidSlugException;
use CtrlField\Integrations\CLI\Scaffold\Exceptions\UnknownFieldTypeException;

final class FieldScaffoldDef
{
    public readonly string    $key;
    public readonly FieldType $type;
    public readonly string    $label;

    private function __construct(string $key, FieldType $type, string $label)
    {
        $this->key   = $key;
        $this->type  = $type;
        $this->label = $label;
    }

    /**
     * Parse a single "key:type[:label]" token.
     *
     * @throws InvalidSlugException       if key contains uppercase or invalid chars
     * @throws UnknownFieldTypeException  if type does not match a FieldType case
     */
    public static function fromToken(string $token): self
    {
        $parts = explode(':', $token, 3);
        $key   = $parts[0] ?? '';
        $type  = $parts[1] ?? '';
        $label = $parts[2] ?? '';

        if (! preg_match('/^[a-z0-9_]+$/', $key)) {
            throw new InvalidSlugException(
                "Field key '{$key}' is invalid. Only lowercase letters, digits, and underscores are allowed."
            );
        }

        $fieldType = FieldType::tryFrom($type);

        if ($fieldType === null) {
            throw new UnknownFieldTypeException(
                "Unknown field type '{$type}'. Valid types: " . implode(', ', array_column(FieldType::cases(), 'value'))
            );
        }

        if ($label === '') {
            $label = self::titleCase($key);
        }

        return new self($key, $fieldType, $label);
    }

    /**
     * Parse a comma-separated "--fields" string into an array of FieldScaffoldDef.
     *
     * @return array<int, self>
     * @throws InvalidSlugException
     * @throws UnknownFieldTypeException
     */
    public static function parseMany(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        return array_map(
            static fn (string $token) => self::fromToken(trim($token)),
            explode(',', $raw)
        );
    }

    private static function titleCase(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
