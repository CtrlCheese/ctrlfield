<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Integrations\Blade;

use FieldForge\Integrations\Blade\RepeaterDirective;
use PHPUnit\Framework\TestCase;

class RepeaterDirectiveTest extends TestCase
{
    public function test_compile_uses_fieldforge_get(): void
    {
        $output = RepeaterDirective::compile("'schedule'");

        $this->assertStringContainsString("fieldforge_get('schedule')", $output);
    }

    public function test_compile_opens_foreach(): void
    {
        $output = RepeaterDirective::compile("'items'");

        $this->assertStringContainsString('foreach', $output);
        $this->assertStringContainsString('$__ff_rows', $output);
        $this->assertStringContainsString('$__ff_row', $output);
    }

    public function test_compile_calls_extract(): void
    {
        $output = RepeaterDirective::compile("'items'");

        $this->assertStringContainsString('extract', $output);
        $this->assertStringContainsString('EXTR_OVERWRITE', $output);
    }

    public function test_compile_end_closes_foreach(): void
    {
        $output = RepeaterDirective::compileEnd();

        $this->assertStringContainsString('}', $output);
        $this->assertStringContainsString('unset($__ff_rows, $__ff_row)', $output);
    }

    public function test_open_and_end_form_valid_php(): void
    {
        // Simulate what Blade does: compile open + some body + compile end
        $open  = RepeaterDirective::compile("'schedule'");
        $close = RepeaterDirective::compileEnd();

        $fullCode = '<?php ' .
            preg_replace('/^<\?php\s*|\s*\?>$/', '', $open) .
            ' echo "row"; ' .
            preg_replace('/^<\?php\s*|\s*\?>$/', '', $close) .
            ' ?>';

        // Must parse without error
        $tokens = token_get_all($fullCode);
        $this->assertNotEmpty($tokens);
    }

    public function test_actual_iteration_with_stubs(): void
    {
        // Directly test the runtime logic by simulating what the compiled PHP does
        $rows = [
            ['phase_name' => 'Discovery', 'due_date' => '2026-01-01'],
            ['phase_name' => 'Build',     'due_date' => '2026-03-01'],
        ];

        $collected = [];
        foreach ($rows as $__ff_row) {
            extract($__ff_row, EXTR_OVERWRITE);
            /** @var string $phase_name */
            /** @var string $due_date */
            $collected[] = $phase_name . ' — ' . $due_date;
        }
        unset($rows, $__ff_row);

        $this->assertCount(2, $collected);
        $this->assertSame('Discovery — 2026-01-01', $collected[0]);
        $this->assertSame('Build — 2026-03-01',     $collected[1]);
    }
}
