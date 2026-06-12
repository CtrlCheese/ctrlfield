<?php

declare(strict_types=1);

namespace FieldForge\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\FieldDefinition;

final class UserField extends FieldDefinition
{
    private bool  $multiple = false;
    /** @var list<string> */
    private array $roles    = [];
    private int   $minUsers = 0;
    private int   $maxUsers = 0; // 0 = unlimited

    public function getType(): FieldType
    {
        return FieldType::USER;
    }

    public function multiple(bool $m = true): static
    {
        $this->multiple = $m;
        return $this;
    }

    /** @param list<string> $roles */
    public function roles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function minUsers(int $n): static
    {
        $this->minUsers = $n;
        return $this;
    }

    public function maxUsers(int $n): static
    {
        $this->maxUsers = $n;
        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'multiple'  => $this->multiple,
            'roles'     => $this->roles,
            'min_users' => $this->minUsers,
            'max_users' => $this->maxUsers,
        ]);
    }
}
