<?php

declare(strict_types=1);

namespace FieldForge\Core\Pipeline\Stages;

use FieldForge\Core\Pipeline\Contracts\StageInterface;
use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\PipelineException;
use FieldForge\Core\Security\Contracts\CapabilityCheckerInterface;

class CapabilityCheckStage implements StageInterface
{
    public const NAME = 'capability_check';

    public function __construct(
        private readonly CapabilityCheckerInterface $checker,
        private readonly string $capability = 'edit_posts',
    ) {}

    public function handle(PipelineContext $context): void
    {
        // Base capability gate
        if (! $this->checker->currentUserCan($this->capability)) {
            throw new PipelineException(
                errorCode:    'INSUFFICIENT_CAPABILITY',
                errorMessage: "You do not have permission to save these fields ({$this->capability}).",
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
