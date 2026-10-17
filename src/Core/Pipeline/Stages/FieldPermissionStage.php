<?php

declare(strict_types=1);

namespace CtrlField\Core\Pipeline\Stages;

use CtrlField\Core\Pipeline\Contracts\StageInterface;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\Traits\BuildsFieldMap;
use CtrlField\Storage\Contracts\StorageAdapterInterface;

/**
 * Field permissions (->permissions(['author' => 'read'])) on save.
 *
 * A field the current user may only read, or may not see, keeps its stored
 * value whatever the request sends: the form, a hand-made POST or the REST
 * API. Runs on every save path (meta box, quick edit, REST, users, terms),
 * right after the payload is decoded, so the later stages validate the
 * value that will actually be stored. Locked fields are skipped by the
 * required checks: the user cannot change them.
 *
 * Top-level fields only, like the permission map itself.
 */
final class FieldPermissionStage implements StageInterface
{
    use BuildsFieldMap;

    public const NAME = 'field_permissions';

    public function __construct(
        private readonly StorageAdapterInterface $adapter
    ) {}

    public function handle(PipelineContext $context): void
    {
        foreach (self::buildFieldMap($context->fieldGroups) as $key => $field) {
            if ($field->currentUserPermission() !== 'edit') {
                $context->locked[] = (string) $key;
            }
        }

        if ($context->locked !== []) {
            $stored = $this->adapter->load($context->postId) ?? [];
            foreach ($context->locked as $key) {
                if (array_key_exists($key, $stored)) {
                    $context->fields[$key] = $stored[$key];
                } else {
                    unset($context->fields[$key]);
                }
            }
        }

        if (function_exists('apply_filters')) {
            /** Last word on what is saved: (array $fields, int $entityId) → array. */
            $filtered = apply_filters('ctrlfield/pipeline/input_fields', $context->fields, $context->postId);
            if (is_array($filtered)) {
                $context->fields = $filtered;
            }
        }
    }
}
