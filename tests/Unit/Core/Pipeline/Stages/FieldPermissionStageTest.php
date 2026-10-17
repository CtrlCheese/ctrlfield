<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Core\Pipeline\Stages;

use CtrlField\Builder\FieldGroup;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\SavePipeline;
use CtrlField\Core\Pipeline\Stages\FieldPermissionStage;
use CtrlField\Core\Pipeline\Stages\JsonDecodeStage;
use CtrlField\Core\Pipeline\Stages\PersistenceStage;
use CtrlField\Core\Pipeline\Stages\RulesVerificationStage;
use CtrlField\Core\Pipeline\Stages\SchemaValidationStage;
use CtrlField\Fields\Field;
use CtrlField\Storage\Contracts\StorageAdapterInterface;
use PHPUnit\Framework\TestCase;

final class FieldPermissionStageTest extends TestCase
{
    private StorageAdapterInterface $adapter;

    protected function setUp(): void
    {
        $this->adapter = new class implements StorageAdapterInterface {
            /** @var array<int|string, array<string, mixed>> */
            public array $rows = [];
            public function save(int|string $id, array $data, int $version, array $indexedFields = []): void { $this->rows[$id] = $data; }
            public function load(int|string $id): ?array { return $this->rows[$id] ?? null; }
        };
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_wp_current_user']);
    }

    private function as(string $role): void
    {
        $GLOBALS['_wp_current_user'] = new \CtrlFieldStubUser(7, [$role]);
    }

    /** Saves $payload as the current user through decode → permissions → validation → persistence. */
    private function save(array $payload, array $fields): array
    {
        $group    = FieldGroup::make('perm_test')->fields($fields);
        $context  = new PipelineContext(1, ['ctrlfield_payload' => json_encode($payload)], [$group]);
        $pipeline = new SavePipeline([
            new JsonDecodeStage(),
            new FieldPermissionStage($this->adapter),
            new SchemaValidationStage(),
            new RulesVerificationStage(),
            new PersistenceStage($this->adapter),
        ]);
        $pipeline->process($context);

        return $this->adapter->load(1) ?? [];
    }

    public function test_a_read_only_field_keeps_its_stored_value(): void
    {
        $this->adapter->rows[1] = ['price' => '10', 'title' => 'Old'];
        $this->as('author');

        $stored = $this->save(['price' => '0', 'title' => 'New'], [
            Field::text('title'),
            Field::text('price')->permissions(['author' => 'read']),
        ]);

        $this->assertSame('10', $stored['price'], 'a hand-made request cannot change it');
        $this->assertSame('New', $stored['title']);
    }

    public function test_a_hidden_field_cannot_be_set_when_nothing_is_stored(): void
    {
        $this->as('author');

        $stored = $this->save(['approved' => '1'], [
            Field::text('approved')->permissions(['author' => 'hidden']),
        ]);

        $this->assertArrayNotHasKey('approved', $stored);
    }

    public function test_roles_without_a_rule_can_edit(): void
    {
        $this->adapter->rows[1] = ['price' => '10'];
        $this->as('editor');

        $stored = $this->save(['price' => '12'], [
            Field::text('price')->permissions(['author' => 'read']),
        ]);

        $this->assertSame('12', $stored['price']);
    }

    public function test_a_locked_required_field_does_not_block_the_save(): void
    {
        $this->as('author');

        $stored = $this->save(['title' => 'Mine'], [
            Field::text('title'),
            Field::text('reviewer')->required()->permissions(['author' => 'read']),
        ]);

        $this->assertSame('Mine', $stored['title'], 'the author cannot fill it, so it is not required of them');
    }
}
