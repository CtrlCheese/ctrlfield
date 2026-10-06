<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Components;

use CtrlField\Components\ComponentDefinition;
use CtrlField\Components\ComponentRegistry;
use CtrlField\Components\ComponentRenderer;
use PHPUnit\Framework\TestCase;

class ComponentRendererTest extends TestCase
{
    private string $tmpBase = '';

    protected function setUp(): void
    {
        ComponentRegistry::reset();
        $this->tmpBase = sys_get_temp_dir() . '/ctrlf_renderer_test_' . uniqid();
        mkdir($this->tmpBase, 0777, true);
    }

    protected function tearDown(): void
    {
        ComponentRegistry::reset();
        $this->removeDir($this->tmpBase);
    }

    public function test_render_returns_empty_string_when_layout_key_missing(): void
    {
        $result = ComponentRenderer::render([]);
        $this->assertSame('', $result);
    }

    public function test_render_returns_empty_string_for_unregistered_component(): void
    {
        $result = ComponentRenderer::render(['_layout' => 'nonexistent']);
        $this->assertSame('', $result);
    }

    public function test_render_returns_empty_string_when_no_template(): void
    {
        $this->registerBare('bare_component', null);

        $result = ComponentRenderer::render(['_layout' => 'bare_component']);
        $this->assertSame('', $result);
    }

    public function test_render_php_template_outputs_html(): void
    {
        $template = $this->tmpBase . '/hero.php';
        file_put_contents($template, '<h1>Hello World</h1>');

        $this->registerBare('hero', $template);

        $result = ComponentRenderer::render(['_layout' => 'hero']);
        $this->assertSame('<h1>Hello World</h1>', $result);
    }

    public function test_render_php_template_exposes_data_via_extract(): void
    {
        $template = $this->tmpBase . '/greeting.php';
        file_put_contents($template, '<?php echo htmlspecialchars($name ?? ""); ?>');

        $this->registerBare('greeting', $template);

        $result = ComponentRenderer::render(['_layout' => 'greeting', 'name' => 'CtrlField']);
        $this->assertSame('CtrlField', $result);
    }

    public function test_render_by_name_injects_layout_key(): void
    {
        $template = $this->tmpBase . '/btn.php';
        file_put_contents($template, 'Button');

        $this->registerBare('btn', $template);

        $result = ComponentRenderer::renderByName('btn');
        $this->assertSame('Button', $result);
    }

    public function test_render_by_name_passes_data_to_template(): void
    {
        $template = $this->tmpBase . '/label.php';
        file_put_contents($template, '<?php echo $text ?? ""; ?>');

        $this->registerBare('label', $template);

        $result = ComponentRenderer::renderByName('label', ['text' => 'Click me']);
        $this->assertSame('Click me', $result);
    }

    public function test_render_returns_empty_on_template_exception(): void
    {
        $template = $this->tmpBase . '/broken.php';
        file_put_contents($template, '<?php throw new \RuntimeException("template error"); ?>');

        $this->registerBare('broken', $template);

        $result = ComponentRenderer::render(['_layout' => 'broken']);
        $this->assertSame('', $result);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function registerBare(string $key, ?string $templatePath): void
    {
        // Directly inject into ComponentRegistry via the discover path is not
        // available publicly, so we use reflection to set internal state.
        $def = new ComponentDefinition(
            key:          $key,
            path:         $this->tmpBase,
            label:        ucfirst($key),
            icon:         '',
            category:     'general',
            templatePath: $templatePath,
            hasScript:    false,
            hasFunctions: false,
        );

        $ref = new \ReflectionProperty(ComponentRegistry::class, 'components');
        $current = $ref->getValue();
        $current[$key] = $def;
        $ref->setValue(null, $current);
    }

    private function removeDir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeDir($full) : unlink($full);
        }

        rmdir($path);
    }
}
