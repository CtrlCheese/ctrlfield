<?php

declare(strict_types=1);

namespace CtrlField\Core\Pipeline\Stages;

use CtrlField\Core\Pipeline\Contracts\StageInterface;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\PipelineException;

class JsonDecodeStage implements StageInterface
{
    public const NAME         = 'json_decode';
    public const PAYLOAD_FIELD = 'ctrlfield_payload';

    public function handle(PipelineContext $context): void
    {
        $raw = $context->rawPost[self::PAYLOAD_FIELD] ?? null;

        if (! isset($raw)) {
            throw new PipelineException(
                errorCode:    'MISSING_PAYLOAD',
                errorMessage: 'No ctrlfield_payload found in the request.',
                fieldKey:     null,
                stageName:    self::NAME,
            );
        }

        if (! is_string($raw)) {
            throw new PipelineException(
                errorCode:    'INVALID_PAYLOAD_TYPE',
                errorMessage: 'ctrlfield_payload must be a JSON string.',
                fieldKey:     null,
                stageName:    self::NAME,
            );
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new PipelineException(
                errorCode:    'MALFORMED_JSON',
                errorMessage: 'Failed to parse ctrlfield_payload: ' . $e->getMessage(),
                fieldKey:     null,
                stageName:    self::NAME,
                previous:     $e,
            );
        }

        if (! is_array($decoded)) {
            throw new PipelineException(
                errorCode:    'INVALID_PAYLOAD_STRUCTURE',
                errorMessage: 'ctrlfield_payload must decode to a JSON object.',
                fieldKey:     null,
                stageName:    self::NAME,
            );
        }

        $context->fields = $decoded;
    }
}
