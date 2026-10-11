<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin;

use CtrlField\Admin\EntitySave;
use PHPUnit\Framework\TestCase;

/** C7.11: user / term / comment forms must never post a payload that crashes the save. */
class EntitySaveTest extends TestCase
{
    public function test_no_payload_is_nothing_to_save(): void
    {
        $this->assertNull(EntitySave::mergedPayload([]));
    }

    public function test_empty_payload_is_nothing_to_save(): void
    {
        // The field script did not run: the hidden input is posted empty.
        $this->assertNull(EntitySave::mergedPayload(['ctrlfield_payload' => '']));
    }

    public function test_malformed_payload_is_nothing_to_save(): void
    {
        $this->assertNull(EntitySave::mergedPayload(['ctrlfield_payload' => '{not json']));
    }

    public function test_payload_without_fields_is_nothing_to_save(): void
    {
        $this->assertNull(EntitySave::mergedPayload(['ctrlfield_payload' => '{}']));
    }

    public function test_single_payload(): void
    {
        $this->assertSame(
            ['author_position' => 'CEO'],
            EntitySave::mergedPayload(['ctrlfield_payload' => '{"author_position":"CEO"}']),
        );
    }

    public function test_per_group_payloads_are_merged_and_bad_ones_skipped(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => 'x'],
            EntitySave::mergedPayload(['ctrlfield_payload' => [
                'group_one'   => '{"a":1}',
                'group_two'   => '',
                'group_three' => '{"b":"x"}',
                'group_four'  => ['not a string'],
            ]]),
        );
    }

    public function test_slashed_payload_is_unslashed(): void
    {
        // WordPress adds slashes to $_POST.
        $this->assertSame(
            ['note' => 'say "hi"'],
            EntitySave::mergedPayload(['ctrlfield_payload' => addslashes('{"note":"say \"hi\""}')]),
        );
    }
}
