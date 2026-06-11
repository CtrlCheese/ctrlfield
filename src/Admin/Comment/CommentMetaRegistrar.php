<?php

declare(strict_types=1);

namespace FieldForge\Admin\Comment;

use FieldForge\Admin\MetaBox\MetaBoxRenderer;
use FieldForge\Builder\AdminContext;
use FieldForge\Core\Pipeline\SavePipeline;
use FieldForge\Registry\ContextRegistry;
use FieldForge\Storage\CommentMetaAdapter;

/**
 * Registers FieldForge field groups on WordPress comment edit screens.
 * Excluded from PHPStan — references WP functions.
 */
class CommentMetaRegistrar
{
    public function register(): void
    {
        add_action('add_meta_boxes_comment', [$this, 'addMetaBox']);
        add_action('edit_comment',           [$this, 'save']);
    }

    public function addMetaBox(\WP_Comment $comment): void
    {
        $context = new AdminContext(contextType: 'comment');
        $groups  = ContextRegistry::resolve($context);

        if (empty($groups)) {
            return;
        }

        add_meta_box(
            'fieldforge-comment-fields',
            'FieldForge Fields',
            fn() => $this->render($comment),
            'comment',
            'normal',
        );
    }

    public function render(\WP_Comment $comment): void
    {
        $context = new AdminContext(contextType: 'comment');
        $groups  = ContextRegistry::resolve($context);

        if (empty($groups)) {
            return;
        }

        $renderer = new MetaBoxRenderer();
        $renderer->render((int) $comment->comment_ID, array_values($groups));
    }

    public function save(int $commentId): void
    {
        if (! isset($_POST['fieldforge_payload'])) {
            return;
        }

        if (! current_user_can('edit_comment', $commentId)) {
            return;
        }

        SavePipeline::run(
            entityId:        $commentId,
            rawPost:         $_POST,
            adapterOverride: new CommentMetaAdapter(),
            contextOverride: new AdminContext(contextType: 'comment'),
        );
    }
}
