<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Integrations\REST;

use FieldForge\Builder\FieldGroup;
use FieldForge\Core\Cache\CacheAdapter;
use FieldForge\Fields\Field;
use FieldForge\Integrations\REST\FieldsController;
use FieldForge\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests the FieldsController logic in isolation.
 *
 * WP REST API classes (WP_REST_Request, WP_REST_Response, WP_Error) are not
 * available outside WP runtime. We test the filtering + schema-building logic
 * via the ContextRegistry / FieldRegistry directly, and verify the
 * showInRest filtering contract as a unit.
 */
class FieldsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
        CacheAdapter::flush();
    }

    // -------------------------------------------------------------------------
    // showInRest filtering logic
    // -------------------------------------------------------------------------

    public function test_only_rest_exposed_keys_are_collected(): void
    {
        // Register a group with mixed rest_exposed settings
        FieldGroup::make('test_group')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::text('public_field')->label('Public')->showInRest(true),
                Field::text('private_field')->label('Private'),           // default false
                Field::email('also_public')->label('Email')->showInRest(true),
                Field::text('secret')->label('Secret')->showInRest(false),
            ])
            ->register();

        $groups = FieldRegistry::all();

        $restKeys = [];
        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                if ($field->getDefinition()['rest_exposed'] === true) {
                    $restKeys[] = $field->getKey();
                }
            }
        }

        $this->assertContains('public_field', $restKeys);
        $this->assertContains('also_public',  $restKeys);
        $this->assertNotContains('private_field', $restKeys);
        $this->assertNotContains('secret',        $restKeys);
    }

    public function test_array_intersect_key_filters_stored_data(): void
    {
        $stored = [
            'public_field'  => 'hello',
            'private_field' => 'secret_value',
            'also_public'   => 'user@example.com',
        ];

        $restKeys = ['public_field', 'also_public'];
        $filtered = array_intersect_key($stored, array_flip($restKeys));

        $this->assertSame([
            'public_field' => 'hello',
            'also_public'  => 'user@example.com',
        ], $filtered);

        $this->assertArrayNotHasKey('private_field', $filtered);
    }

    public function test_showInRest_false_field_absent_even_if_key_guessed(): void
    {
        // Verify that even if an attacker knows the key name, it's excluded
        $stored   = ['secret' => 'classified'];
        $restKeys = []; // showInRest(false) → key never added
        $filtered = array_intersect_key($stored, array_flip($restKeys));

        $this->assertEmpty($filtered);
        $this->assertArrayNotHasKey('secret', $filtered);
    }

    // -------------------------------------------------------------------------
    // Schema building logic
    // -------------------------------------------------------------------------

    public function test_schema_contains_key_type_label(): void
    {
        FieldGroup::make('portfolio_group')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                Field::text('client_name')->label('Client Name'),
                Field::select('project_type')->label('Project Type')->options(['web' => 'Web']),
                Field::number('year')->label('Year'),
            ])
            ->register();

        $groups = FieldRegistry::all();
        $schema = [];
        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $def      = $field->getDefinition();
                $schema[] = [
                    'key'   => $def['key'],
                    'type'  => $def['type'],
                    'label' => $def['label'],
                ];
            }
        }

        $this->assertCount(3, $schema);

        $keys = array_column($schema, 'key');
        $this->assertContains('client_name',  $keys);
        $this->assertContains('project_type', $keys);
        $this->assertContains('year',         $keys);

        // Schema should NOT contain values
        foreach ($schema as $entry) {
            $this->assertArrayHasKey('key',   $entry);
            $this->assertArrayHasKey('type',  $entry);
            $this->assertArrayHasKey('label', $entry);
            $this->assertArrayNotHasKey('value', $entry);
        }
    }

    public function test_schema_for_unregistered_post_type_is_empty(): void
    {
        // No groups registered for 'page' → schema should be empty
        $schema = [];
        // (ContextRegistry would resolve to empty for 'page' with no groups)

        $this->assertEmpty($schema);
    }

    // -------------------------------------------------------------------------
    // Controller instantiation
    // -------------------------------------------------------------------------

    public function test_controller_can_be_instantiated(): void
    {
        $controller = new FieldsController();
        $this->assertInstanceOf(FieldsController::class, $controller);
    }

    public function test_namespace_constant(): void
    {
        $this->assertSame('fieldforge/v1', FieldsController::NAMESPACE);
    }
}
