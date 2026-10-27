<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Data;

use CtrlField\Data\FlexRows;
use PHPUnit\Framework\TestCase;

final class FlexRowsTest extends TestCase
{
    public function test_hidden_sections_are_left_out(): void
    {
        $rows = [
            ['_layout' => 'hero', 'title' => 'A'],
            ['_layout' => 'text', '_hidden' => true, 'title' => 'B'],
            ['_layout' => 'cta', 'title' => 'C'],
        ];

        $this->assertSame(
            [['_layout' => 'hero', 'title' => 'A'], ['_layout' => 'cta', 'title' => 'C']],
            FlexRows::visible($rows)
        );
    }

    public function test_only_true_hides_a_section(): void
    {
        $rows = [['_layout' => 'a', '_hidden' => '1'], ['_layout' => 'b', '_hidden' => false]];

        $this->assertSame($rows, FlexRows::visible($rows));
    }

    public function test_values_that_are_not_rows_pass_through(): void
    {
        $this->assertNull(FlexRows::visible(null));
        $this->assertSame('', FlexRows::visible(''));
    }
}
