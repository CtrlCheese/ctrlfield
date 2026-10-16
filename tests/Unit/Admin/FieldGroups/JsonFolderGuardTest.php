<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\FieldGroups;

use CtrlField\Admin\FieldGroups\JsonFolderGuard;
use PHPUnit\Framework\TestCase;

final class JsonFolderGuardTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ctrlf-guard-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/{,.}[!.]*', GLOB_BRACE) ?: []);
        @rmdir($this->dir);
    }

    public function test_writes_the_files_that_deny_web_access(): void
    {
        $this->assertFalse(JsonFolderGuard::isProtected($this->dir));
        $this->assertTrue(JsonFolderGuard::protect($this->dir));

        $this->assertStringContainsString('Require all denied', (string) file_get_contents($this->dir . '/.htaccess'));
        $this->assertStringContainsString('Deny from all', (string) file_get_contents($this->dir . '/.htaccess'), 'Apache 2.2 too');
        $this->assertStringContainsString('fileExtension=".json" allowed="false"', (string) file_get_contents($this->dir . '/web.config'));
        $this->assertSame("<?php\n// Silence is golden.\n", file_get_contents($this->dir . '/index.php'));
        $this->assertTrue(JsonFolderGuard::isProtected($this->dir));
    }

    public function test_keeps_a_file_someone_edited(): void
    {
        file_put_contents($this->dir . '/.htaccess', "# custom rules\n");

        JsonFolderGuard::protect($this->dir);

        $this->assertSame("# custom rules\n", file_get_contents($this->dir . '/.htaccess'));
        $this->assertFileExists($this->dir . '/web.config');
    }

    public function test_a_missing_folder_is_not_protected(): void
    {
        $this->assertFalse(JsonFolderGuard::protect($this->dir . '/missing'));
    }

    public function test_finds_the_url_of_a_folder(): void
    {
        $bases = [
            '/srv/wp/wp-content/themes/acme' => 'https://example.com/wp-content/themes/acme',
            '/srv/wp/wp-content'             => 'https://example.com/wp-content/',
        ];

        $this->assertSame('https://example.com/wp-content/themes/acme/ctrlfield-json', JsonFolderGuard::urlFor('/srv/wp/wp-content/themes/acme/ctrlfield-json/', $bases));
        $this->assertSame('https://example.com/wp-content/ctrlfield-json', JsonFolderGuard::urlFor('/srv/wp/wp-content/ctrlfield-json', $bases));
        $this->assertNull(JsonFolderGuard::urlFor('/srv/private/ctrlfield-json', $bases), 'outside the web root');
        $this->assertNull(JsonFolderGuard::urlFor('/srv/wp/wp-content-old/x', $bases), 'a prefix is not a parent folder');
    }

    public function test_nginx_rule_denies_the_folder_path(): void
    {
        $this->assertSame(
            'location ^~ /wp-content/ctrlfield-json/ { deny all; }',
            JsonFolderGuard::nginxRule('https://example.com/wp-content/ctrlfield-json')
        );
    }
}
