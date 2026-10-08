<?php

declare(strict_types=1);

namespace CtrlField\Admin\MetaBox;

use CtrlField\Builder\AdminContext;
use CtrlField\Builder\FieldGroup;
use CtrlField\Registry\ContextRegistry;

/**
 * Registers one meta box per FieldGroup that matches the current post type.
 *
 * Each group gets its own meta box so groups can have independent positions
 * ('normal', 'side', 'after_title') and styles ('default', 'seamless').
 *
 * Excluded from PHPStan — calls WP functions.
 */
class MetaBoxRegistrar
{
    private MetaBoxRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new MetaBoxRenderer();
    }

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBoxes'], 10, 2);
    }

    public function addMetaBoxes(string $postTypeArg = '', mixed $post = null): void
    {
        $screen = get_current_screen();

        if ($screen === null) {
            return;
        }

        $postType = $screen->post_type;
        if (empty($postType)) {
            return;
        }

        // The post itself is needed by location rules (template, parent, term…).
        $context = $post instanceof \WP_Post ? AdminContext::forPost($post->ID) : new AdminContext(postType: $postType);
        $groups  = ContextRegistry::resolve($context);

        if (empty($groups)) {
            return;
        }

        foreach ($groups as $key => $group) {
            $this->registerGroupMetaBox($key, $group, $postType);
        }
    }

    private function registerGroupMetaBox(string $key, FieldGroup $group, string $postType): void
    {
        $title    = $group->getTitle() ?: __('CtrlField Fields', 'ctrlfield');
        $position = $group->getPosition(); // 'normal' | 'side' | 'after_title'
        $style    = $group->getStyle();    // 'default' | 'seamless'

        // Always pass the title: WordPress skips meta boxes without one. The
        // seamless style hides the header with CSS (.ctrlfield-seamless) instead.
        add_meta_box(
            'ctrlfield-' . $key,
            $title,
            function (\WP_Post $post) use ($group): void {
                $this->renderer->renderGroup($post->ID, $group);
            },
            $postType,
            $position,
            'high',
        );

        // For seamless style, remove the meta box wrapper visuals via CSS class.
        if ($style === 'seamless') {
            add_filter('postbox_classes_' . $postType . '_ctrlfield-' . $key, static function (array $classes): array {
                $classes[] = 'ctrlfield-seamless';
                return $classes;
            });
        }
    }
}
