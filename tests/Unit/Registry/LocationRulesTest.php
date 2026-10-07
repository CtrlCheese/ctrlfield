<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Registry;

use CtrlField\Builder\AdminContext;
use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Field;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Location rules beyond post type: template, page type, parent, status,
 * format, term, specific post, current user role / capability, edited user role.
 */
final class LocationRulesTest extends TestCase
{
    private const GLOBALS = ['_wp_posts', '_wp_post_types', '_wp_templates', '_wp_options', '_wp_post_formats',
        '_wp_post_terms', '_wp_terms', '_wp_current_user', '_wp_current_user_caps', '_wp_users'];

    protected function setUp(): void
    {
        FieldRegistry::reset();

        $post = static function (int $id, string $type, int $parent = 0, string $status = 'publish'): \WP_Post {
            $p = new \WP_Post();
            $p->ID = $id; $p->post_type = $type; $p->post_parent = $parent; $p->post_status = $status;
            return $p;
        };

        $GLOBALS['_wp_posts'] = [
            10 => $post(10, 'page'),            // front page, top level, has a child
            11 => $post(11, 'page', 10),        // child of 10
            12 => $post(12, 'page', 0, 'draft'),
            20 => $post(20, 'post'),
        ];
        $GLOBALS['_wp_post_types']   = [10 => 'page', 11 => 'page', 12 => 'page', 20 => 'post'];
        $GLOBALS['_wp_templates']    = [11 => 'templates/landing.php'];
        $GLOBALS['_wp_options']      = ['show_on_front' => 'page', 'page_on_front' => 10, 'page_for_posts' => 12];
        $GLOBALS['_wp_post_formats'] = [20 => 'video'];
        $GLOBALS['_wp_post_terms']   = [20 => ['category' => ['news', 5]]];
        $term = new \WP_Term();
        $term->term_id = 5; $term->taxonomy = 'category';
        $GLOBALS['_wp_terms']        = [5 => $term];
        $GLOBALS['_wp_current_user'] = new \CtrlFieldStubUser(1, ['editor']);
        $GLOBALS['_wp_current_user_caps'] = ['edit_posts'];
        $GLOBALS['_wp_users']        = [7 => new \CtrlFieldStubUser(7, ['author'])];
    }

    protected function tearDown(): void
    {
        foreach (self::GLOBALS as $g) {
            unset($GLOBALS[$g]);
        }
        FieldRegistry::reset();
    }

    private function ruleMatches(array $rule, AdminContext $ctx): bool
    {
        $group = FieldGroup::make('g_' . md5(serialize($rule)))->where(...$rule)->fields([Field::text('x')]);
        return ContextRegistry::groupMatches($group, $ctx);
    }

    /** @return array<string, array{0: array, 1: int, 2: bool}> */
    public static function postRules(): array
    {
        return [
            'template default'        => [['page_template', '==', 'default'], 10, true],
            'template landing'        => [['page_template', '==', 'templates/landing.php'], 11, true],
            'template landing (other page)' => [['page_template', '==', 'templates/landing.php'], 10, false],
            'post_template alias'     => [['post_template', '!=', 'default'], 11, true],
            'front page'              => [['page_type', '==', 'front_page'], 10, true],
            'not front page'          => [['page_type', '==', 'front_page'], 11, false],
            'posts page'              => [['page_type', '==', 'posts_page'], 12, true],
            'top level'               => [['page_type', '==', 'top_level'], 10, true],
            'child'                   => [['page_type', '==', 'child'], 11, true],
            'parent'                  => [['page_type', '==', 'parent'], 10, true],
            'leaf is not parent'      => [['page_type', '==', 'parent'], 11, false],
            'parent id'               => [['post_parent', '==', 10], 11, true],
            'status draft'            => [['post_status', '==', 'draft'], 12, true],
            'status not draft'        => [['post_status', '!=', 'draft'], 10, true],
            'format video'            => [['post_format', '==', 'video'], 20, true],
            'format standard'         => [['post_format', '==', 'standard'], 10, true],
            'term by slug'            => [['post_term', '==', 'category:news'], 20, true],
            'term by id'              => [['post_term', '==', 5], 20, true],
            'term missing'            => [['post_term', '==', 'category:sports'], 20, false],
            'specific post'           => [['post', '==', 11], 11, true],
            'specific post (other)'   => [['post', '!=', 11], 10, true],
        ];
    }

    #[DataProvider('postRules')]
    public function test_post_rule(array $rule, int $postId, bool $expected): void
    {
        $this->assertSame($expected, $this->ruleMatches($rule, AdminContext::forPost($postId)));
    }

    public function test_post_rules_never_match_without_a_concrete_post(): void
    {
        // Screens without a post (list table, schema endpoint) must not show
        // post-specific groups — neither for '==' nor for '!='.
        $noPost = new AdminContext(postType: 'page');
        $this->assertFalse($this->ruleMatches(['page_template', '==', 'default'], $noPost));
        $this->assertFalse($this->ruleMatches(['page_template', '!=', 'default'], $noPost));
        $this->assertFalse($this->ruleMatches(['post', '!=', 11], $noPost));
    }

    public function test_combined_with_post_type(): void
    {
        $group = FieldGroup::make('home_hero')
            ->where('post_type', '==', 'page')
            ->where('page_type', '==', 'front_page')
            ->fields([Field::text('headline')]);

        $this->assertTrue(ContextRegistry::groupMatches($group, AdminContext::forPost(10)));
        $this->assertFalse(ContextRegistry::groupMatches($group, AdminContext::forPost(11)));
        $this->assertFalse(ContextRegistry::groupMatches($group, AdminContext::forPost(20)));
    }

    public function test_current_user_rules(): void
    {
        $ctx = AdminContext::forPost(10);
        $this->assertTrue($this->ruleMatches(['current_user_role', '==', 'editor'], $ctx));
        $this->assertFalse($this->ruleMatches(['current_user_role', '==', 'administrator'], $ctx));
        $this->assertTrue($this->ruleMatches(['current_user_can', '==', 'edit_posts'], $ctx));
        $this->assertFalse($this->ruleMatches(['current_user_can', '==', 'manage_options'], $ctx));
    }

    public function test_edited_user_role(): void
    {
        $this->assertTrue($this->ruleMatches(['user_role', '==', 'author'], AdminContext::forUser(7)));
        $this->assertFalse($this->ruleMatches(['user_role', '==', 'editor'], AdminContext::forUser(7)));
        $this->assertFalse($this->ruleMatches(['user_role', '==', 'author'], AdminContext::forUser(0)), 'new-user screen');
    }

    public function test_for_post_resolves_post_type(): void
    {
        $ctx = AdminContext::forPost(11);
        $this->assertSame('page', $ctx->postType);
        $this->assertSame(11, $ctx->postId);
    }
}
