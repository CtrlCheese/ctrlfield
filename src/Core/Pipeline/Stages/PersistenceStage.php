<?php

declare(strict_types=1);

namespace CtrlField\Core\Pipeline\Stages;

use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Core\Pipeline\Contracts\StageInterface;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\Traits\BuildsFieldMap;
use CtrlField\Fields\Contracts\ExternalStorageInterface;
use CtrlField\Storage\Contracts\StorageAdapterInterface;

class PersistenceStage implements StageInterface
{
    use BuildsFieldMap;

    public const NAME           = 'persistence';
    public const SCHEMA_VERSION = SchemaVersion::CURRENT;

    public function __construct(
        private readonly StorageAdapterInterface $adapter
    ) {}

    public function handle(PipelineContext $context): void
    {
        $fieldMap  = self::buildFieldMap($context->fieldGroups);
        $blobData  = [];

        foreach ($context->fields as $key => $value) {
            $definition = $fieldMap[$key] ?? null;

            if ($definition instanceof ExternalStorageInterface) {
                // Field manages its own storage (e.g., RelationshipField → pivot table).
                $definition->persistExternal($context->postId, $value);
            } else {
                $blobData[$key] = $value;
            }
        }

        $this->adapter->save(
            id:            $context->postId,
            data:          $blobData,
            version:       self::SCHEMA_VERSION,
            indexedFields: $context->indexedFields,
        );
    }
}
