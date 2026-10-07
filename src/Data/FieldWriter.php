<?php

declare(strict_types=1);

namespace CtrlField\Data;

use CtrlField\Builder\FieldGroup;
use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\PipelineException;
use CtrlField\Core\Pipeline\Stages\ComputedFieldsStage;
use CtrlField\Core\Pipeline\Stages\JsonDecodeStage;
use CtrlField\Core\Pipeline\Stages\PersistenceStage;
use CtrlField\Core\Pipeline\Stages\RulesVerificationStage;
use CtrlField\Core\Pipeline\Stages\SanitizationStage;
use CtrlField\Core\Pipeline\Stages\SchemaValidationStage;
use CtrlField\Core\Pipeline\Stages\TypeCoercionStage;
use CtrlField\Fields\Contracts\ExternalStorageInterface;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Registry\FieldRegistry;
use CtrlField\Storage\CommentMetaAdapter;
use CtrlField\Storage\Contracts\StorageAdapterInterface;
use CtrlField\Storage\Drivers\WpOptionsDriver;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Storage\OptionsAdapter;
use CtrlField\Storage\PostMetaAdapter;
use CtrlField\Storage\TermMetaAdapter;
use CtrlField\Storage\UserMetaAdapter;

/**
 * Writes field values from code (update_field(), the ACF importer, …).
 *
 * Values go through the same coercion, validation and sanitization as an
 * editor save, but only for the fields being written: a required field that
 * is not part of the write does not block it. No nonce / capability checks —
 * callers are PHP code, not requests.
 */
final class FieldWriter
{
    /**
     * @param string               $entityType 'post' | 'user' | 'term' | 'comment' | 'options'
     * @param int|string           $entityId   ID, or the options page key
     * @param array<string, mixed> $values     field key => value in CtrlField's storage format
     * @param bool                 $skipInvalid write the valid fields and report the others
     *                                          instead of failing as a whole
     * @param array<string, FieldDefinition>|null $definitions use these instead of the
     *                                          registered fields (importers)
     * @return array<string, string> field key => why it was not written
     *
     * @throws PipelineException when a value is invalid and $skipInvalid is false
     */
    public static function write(string $entityType, int|string $entityId, array $values, bool $skipInvalid = false, ?array $definitions = null): array
    {
        $skipped = [];
        $defs    = [];

        foreach (array_keys($values) as $key) {
            $def = $definitions !== null ? ($definitions[(string) $key] ?? null) : self::findField((string) $key);
            if ($def === null) {
                $skipped[(string) $key] = 'unknown field';
                unset($values[$key]);
                continue;
            }
            $defs[(string) $key] = $def;
        }

        if ($values === []) {
            return $skipped;
        }

        try {
            self::run($entityType, $entityId, $values, $defs);
        } catch (PipelineException $e) {
            if (! $skipInvalid) {
                throw $e;
            }
            // Find the culprits one field at a time.
            foreach ($values as $key => $value) {
                try {
                    self::run($entityType, $entityId, [$key => $value], [$key => $defs[$key]]);
                } catch (PipelineException $fieldError) {
                    $skipped[$key] = $fieldError->getMessage();
                }
            }
        }

        return $skipped;
    }

    /** Remove values (the field definitions stay). */
    public static function delete(string $entityType, int|string $entityId, string ...$keys): void
    {
        $adapter = self::adapter($entityType, $entityId);
        $id      = self::storageId($entityType, $entityId);
        $data    = $adapter->load($id) ?? [];

        foreach ($keys as $key) {
            $def = self::findField($key);
            if ($def instanceof ExternalStorageInterface && is_int($id)) {
                $def->persistExternal($id, []);
            }
            unset($data[$key]);
        }

        $adapter->save($id, $data, SchemaVersion::CURRENT);
    }

    /** Top-level field definition by key, from any registered group. */
    public static function findField(string $key): ?FieldDefinition
    {
        foreach (FieldRegistry::all() as $group) {
            foreach ($group->getFields() as $field) {
                if ($field->getKey() === $key) {
                    return $field;
                }
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed>           $values
     * @param array<string, FieldDefinition> $defs
     */
    private static function run(string $entityType, int|string $entityId, array $values, array $defs): void
    {
        $adapter = self::adapter($entityType, $entityId);
        $id      = self::storageId($entityType, $entityId);
        $group   = FieldGroup::make('_ctrlfield_write')->fields(array_values($defs));
        $context = new PipelineContext(
            is_int($id) ? $id : 0,
            [JsonDecodeStage::PAYLOAD_FIELD => (string) json_encode($values)],
            [$group]
        );

        foreach ([
            new JsonDecodeStage(),
            new SchemaValidationStage(),
            new TypeCoercionStage(),
            new RulesVerificationStage(),
            new SanitizationStage($adapter),
            new ComputedFieldsStage(),
        ] as $stage) {
            $stage->handle($context);
        }

        if (is_int($id)) {
            (new PersistenceStage($adapter))->handle($context);
            return;
        }

        // Options pages are keyed by a string; PersistenceStage expects an ID.
        $adapter->save($id, array_merge($adapter->load($id) ?? [], $context->fields), SchemaVersion::CURRENT);
    }

    private static function storageId(string $entityType, int|string $entityId): int|string
    {
        return $entityType === 'options' ? (string) $entityId : (int) $entityId;
    }

    private static function adapter(string $entityType, int|string $entityId): StorageAdapterInterface
    {
        return match ($entityType) {
            'user'    => new UserMetaAdapter(),
            'term'    => new TermMetaAdapter(),
            'comment' => new CommentMetaAdapter(),
            'options' => self::optionsAdapter((string) $entityId),
            default   => new PostMetaAdapter(new WpPostMetaDriver()),
        };
    }

    /**
     * The pipeline's stages address entities by int; this adapter maps any ID
     * to the options page key so they read and write the right page.
     */
    private static function optionsAdapter(string $pageKey): StorageAdapterInterface
    {
        $inner = new OptionsAdapter(new WpOptionsDriver());

        return new class ($inner, $pageKey) implements StorageAdapterInterface {
            public function __construct(private readonly OptionsAdapter $inner, private readonly string $key) {}

            public function save(int|string $id, array $data, int $version, array $indexedFields = []): void
            {
                $this->inner->save($this->key, $data, $version);
            }

            public function load(int|string $id): ?array
            {
                return $this->inner->load($this->key);
            }
        };
    }
}
