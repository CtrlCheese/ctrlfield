<?php

declare(strict_types=1);

namespace CtrlField\Admin\Comment;

use CtrlField\Admin\MetaBox\MetaBoxRenderer;
use CtrlField\Builder\AdminContext;
use CtrlField\Core\Pipeline\SavePipeline;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Storage\CommentMetaAdapter;

/**
 * Registers CtrlField field groups on WordPress comment edit screens.
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
            'ctrlfield-comment-fields',
            'CtrlField Fields',
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
        if (! isset($_POST['ctrlfield_payload'])) {
            return;
        }

        if (! current_user_can('edit_comment', $commentId)) {
            return;
        }

        // Verify the CtrlField nonce explicitly before passing to the pipeline.
        if (! isset($_POST['_ctrlfield_nonce'])
            || ! wp_verify_nonce(sanitize_key($_POST['_ctrlfield_nonce']), 'ctrlfield_save')
        ) {
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
