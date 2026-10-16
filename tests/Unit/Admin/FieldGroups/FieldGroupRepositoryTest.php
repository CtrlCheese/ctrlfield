<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\FieldGroups;

use CtrlField\Admin\FieldGroups\FieldGroupRepository;
use CtrlField\Schema\JsonGroup;
use PHPUnit\Framework\TestCase;

final class FieldGroupRepositoryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ctrlfield-json-' . bin2hex(random_bytes(4));
        $GLOBALS['_wp_options'] = [];
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            @chmod($this->dir, 0777);
            array_map('unlink', glob($this->dir . '/{,.}[!.]*', GLOB_BRACE) ?: []);
            rmdir($this->dir);
        }
        unset($GLOBALS['_wp_options']);
    }

    private function group(string $key, string $title = 'Group'): array
    {
        [$g, $errors] = JsonGroup::normalize([
            'key'      => $key,
            'title'    => $title,
            'location' => [['key' => 'post_type', 'operator' => '==', 'value' => 'post']],
            'fields'   => [['key' => 'subtitle', 'type' => 'text', 'label' => 'Subtítulo']],
        ]);
        $this->assertSame([], $errors);

        return $g;
    }

    public function test_the_folder_is_protected_from_web_access(): void
    {
        (new FieldGroupRepository($this->dir))->save($this->group('hero'));

        $this->assertFileExists($this->dir . '/.htaccess');
        $this->assertFileExists($this->dir . '/web.config');
        $this->assertFileExists($this->dir . '/index.php');
    }

    public function test_saves_a_readable_json_file(): void
    {
        $repo = new FieldGroupRepository($this->dir);

        $this->assertSame(FieldGroupRepository::SOURCE_FILE, $repo->save($this->group('hero')));

        $file = $this->dir . '/hero.json.php';
        $this->assertStringStartsWith(FieldGroupRepository::HEADER, (string) file_get_contents($file));
        $json = FieldGroupRepository::read($file);
        $this->assertStringContainsString('"Subtítulo"', $json, 'pretty, unescaped unicode');
        $this->assertIsArray(json_decode($json, true));
        $this->assertSame('Group', $repo->get('hero')['title']);
        $this->assertSame(FieldGroupRepository::SOURCE_FILE, $repo->get('hero')['_source']);
        $this->assertArrayNotHasKey(FieldGroupRepository::OPTION_KEY, $GLOBALS['_wp_options']);
    }

    public function test_a_group_file_opened_in_a_browser_prints_nothing(): void
    {
        $group = $this->group('hero');
        $group['fields'][0]['label']        = 'Hack <?php echo "RAN"; ?> me';
        $group['fields'][0]['instructions'] = 'End with ?> and go on';
        $repo  = new FieldGroupRepository($this->dir);
        $repo->save($group);

        $file = $this->dir . '/hero.json.php';
        $this->assertSame(1, substr_count((string) file_get_contents($file), '<?php'), 'typed text never becomes PHP');
        $this->assertSame(1, substr_count((string) file_get_contents($file), '?>'));

        ob_start();
        include $file; // what the web server does with a request for it
        $this->assertSame('', ob_get_clean());

        $this->assertSame($group['fields'][0]['instructions'], $repo->get('hero')['fields'][0]['instructions'] ?? null, 'read back unchanged');
    }

    public function test_plain_json_files_are_read_and_upgraded(): void
    {
        mkdir($this->dir);
        file_put_contents($this->dir . '/hero.json', json_encode($this->group('hero', 'Plain')));
        $repo = new FieldGroupRepository($this->dir);

        $this->assertSame('Plain', $repo->get('hero')['title']);
        $this->assertSame(['hero'], $repo->upgradeFiles());

        $this->assertFileDoesNotExist($this->dir . '/hero.json');
        $this->assertFileExists($this->dir . '/hero.json.php');
        $this->assertSame('Plain', $repo->get('hero')['title']);
        $this->assertSame([], $repo->upgradeFiles(), 'nothing left to upgrade');
    }

    public function test_protected_file_wins_over_a_plain_one(): void
    {
        $repo = new FieldGroupRepository($this->dir);
        $repo->save($this->group('hero', 'Protected'));
        file_put_contents($this->dir . '/hero.json', json_encode($this->group('hero', 'Plain')));

        $this->assertSame('Protected', $repo->get('hero')['title']);
    }

    public function test_falls_back_to_the_database_when_the_folder_is_read_only(): void
    {
        mkdir($this->dir);
        chmod($this->dir, 0555);
        if (is_writable($this->dir)) {
            $this->markTestSkipped('Running as a user that can write anywhere.');
        }

        $repo = new FieldGroupRepository($this->dir);
        $this->assertFalse($repo->canWriteFiles());
        $this->assertSame(FieldGroupRepository::SOURCE_OPTION, $repo->save($this->group('hero')));
        $this->assertSame(FieldGroupRepository::SOURCE_OPTION, $repo->get('hero')['_source']);
    }

    public function test_file_wins_over_the_database_copy(): void
    {
        $GLOBALS['_wp_options'][FieldGroupRepository::OPTION_KEY] = ['hero' => $this->group('hero', 'From DB')];
        mkdir($this->dir);
        file_put_contents($this->dir . '/hero.json', json_encode($this->group('hero', 'From file')));

        $this->assertSame('From file', (new FieldGroupRepository($this->dir))->get('hero')['title']);
    }

    public function test_saving_to_a_file_removes_the_database_copy(): void
    {
        $GLOBALS['_wp_options'][FieldGroupRepository::OPTION_KEY] = ['hero' => $this->group('hero', 'Old')];

        (new FieldGroupRepository($this->dir))->save($this->group('hero', 'New'));

        $this->assertSame([], $GLOBALS['_wp_options'][FieldGroupRepository::OPTION_KEY]);
    }

    public function test_file_name_is_the_key(): void
    {
        mkdir($this->dir);
        file_put_contents($this->dir . '/hero.json', json_encode(['key' => 'other'] + $this->group('x')));
        file_put_contents($this->dir . '/Bad Name.json', json_encode($this->group('bad')));

        $all = (new FieldGroupRepository($this->dir))->all();

        $this->assertSame(['hero'], array_keys($all));
        $this->assertSame('hero', $all['hero']['key']);
    }

    public function test_broken_or_hand_edited_files_are_validated(): void
    {
        mkdir($this->dir);
        file_put_contents($this->dir . '/broken.json', '{not json');
        $g = $this->group('typo');
        $g['fields'][0]['type'] = 'no_such_type';
        file_put_contents($this->dir . '/typo.json', json_encode($g));

        $all = (new FieldGroupRepository($this->dir))->all();

        $this->assertNotSame([], $all['broken']['_errors']);
        $this->assertNotSame([], $all['typo']['_errors']);
    }

    public function test_rename_and_delete(): void
    {
        $repo = new FieldGroupRepository($this->dir);
        $repo->save($this->group('old'));
        $repo->save($this->group('renamed'), 'old');

        $this->assertFileDoesNotExist($this->dir . '/old.json.php');
        $this->assertSame(['renamed'], array_keys($repo->all()));

        $repo->delete('renamed');
        $repo->delete('../../etc/passwd');
        $this->assertSame([], $repo->all());
    }
}
