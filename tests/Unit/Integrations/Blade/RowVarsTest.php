<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Integrations\Blade;

use CtrlField\Integrations\Blade\RowVars;
use PHPUnit\Framework\TestCase;

/** @repeater / @flexible extract() a stored row: only field-like names become variables. */
final class RowVarsTest extends TestCase
{
    public function test_keeps_field_keys_and_layout(): void
    {
        $this->assertSame(
            ['title' => 'A', 'cta_link' => 'B', '_layout' => 'hero'],
            RowVars::of(['title' => 'A', 'cta_link' => 'B', '_layout' => 'hero'])
        );
    }

    public function test_a_stored_row_cannot_replace_template_variables(): void
    {
        $row = ['__env' => 1, '__data' => 1, '_ctrlf_flex_item' => 1, 'this' => 1, 'GLOBALS' => 1, 'bad-name' => 1, '1st' => 1, 0 => 1, 'ok' => 2];

        $this->assertSame(['ok' => 2], RowVars::of($row));
    }

    public function test_not_a_row(): void
    {
        $this->assertSame([], RowVars::of('text'));
    }
}
