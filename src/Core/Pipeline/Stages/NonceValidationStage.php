<?php

declare(strict_types=1);

namespace FieldForge\Core\Pipeline\Stages;

use FieldForge\Core\Pipeline\Contracts\StageInterface;
use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\PipelineException;
use FieldForge\Core\Security\Contracts\NonceValidatorInterface;

class NonceValidationStage implements StageInterface
{
    public const NAME         = 'nonce_validation';
    public const NONCE_FIELD  = '_fieldforge_nonce';
    public const NONCE_ACTION = 'fieldforge_save';

    public function __construct(
        private readonly NonceValidatorInterface $validator
    ) {}

    public function handle(PipelineContext $context): void
    {
        $nonce = $context->rawPost[self::NONCE_FIELD] ?? '';

        if (! is_string($nonce) || ! $this->validator->verify($nonce, self::NONCE_ACTION)) {
            throw new PipelineException(
                errorCode:    'INVALID_NONCE',
                errorMessage: 'Security verification failed. Please reload and try again.',
                fieldKey:     null,
                stageName:    self::NAME,
            );
        }
    }
}
