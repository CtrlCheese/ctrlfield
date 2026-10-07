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
            array_map('unlink', glob($this->dir . '/*') ?: []);
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

    public function test_saves_a_readable_json_file(): void
    {
        $repo = new FieldGroupRepository($this->dir);

        $this->assertSame(FieldGroupRepository::SOURCE_FILE, $repo->save($this->group('hero')));

        $json = (string) file_get_contents($this->dir . '/hero.json');
        $this->assertStringContainsString('"Subtítulo"', $json, 'pretty, unescaped unicode');
        $this->assertSame('Group', $repo->get('hero')['title']);
        $this->assertSame(FieldGroupRepository::SOURCE_FILE, $repo->get('hero')['_source']);
        $this->assertArrayNotHasKey(FieldGroupRepository::OPTION_KEY, $GLOBALS['_wp_options']);
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

        $this->assertFileDoesNotExist($this->dir . '/old.json');
        $this->assertSame(['renamed'], array_keys($repo->all()));

        $repo->delete('renamed');
        $repo->delete('../../etc/passwd');
        $this->assertSame([], $repo->all());
    }
}
