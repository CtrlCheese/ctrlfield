<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Data\IconLibrary;
use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

/**
 * An icon picked in a dialog with one tab per library (and "All").
 * Stored as "dashicons-admin-site" or "lucide:arrow-right";
 * print it with ctrlfield_icon($value).
 */
final class IconField extends FieldDefinition
{
    /** @var list<string> Libraries offered; empty = all of them. */
    private array $libraries = [];

    public function getType(): FieldType
    {
        return FieldType::ICON;
    }

    /**
     * Which libraries the dialog offers: 'lucide', 'dashicons', a list of
     * them, or 'all' (the default).
     *
     * @param string|list<string> $lib
     */
    public function library(string|array $lib): static
    {
        $this->libraries = array_values(array_intersect(IconLibrary::LIBRARIES, (array) $lib));
        return $this;
    }

    /** @return list<string> */
    public function getLibraries(): array
    {
        return $this->libraries !== [] ? $this->libraries : IconLibrary::LIBRARIES;
    }

    /** 'lucide', 'dashicons' or 'all'. */
    public function getLibrary(): string
    {
        $libs = $this->getLibraries();
        return count($libs) === 1 ? $libs[0] : 'all';
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), ['library' => $this->getLibrary()]);
    }
}
