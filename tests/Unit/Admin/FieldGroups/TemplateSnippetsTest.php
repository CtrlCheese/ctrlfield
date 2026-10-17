<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\FieldGroups;

use CtrlField\Admin\FieldGroups\TemplateSnippets;
use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

/** "Template code": each field printed with the right escaping, per template language. */
final class TemplateSnippetsTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    private function group(string $where = 'post_type', string $value = 'post'): FieldGroup
    {
        return FieldGroup::make('demo')->title('Demo')->where($where, '==', $value)->fields([
            Field::text('headline'),
            Field::wysiwyg('body'),
            Field::image('photo'),
            Field::link('cta'),
            Field::trueFalse('featured'),
            Field::object('contact')->fields([Field::email('email')]),
            Field::tab('Settings'),
        ]);
    }

    public function test_php_escapes_each_type_for_where_it_is_printed(): void
    {
        $code = TemplateSnippets::generate($this->group(), 'php');

        $this->assertStringContainsString("<?= esc_html(ctrlfield_get('headline')) ?>", $code);
        $this->assertStringContainsString("<?= wp_kses_post(ctrlfield_get('body')) ?>", $code);
        $this->assertStringContainsString("wp_get_attachment_image(\$photo, 'large')", $code);
        $this->assertStringContainsString('<a <?= ctrlfield_link_attrs($cta) ?>><?= esc_html($cta[\'title\']) ?></a>', $code);
        $this->assertStringContainsString("<?php if (!empty(ctrlfield_get('featured'))): ?>", $code);
        $this->assertStringContainsString("mailto:<?= esc_attr(\$email) ?>", $code);
        $this->assertStringNotContainsString('Settings', $code, 'tabs print nothing');
    }

    public function test_twig_escapes_explicitly_because_timber_does_not(): void
    {
        $code = TemplateSnippets::generate($this->group(), 'twig');

        $this->assertStringContainsString("{{ ctrlf('headline')|e }}", $code);
        $this->assertStringContainsString("{{ function('wp_kses_post', ctrlf('body'))|raw }}", $code);
        $this->assertStringContainsString("{{ function('ctrlfield_link_attrs', cta)|raw }}", $code);
    }

    public function test_blade_and_acf(): void
    {
        $this->assertStringContainsString("{{ ctrlfield_get('headline') }}", TemplateSnippets::generate($this->group(), 'blade'));
        $this->assertStringContainsString("esc_html(get_field('headline'))", TemplateSnippets::generate($this->group(), 'acf'));
    }

    public function test_values_are_read_from_where_the_group_is_shown(): void
    {
        $this->assertSame(['type' => 'options', 'page' => 'site-settings'], TemplateSnippets::entity($this->group('options_page', 'site-settings')));
        $this->assertStringContainsString("ctrlfield_get_options('headline', 'site-settings')", TemplateSnippets::generate($this->group('options_page', 'site-settings'), 'php'));
        $this->assertStringContainsString("get_field('headline', 'option')", TemplateSnippets::generate($this->group('options_page', 'site-settings'), 'acf'));
        $this->assertStringContainsString("ctrlfield_get_term('headline', \$term->term_id)", TemplateSnippets::generate($this->group('taxonomy', 'category'), 'php'));
    }

    public function test_an_unknown_language_falls_back_to_php(): void
    {
        $this->assertStringContainsString('ctrlfield_get(', TemplateSnippets::generate($this->group(), 'cobol'));
    }

    public function test_titles_and_labels_cannot_become_template_code(): void
    {
        $group = FieldGroup::make('evil')
            ->title('Hero ?><?php system($_GET[1]); ?> */ --}} #} -#}-}}')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::file('doc')->label('Doc @php(system(1))@endphp {{ 7*7 }} {% set a %}id{% endset %}'),
                Field::trueFalse('on')->label('On ?><?php system(1); ?>'),
            ]);

        foreach (TemplateSnippets::DIALECTS as $dialect) {
            $code = TemplateSnippets::generate($group, $dialect);

            $this->assertStringNotContainsString('system', $this->code($code, $dialect), "$dialect: admin text stays inside comments and text");
            $this->assertStringNotContainsString('@php(system', $code, $dialect);
            $this->assertStringNotContainsString('{{ 7*7 }}', $code, $dialect);
            $this->assertStringNotContainsString('{% set a %}', $code, $dialect);
        }
    }

    /** The parts of the snippet a template engine runs: comments and plain text removed. */
    private function code(string $snippet, string $dialect): string
    {
        if ($dialect === 'php' || $dialect === 'acf') {
            $run = '';
            foreach (token_get_all($snippet) as $t) {
                if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML, T_CONSTANT_ENCAPSED_STRING], true)) {
                    continue;
                }
                $run .= is_array($t) ? $t[1] : $t;
            }
            return $run;
        }
        $comment = $dialect === 'blade' ? '/\{\{--.*?--\}\}/s' : '/\{#.*?#\}/s';
        $tags    = $dialect === 'blade' ? '/(\{\{.*?\}\}|\{!!.*?!!\}|@\w+(\((?:[^()]|(?2))*\))?)/s' : '/(\{\{.*?\}\}|\{%.*?%\})/s';
        preg_match_all($tags, (string) preg_replace($comment, '', $snippet), $m);

        return implode("\n", $m[0]);
    }
}
