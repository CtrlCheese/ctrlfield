<?php

declare(strict_types=1);

namespace FieldForge\Storage\Drivers;

interface PostMetaDriverInterface
{
    public function update(int $postId, string $key, mixed $value): void;

    public function get(int $postId, string $key): mixed;

    public function delete(int $postId, string $key): void;
}
