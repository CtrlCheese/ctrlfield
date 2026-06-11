<?php

declare(strict_types=1);

namespace FieldForge\Builder;

/**
 * Value object holding the Gutenberg block configuration for a FieldGroup.
 *
 * Set via FieldGroup::asBlock(). Read by BlockServiceProvider (Pro) to
 * call register_block_type().
 */
final class BlockConfig
{
    /**
     * @param string          $icon           Dashicons slug or inline SVG string.
     * @param string          $category       Block category: 'text' | 'media' | 'design' | 'widgets' | 'theme' | 'embed'
     * @param string[]        $keywords       Search terms for the block inserter.
     * @param string          $renderTemplate Path to a Blade template relative to get_stylesheet_directory().
     * @param callable|null   $renderCallback PHP callable that returns the rendered HTML string.
     */
    public function __construct(
        public readonly string   $icon,
        public readonly string   $category,
        public readonly array    $keywords,
        public readonly string   $renderTemplate,
        public readonly mixed    $renderCallback,
    ) {}
}
