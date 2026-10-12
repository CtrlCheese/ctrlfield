<?php

declare(strict_types=1);

namespace CtrlField\Fields\Conditions;

/**
 * Immutable value object holding a field visibility condition group.
 *
 * type='all' → field is shown only when ALL conditions evaluate to true
 * type='any' → field is shown when ANY condition evaluates to true
 *
 * @phpstan-type RawCondition array{0: string, 1: string, 2: mixed}
 */
final class ConditionGroup
{
    private const TYPE_ALL = 'all';
    private const TYPE_ANY = 'any';

    /**
     * @param array<int, array{0: string, 1: string, 2: mixed}> $conditions
     */
    private function __construct(
        private readonly string $type,
        private readonly array $conditions,
    ) {}

    /**
     * @param array<int, array{0: string, 1: string, 2: mixed}> $conditions
     */
    public static function all(array $conditions): self
    {
        return new self(self::TYPE_ALL, $conditions);
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: mixed}> $conditions
     */
    public static function any(array $conditions): self
    {
        return new self(self::TYPE_ANY, $conditions);
    }

    public function getType(): string
    {
        return $this->type;
    }

    /** @return array<int, array{0: string, 1: string, 2: mixed}> */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * The same group with field keys renamed ($map: old key => new key);
     * keys not in the map (context keys, other fields) stay.
     *
     * @param array<string, string> $map
     */
    public function withFieldKeys(array $map): self
    {
        $conditions = array_map(
            static fn (array $c): array => [$map[$c[0]] ?? $c[0], $c[1], $c[2]],
            $this->conditions,
        );

        return new self($this->type, $conditions);
    }

    /**
     * Serializes to the format consumed by the Alpine.js condition evaluator.
     *
     * @return array{type: string, conditions: array<int, array{field: string, operator: string, value: mixed}>}
     */
    public function toArray(): array
    {
        return [
            'type'       => $this->type,
            'conditions' => array_map(
                static fn(array $c): array => [
                    'field'    => $c[0],
                    'operator' => $c[1],
                    'value'    => $c[2],
                ],
                $this->conditions,
            ),
        ];
    }
}
