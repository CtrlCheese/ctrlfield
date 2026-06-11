<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Integrations\CLI\Scaffold;

use FieldForge\Enums\FieldType;
use FieldForge\Integrations\CLI\Scaffold\CptScaffoldDef;
use FieldForge\Integrations\CLI\Scaffold\Exceptions\FileExistsException;
use FieldForge\Integrations\CLI\Scaffold\FieldGroupScaffoldDef;
use FieldForge\Integrations\CLI\Scaffold\FieldScaffoldDef;
use FieldForge\Integrations\CLI\Scaffold\OptionsPageScaffoldDef;
use FieldForge\Integrations\CLI\Scaffold\PhpCodeWriter;
use FieldForge\Integrations\CLI\Scaffold\TaxonomyScaffoldDef;
use PHPUnit\Framework\TestCase;

class PhpCodeWriterTest extends TestCase
{
    private PhpCodeWriter $writer;

    protected function setUp(): void
    {
        $this->writer = new PhpCodeWriter();
    }

    // -------------------------------------------------------------------------
    // CPT
    // -------------------------------------------------------------------------

    public function testWriteCptProducesValidPhp(): void
    {
        $def  = $this->makeCptDef();
        $code = $this->writer->writeCpt($def);

        self::assertStringStartsWith('<?php', $code);
        self::assertStringContainsString("declare(strict_types=1);", $code);
        self::assertStringContainsString("use FieldForge\\Builder\\CPT;", $code);
        self::assertStringContainsString("CPT::make('portfolio')", $code);
        self::assertStringContainsString("->label('Portfolio', 'Projects')", $code);
        self::assertStringContainsString("->supports(['title', 'editor', 'thumbnail'])", $code);
        self::assertStringContainsString("->register();", $code);
    }

    public function testWriteCptWithFieldsIncludesFieldGroup(): void
    {
        $fields = [FieldScaffoldDef::fromToken('client_name:text:Client')];
        $def    = new CptScaffoldDef('portfolio', 'Portfolio', 'Projects', 'dashicons-admin-post', ['title', 'editor', 'thumbnail'], $fields, false);
        $code   = $this->writer->writeCpt($def);

        self::assertStringContainsString("use FieldForge\\Fields\\Field;", $code);
        self::assertStringContainsString("Field::group('portfolio_fields')", $code);
        self::assertStringContainsString("->where('post_type', '==', 'portfolio')", $code);
        self::assertStringContainsString("Field::text('client_name')", $code);
        self::assertStringContainsString("->label('Client'),", $code);
    }

    public function testWriteCptNoRegisterOmitsRegisterCall(): void
    {
        $def  = new CptScaffoldDef('portfolio', 'Portfolio', 'Projects', 'dashicons-admin-post', ['title', 'editor'], [], true);
        $code = $this->writer->writeCpt($def);

        self::assertStringNotContainsString('->register();', $code);
    }

    public function testWriteCptOutputPassesPhpLint(): void
    {
        $def  = $this->makeCptDef();
        $code = $this->writer->writeCpt($def);

        $this->assertValidPhpSyntax($code);
    }

    public function testWriteCptSnapshotMatchesFixture(): void
    {
        $def  = new CptScaffoldDef(
            'portfolio',
            'Portfolio',
            'Projects',
            'dashicons-admin-post',
            ['title', 'editor', 'thumbnail'],
            [
                FieldScaffoldDef::fromToken('client_name:text:Client'),
                FieldScaffoldDef::fromToken('budget:number:Budget'),
            ],
            false,
        );

        $code = $this->writer->writeCpt($def);

        // Replace the generated date so the snapshot stays stable
        $code = preg_replace('/ \* Generated: \d{4}-\d{2}-\d{2}/', ' * Generated: DATE', $code);

        $fixture = __DIR__ . '/fixtures/cpt-portfolio.php.txt';

        if (! file_exists($fixture)) {
            file_put_contents($fixture, $code);
            $this->markTestSkipped("Fixture created at {$fixture} — re-run to verify.");
        }

        self::assertSame(file_get_contents($fixture), $code);
    }

    // -------------------------------------------------------------------------
    // Taxonomy
    // -------------------------------------------------------------------------

    public function testWriteTaxonomyProducesValidPhp(): void
    {
        $def = new TaxonomyScaffoldDef('category', 'Category', 'Categories', ['portfolio'], true, [], false);
        $code = $this->writer->writeTaxonomy($def);

        self::assertStringContainsString("use FieldForge\\Builder\\Taxonomy;", $code);
        self::assertStringContainsString("Taxonomy::make('category')", $code);
        self::assertStringContainsString("->hierarchical()", $code);
        self::assertStringContainsString("->register();", $code);

        $this->assertValidPhpSyntax($code);
    }

