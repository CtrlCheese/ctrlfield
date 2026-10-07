<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\Contracts\ContextInterface;

/** where('page_template', '==', 'default' | 'templates/landing.php' | block template slug) */
final class PageTemplateResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        if (! $screen instanceof AdminContext || $screen->postId === null) {
            return false; // no concrete post: a rule about a post cannot match
        }

        $template = (string) get_page_template_slug($screen->postId);
        $current  = $template === '' ? 'default' : $template;

        return PostRuleOperator::compare($operator, $current === (string) $value);
    }
}
