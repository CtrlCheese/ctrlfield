<?php

declare(strict_types=1);

namespace CtrlField\Fields\Contracts;

use CtrlField\Fields\FieldDefinition;

/**
 * Implemented by FlexibleContentField (Pro).
 *
 * Core pipeline stages use this interface to interact with flexible content
 * data without directly importing Pro classes.
 */
interface FlexibleContentInterface
{
    /** @return string[] all registered layout keys */
    public function getLayoutKeys(): array;

    public function getMinLayouts(): ?int;

    public function getMaxLayouts(): ?int;

    /**
     * Returns field definitions for a specific layout, keyed by field key.
     *
     * @return array<string, FieldDefinition>
     */
    public function getLayoutFields(string $layoutKey): array;
}
