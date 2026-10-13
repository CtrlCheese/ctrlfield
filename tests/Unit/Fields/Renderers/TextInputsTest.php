<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Renderers;

use CtrlField\Builder\FieldGroup;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\PipelineException;
use CtrlField\Core\Pipeline\Stages\RulesVerificationStage;
use CtrlField\Fields\Field;
use CtrlField\Fields\Renderers\NumberRenderer;
use CtrlField\Fields\Renderers\TextareaRenderer;
use CtrlField\Fields\Renderers\TextRenderer;
use CtrlField\Registry\FieldRegistry;
use CtrlField\Schema\JsonGroup;
use PHPUnit\Framework\TestCase;

/** Placeholder, read-only, prepend / append, max length, number bounds. */
final class TextInputsTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    public function test_text_input_draws_what_the_field_defines(): void
    {
        $html = (new TextRenderer())->render(
            Field::text('price')->placeholder('0,00')->prepend('R$')->append('/mês')->maxLength(12)->readOnly(),
            "s['price']"
        );

        $this->assertStringContainsString('placeholder="0,00"', $html);
        $this->assertStringContainsString('maxlength="12"', $html);
        $this->assertStringContainsString(' readonly', $html);
        $this->assertStringContainsString('<span class="ctrlf-affix__text">R$</span>', $html);
        $this->assertStringContainsString('<span class="ctrlf-affix__text">/mês</span>', $html);
        $this->assertStringContainsString('class="ctrlf-counter"', $html);
    }

    public function test_plain_text_input_stays_plain(): void
    {
        $html = (new TextRenderer())->render(Field::text('title'), "s['title']");

        $this->assertStringStartsWith('<input type="text"', $html);
        $this->assertStringNotContainsString('ctrlf-affix', $html);
        $this->assertStringNotContainsString('ctrlf-counter', $html);
    }

    public function test_textarea_rows_and_number_bounds(): void
    {
        $this->assertStringContainsString('rows="3"', (new TextareaRenderer())->render(Field::textarea('bio')->rows(3), "s['bio']"));

        $number = (new NumberRenderer())->render(Field::number('qty')->min(1)->max(10), "s['qty']");
        $this->assertStringContainsString('min="1"', $number);
        $this->assertStringContainsString('max="10"', $number);
        $this->assertStringContainsString('step="any"', $number);
        $this->assertStringContainsString('x-model.number=', $number);
    }

    public function test_save_refuses_too_long_text_and_out_of_range_numbers(): void
    {
        $group = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::text('title')->maxLength(5),
            Field::number('qty')->min(1)->max(10),
        ]);
        $check = static function (array $values) use ($group): void {
            $ctx         = new PipelineContext(1, [], [$group]);
            $ctx->fields = $values;
            (new RulesVerificationStage())->handle($ctx);
        };

        $check(['title' => 'Ação!', 'qty' => 10]); // 5 characters (not bytes), 10 is allowed

        $this->expectException(PipelineException::class);
        $check(['title' => 'Hello', 'qty' => 11]);
    }

    public function test_json_settings_round_trip(): void
    {
        [$g, $errors] = JsonGroup::normalize([
            'key' => 'g', 'title' => 'G', 'location' => [['key' => 'post_type', 'operator' => '==', 'value' => 'page']],
            'fields' => [
                ['key' => 'price', 'type' => 'text', 'prepend' => 'R$', 'maxLength' => '12'],
                ['key' => 'qty', 'type' => 'number', 'min' => '1', 'step' => '0.5'],
            ],
        ]);

        $this->assertSame([], $errors);
        $this->assertSame(['key' => 'price', 'type' => 'text', 'label' => '', 'prepend' => 'R$', 'maxLength' => 12], $g['fields'][0]);
        $this->assertSame(1, $g['fields'][1]['min']);
        $this->assertSame(0.5, $g['fields'][1]['step']);
    }
}
