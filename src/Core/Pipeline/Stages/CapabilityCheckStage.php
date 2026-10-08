<?php

declare(strict_types=1);

namespace CtrlField\Core\Pipeline\Stages;

use CtrlField\Core\Pipeline\Contracts\StageInterface;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\PipelineException;
use CtrlField\Core\Security\Contracts\CapabilityCheckerInterface;

class CapabilityCheckStage implements StageInterface
{
    public const NAME = 'capability_check';

    public function __construct(
        private readonly CapabilityCheckerInterface $checker,
        private readonly string $capability = 'edit_posts',
        // Meta capability for the object being saved: edit_post, edit_user,
        // edit_term or edit_comment. Checked against the context's ID.
        private readonly string $objectCapability = 'edit_post',
    ) {}

    public function handle(PipelineContext $context): void
    {
        // Base capability gate ('' = none: the object check below is enough)
        if ($this->capability !== '' && ! $this->checker->currentUserCan($this->capability)) {
            throw new PipelineException(
                errorCode:    'INSUFFICIENT_CAPABILITY',
                errorMessage: "You do not have permission to save these fields ({$this->capability}).",
                fieldKey:     null,
                stageName:    self::NAME,
            );
        }

        // Per-object check: being able to edit posts in general is not being able
        // to edit THIS post (an author saving someone else's post).
        if ($context->postId > 0 && ! $this->checker->currentUserCan($this->objectCapability, $context->postId)) {
            throw new PipelineException(
                errorCode:    'INSUFFICIENT_CAPABILITY',
                errorMessage: $this->objectCapability === 'edit_post'
                    ? 'You do not have permission to edit this post.'
                    : "You do not have permission to edit this item ({$this->objectCapability}).",
                fieldKey:     null,
                stageName:    self::NAME,
            );
        }

        // Per-group capability enforcement (A-13 requiredCapability).
        // ContextRegistry::resolve() already filters invisible groups, but a
        // crafted POST can bypass the UI — the pipeline must re-check here.
        foreach ($context->fieldGroups as $group) {
            $required = $group->getRequiredCapability();
            if ($required === '') {
                continue;
            }

            if (! $this->checker->currentUserCan($required)) {
                throw new PipelineException(
                    errorCode:    'INSUFFICIENT_CAPABILITY',
                    errorMessage: "You do not have the '{$required}' capability required by group '{$group->getKey()}'.",
                    fieldKey:     null,
                    stageName:    self::NAME,
                );
            }
        }
    }
}
