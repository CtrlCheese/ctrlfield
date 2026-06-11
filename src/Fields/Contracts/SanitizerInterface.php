<?php

declare(strict_types=1);

namespace FieldForge\Fields\Contracts;

interface SanitizerInterface
{
    public function sanitize(mixed $value): mixed;
}
