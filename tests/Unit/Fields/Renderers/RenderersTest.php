<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Renderers;

use CtrlField\Fields\Field;
use CtrlField\Fields\Renderers\CheckboxRenderer;
use CtrlField\Fields\Renderers\EmailRenderer;
use CtrlField\Fields\Renderers\GroupRenderer;
use CtrlField\Fields\Renderers\ImageRenderer;
use CtrlField\Fields\Renderers\NumberRenderer;
use CtrlField\Fields\Renderers\RadioRenderer;
use CtrlField\Fields\Renderers\SelectRenderer;
use CtrlField\Fields\Renderers\TextareaRenderer;
use CtrlField\Fields\Renderers\TextRenderer;
use CtrlField\Fields\Renderers\UrlRenderer;
use PHPUnit\Framework\TestCase;

class RenderersTest extends TestCase
{
    public function test_text_renderer_outputs_x_model(): void
    {
        $html = (new TextRenderer())->render(
            Field::text('client_name')->label('Client'),
            "adminState['client_name']"
        );

        // Single quotes in Alpine expressions must NOT be entity-encoded.
        $this->assertStringContainsString("x-model=\"adminState['client_name']\"", $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('id="ctrlf-client_name"', $html);
    }

    public function test_textarea_renderer(): void
    {
        $html = (new TextareaRenderer())->render(
            Field::textarea('bio'),
            "adminState['bio']"
        );

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('x-model=', $html);
    }

    public function test_number_renderer_uses_x_model_number(): void
    {
        $html = (new NumberRenderer())->render(
            Field::number('qty'),
            "adminState['qty']"
        );

        $this->assertStringContainsString('x-model.number=', $html);
        $this->assertStringContainsString('type="number"', $html);
    }

    public function test_email_renderer(): void
    {
        $html = (new EmailRenderer())->render(Field::email('email'), "adminState['email']");
        $this->assertStringContainsString('type="email"', $html);
    }

    public function test_url_renderer(): void
    {
        $html = (new UrlRenderer())->render(Field::url('website'), "adminState['website']");
        $this->assertStringContainsString('type="url"', $html);
    }

    public function test_select_renderer_outputs_options(): void
    {
        $html = (new SelectRenderer())->render(
            Field::select('status')->options(['active' => 'Active', 'inactive' => 'Inactive']),
            "adminState['status']"
        );

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('<option value="active">Active</option>', $html);
        $this->assertStringContainsString('<option value="inactive">Inactive</option>', $html);
        $this->assertStringContainsString('x-model=', $html);
    }

    public function test_checkbox_renderer_outputs_each_option(): void
    {
        $html = (new CheckboxRenderer())->render(
            Field::checkbox('tags')->options(['php' => 'PHP', 'js' => 'JavaScript']),
            "adminState['tags']"
        );

        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('value="php"', $html);
        $this->assertStringContainsString('value="js"', $html);
        $this->assertStringContainsString('PHP', $html);
        $this->assertStringContainsString('JavaScript', $html);
    }

    public function test_radio_renderer_outputs_each_option(): void
    {
        $html = (new RadioRenderer())->render(
            Field::radio('size')->options(['sm' => 'Small', 'lg' => 'Large']),
            "adminState['size']"
        );

        $this->assertStringContainsString('type="radio"', $html);
        $this->assertStringContainsString('value="sm"', $html);
        $this->assertStringContainsString('value="lg"', $html);
    }

    public function test_image_renderer_outputs_media_button(): void
    {
        $html = (new ImageRenderer())->render(Field::image('photo'), "adminState['photo']");

        $this->assertStringContainsString('openMediaLibrary', $html);
        $this->assertStringContainsString('x-model=', $html);
        $this->assertStringContainsString('ctrlf-image-field', $html);
    }

    public function test_group_renderer_renders_sub_fields(): void
    {
        $html = (new GroupRenderer())->render(
            Field::object('info')->fields([
                Field::text('first_name')->label('First Name'),
                Field::text('last_name')->label('Last Name'),
            ]),
            "adminState['info']"
        );

        $this->assertStringContainsString('ctrlf-group', $html);
        $this->assertStringContainsString("adminState['info']['first_name']", $html);
        $this->assertStringContainsString("adminState['info']['last_name']", $html);
        $this->assertStringContainsString('First Name', $html);
    }

}
