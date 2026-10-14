<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Core\Pipeline\Stages;

use CtrlField\Builder\FieldGroup;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\PipelineException;
use CtrlField\Core\Pipeline\Stages\RulesVerificationStage;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

/** Pickers: min/max when visible, "required" too; links: required with ->multiple(). */
final class CollectionRulesTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    /** @param array<int, mixed> $fields */
    private function check(array $fields, array $values): void
    {
        $group       = FieldGroup::make('g')->where('post_type', '==', 'p')->fields($fields);
        $ctx         = new PipelineContext(1, [], [$group]);
        $ctx->fields = $values;
        (new RulesVerificationStage())->handle($ctx);
    }

    public function test_a_required_single_picker_with_nothing_chosen_fails(): void
    {
        $this->expectException(PipelineException::class);
        $this->check([Field::postObject('author_page')->required()], ['author_page' => 0]);
    }

    public function test_user_and_page_link_count_bounds(): void
    {
        $this->check([Field::user('team')->multiple()->minItems(2)->maxItems(3)], ['team' => [1, 2]]);

        $this->expectException(PipelineException::class);
        $this->check([Field::pageLink('pages')->multiple()->minItems(2)], ['pages' => [5]]);
    }

    public function test_a_hidden_picker_is_not_counted(): void
    {
        $this->check([
            Field::trueFalse('show_team'),
            Field::user('team')->multiple()->minItems(2)->visibleWhen('show_team', '==', '1'),
        ], ['show_team' => 0, 'team' => []]);

        $this->assertTrue(true);
    }

    public function test_a_required_multiple_link_with_a_url_passes(): void
    {
        $this->check([Field::link('buttons')->multiple()->required()], ['buttons' => [['url' => '/a', 'title' => 'A']]]);

        $this->expectException(PipelineException::class);
        $this->check([Field::link('buttons')->multiple()->required()], ['buttons' => [['url' => '', 'title' => 'empty']]]);
    }
}
