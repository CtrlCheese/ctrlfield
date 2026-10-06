<?php

declare(strict_types=1);

namespace CtrlField\Storage\Drivers;

interface OptionsDriverInterface
{
    public function update(string $key, mixed $value, string $autoload): void;

    public function get(string $key, mixed $default = null): mixed;
}