    public function testWriteTaxonomyWithFieldsIncludesGroup(): void
    {
        $fields = [FieldScaffoldDef::fromToken('icon:image:Icon')];
        $def    = new TaxonomyScaffoldDef('category', 'Category', 'Categories', ['portfolio'], false, $fields, false);
        $code   = $this->writer->writeTaxonomy($def);

        self::assertStringContainsString("Field::group('category_term_fields')", $code);
        self::assertStringContainsString("->where('taxonomy', '==', 'category')", $code);
        self::assertStringContainsString("Field::image('icon')", $code);
    }

    // -------------------------------------------------------------------------
    // Options Page
    // -------------------------------------------------------------------------

    public function testWriteOptionsPageProducesValidPhp(): void
    {
        $def  = new OptionsPageScaffoldDef('agency', 'Agency Settings', 'manage_options', '', [], false);
        $code = $this->writer->writeOptionsPage($def);

        self::assertStringContainsString("use FieldForge\\Builder\\OptionsPage;", $code);
        self::assertStringContainsString("OptionsPage::make('agency')", $code);
        self::assertStringContainsString("->register();", $code);

        $this->assertValidPhpSyntax($code);
    }

    public function testWriteOptionsPageWithFieldsAndParent(): void
    {
        $fields = [FieldScaffoldDef::fromToken('api_key:text:API Key')];
        $def    = new OptionsPageScaffoldDef('sub_page', 'Sub Page', 'manage_options', 'parent_menu', $fields, false);
        $code   = $this->writer->writeOptionsPage($def);

        self::assertStringContainsString("->parent('parent_menu')", $code);
        self::assertStringContainsString("Field::text('api_key')", $code);
    }

    // -------------------------------------------------------------------------
    // Field Group
    // -------------------------------------------------------------------------

    public function testWriteFieldGroupProducesValidPhp(): void
    {
        $fields = [FieldScaffoldDef::fromToken('meta_title:text:Meta Title')];
        $def    = new FieldGroupScaffoldDef('portfolio_seo', 'Portfolio SEO', 'portfolio', null, null, null, $fields, false);
        $code   = $this->writer->writeFieldGroup($def);

        self::assertStringContainsString("use FieldForge\\Fields\\Field;", $code);
        self::assertStringContainsString("Field::group('portfolio_seo')", $code);
        self::assertStringContainsString("->where('post_type', '==', 'portfolio')", $code);
        self::assertStringContainsString("->register();", $code);

        $this->assertValidPhpSyntax($code);
    }

    public function testWriteFieldGroupWithOptionsPageWhere(): void
    {
        $def  = new FieldGroupScaffoldDef('agency_fields', 'Agency Fields', null, null, 'agency', null, [], false);
        $code = $this->writer->writeFieldGroup($def);

        self::assertStringContainsString("->where('options_page', '==', 'agency')", $code);
    }

    public function testWriteFieldGroupComplexTypesGetTodoComment(): void
    {
        $fields = [FieldScaffoldDef::fromToken('gallery:repeater:Gallery')];
        $def    = new FieldGroupScaffoldDef('test_group', 'Test', 'portfolio', null, null, null, $fields, false);
        $code   = $this->writer->writeFieldGroup($def);

        self::assertStringContainsString('// TODO: define sub-fields manually', $code);
        self::assertStringContainsString("Field::repeater('gallery')", $code);
    }

    // -------------------------------------------------------------------------
    // writeFile
    // -------------------------------------------------------------------------

    public function testWriteFileCreatesFile(): void
    {
        $path = sys_get_temp_dir() . '/fieldforge_test_' . uniqid() . '.php';
        $code = "<?php\n// test\n";

        $this->writer->writeFile($code, $path, false);

        self::assertFileExists($path);
        self::assertSame($code, file_get_contents($path));

        unlink($path);
    }

    public function testWriteFileThrowsWhenFileExistsWithoutForce(): void
    {
        $path = sys_get_temp_dir() . '/fieldforge_test_' . uniqid() . '.php';
        file_put_contents($path, '<?php');

        $this->expectException(FileExistsException::class);

        try {
            $this->writer->writeFile("<?php\n", $path, false);
        } finally {
            unlink($path);
        }
    }

    public function testWriteFileOverwritesWithForce(): void
    {
        $path = sys_get_temp_dir() . '/fieldforge_test_' . uniqid() . '.php';
        file_put_contents($path, '<?php // old');

        $this->writer->writeFile("<?php // new\n", $path, true);

        self::assertStringContainsString('new', (string) file_get_contents($path));

        unlink($path);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCptDef(): CptScaffoldDef
    {
        return new CptScaffoldDef(
            'portfolio',
            'Portfolio',
            'Projects',
            'dashicons-admin-post',
            ['title', 'editor', 'thumbnail'],
            [],
            false,
        );
    }

    private function assertValidPhpSyntax(string $code): void
    {
        $tmp = sys_get_temp_dir() . '/fieldforge_lint_' . uniqid() . '.php';
        file_put_contents($tmp, $code);

        exec(sprintf('php -l %s 2>&1', escapeshellarg($tmp)), $output, $exitCode);
        unlink($tmp);

        self::assertSame(0, $exitCode, implode("\n", $output));
    }
}
