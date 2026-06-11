<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Admin\Attachment;

use FieldForge\Admin\Attachment\AttachmentModalServiceProvider;
use FieldForge\Bootstrap\ServiceContainer;
use FieldForge\Fields\Field;
use FieldForge\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class AttachmentModalServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    protected function tearDown(): void
    {
        FieldRegistry::reset();
    }

    public function test_register_method_exists_and_is_noop(): void
    {
        $provider = new AttachmentModalServiceProvider(new ServiceContainer());
        $provider->register(); // should not throw

        $this->addToAssertionCount(1);
    }

    public function test_boot_without_wp_functions_does_not_throw(): void
    {
        // add_filter is not available in unit test environment — boot() must guard against this.
        $provider = new AttachmentModalServiceProvider(new ServiceContainer());
        $provider->boot();

        $this->addToAssertionCount(1);
    }

    public function test_render_modal_fields_appends_ff_keys(): void
    {
        Field::group('media_meta')
            ->where('post_type', '==', 'attachment')
            ->fields([
                Field::text('photographer')->label('Photographer Credit'),
                Field::text('license')->label('License'),
            ])
            ->register();

        $provider = new AttachmentModalServiceProvider(new ServiceContainer());

        $post        = $this->makePost(42, 'attachment');
        $formFields  = [];
        $result      = $provider->renderModalFields($formFields, $post);

        $this->assertArrayHasKey('ff_photographer', $result);
        $this->assertArrayHasKey('ff_license', $result);
        $this->assertSame('Photographer Credit', $result['ff_photographer']['label']);
        $this->assertSame('html', $result['ff_photographer']['input']);
        $this->assertStringContainsString('ff_photographer', $result['ff_photographer']['html']);
    }

    public function test_render_modal_fields_preserves_existing_form_fields(): void
    {
        Field::group('media_meta')
            ->where('post_type', '==', 'attachment')
            ->fields([
                Field::text('alt_text')->label('Alt Text'),
            ])
            ->register();

        $provider   = new AttachmentModalServiceProvider(new ServiceContainer());
        $post       = $this->makePost(10, 'attachment');
        $formFields = ['caption' => ['label' => 'Caption', 'input' => 'text', 'html' => '']];
        $result     = $provider->renderModalFields($formFields, $post);

        $this->assertArrayHasKey('caption', $result);
        $this->assertArrayHasKey('ff_alt_text', $result);
    }

    public function test_save_modal_fields_returns_post_unchanged_when_no_ff_keys(): void
    {
        $provider   = new AttachmentModalServiceProvider(new ServiceContainer());
        $post       = ['ID' => 5, 'post_title' => 'Test'];
        $attachment = ['caption' => 'Some caption'];

        $result = $provider->saveModalFields($post, $attachment);

        $this->assertSame($post, $result);
    }

    public function test_save_modal_fields_returns_post_unchanged_when_post_id_zero(): void
    {
        $provider   = new AttachmentModalServiceProvider(new ServiceContainer());
        $post       = [];
        $attachment = ['ff_photographer' => 'John Doe'];

        $result = $provider->saveModalFields($post, $attachment);

        $this->assertSame($post, $result);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePost(int $id, string $postType): \WP_Post
    {
        $post           = $this->createMock(\WP_Post::class);
        $post->ID       = $id;
        $post->post_type = $postType;
        return $post;
    }
}
