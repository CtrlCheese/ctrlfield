<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\Inspector;

use CtrlField\Admin\Inspector\ComponentReport;
use CtrlField\Components\ComponentDefinition;
use CtrlField\Components\ComponentRegistry;
use CtrlField\Schema\SchemaErrors;
use PHPUnit\Framework\TestCase;

class ComponentReportTest extends TestCase
{
    private string $base = '';

    protected function setUp(): void
    {
        ComponentRegistry::reset();
        SchemaErrors::reset();
        $this->base = sys_get_temp_dir() . '/ctrlf_report_test_' . uniqid();
        mkdir($this->base, 0777, true);
    }

    protected function tearDown(): void
    {
        ComponentRegistry::reset();
        SchemaErrors::reset();
        unset($GLOBALS['_wp_test_filters']['ctrlfield/component_issues']);
        $this->removeDir($this->base);
    }

    public function test_files_and_screenshot_are_read_from_the_folder(): void
    {
        $this->component('hero', '<?php return [];', ['index.php' => '<?php', 'script.js' => '', 'screenshot.webp' => '']);
        // The theme loads script.js itself: the file is still listed.
        ComponentRegistry::discover($this->base, 'fields.php', ['scripts' => false]);

        $item = (new ComponentReport([]))->all()['hero'];

        $this->assertSame(['fields.php', 'index.php', 'script.js'], $item['files']);
        $this->assertSame($this->base . '/hero/screenshot.webp', $item['screenshot']);
        $this->assertSame([], $item['issues']);
    }

    public function test_reports_a_broken_fields_file_and_a_missing_template(): void
    {
        $this->component('broken', "<?php throw new \\RuntimeException('Bad field');");
        ComponentRegistry::discover($this->base);

        $issues = (new ComponentReport([], SchemaErrors::all()))->all()['broken']['issues'];

        $this->assertSame('error', $issues[0]['level']);
        $this->assertStringContainsString('fields.php: ', $issues[0]['message']);
        $this->assertStringContainsString('Bad field', $issues[0]['message']);
        $this->assertSame('warning', $issues[1]['level']);
        $this->assertStringContainsString('No template', $issues[1]['message']);
    }

    public function test_keeps_the_schema_problems_of_the_component_layout_only(): void
    {
        $this->component('cta', '<?php return [];', ['index.php' => '<?php']);
        ComponentRegistry::discover($this->base);

        $issues = (new ComponentReport([], [], [
            ['level' => 'warning', 'code' => 'condition_target', 'group' => 'g', 'field' => 'a', 'layout' => 'cta', 'message' => 'In cta'],
            ['level' => 'info', 'code' => 'no_label', 'group' => 'g', 'field' => 'b', 'layout' => 'cta', 'message' => 'Info only'],
            ['level' => 'warning', 'code' => 'condition_target', 'group' => 'g', 'field' => 'c', 'layout' => 'other', 'message' => 'Elsewhere'],
        ]))->all()['cta']['issues'];

        $this->assertSame([['level' => 'warning', 'message' => 'In cta']], $issues);
    }

    public function test_the_theme_adds_its_own_checks_errors_first(): void
    {
        $this->component('cta', '<?php return [];', ['index.php' => '<?php']);
        ComponentRegistry::discover($this->base);
        $GLOBALS['_wp_test_filters']['ctrlfield/component_issues'] = static function (array $issues, ComponentDefinition $c): array {
            $issues[] = ['level' => 'warning', 'message' => 'Convention for ' . $c->folder];
            $issues[] = ['level' => 'error', 'message' => 'No class'];
            $issues[] = ['level' => 'error', 'message' => ''];  // dropped
            $issues[] = 'not an issue';                         // dropped
            return $issues;
        };

        $issues = (new ComponentReport([]))->all()['cta']['issues'];

        $this->assertSame([
            ['level' => 'error', 'message' => 'No class'],
            ['level' => 'warning', 'message' => 'Convention for cta'],
        ], $issues);
    }

    /** @param array<string, string> $files */
    private function component(string $name, string $fields, array $files = []): void
    {
        mkdir($this->base . '/' . $name, 0777, true);
        file_put_contents($this->base . '/' . $name . '/fields.php', $fields);
        foreach ($files as $file => $content) {
            file_put_contents($this->base . '/' . $name . '/' . $file, $content);
        }
    }

    private function removeDir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            is_dir($path . '/' . $entry) ? $this->removeDir($path . '/' . $entry) : unlink($path . '/' . $entry);
        }
        rmdir($path);
    }
}
