<?php

declare(strict_types=1);

namespace FieldForge\Core\Pipeline\Stages;

use FieldForge\Core\Pipeline\Contracts\StageInterface;
use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\PipelineException;

class JsonDecodeStage implements StageInterface
{
    public const NAME         = 'json_decode';
    public const PAYLOAD_FIELD = 'fieldforge_payload';

    public function handle(PipelineContext $context): void
    {
        $raw = $context->rawPost[self::PAYLOAD_FIELD] ?? null;

        if (! isset($raw)) {
            throw new PipelineException(
                errorCode:    'MISSING_PAYLOAD',
                errorMessage: 'No fieldforge_payload found in the request.',
                fieldKey:     null,
                stageName:    self::NAME,
            );
        }

        if (! is_string($raw)) {
            throw new PipelineException(
                errorCode:    'INVALID_PAYLOAD_TYPE',
                errorMessage: 'fieldforge_payload must be a JSON string.',
                fieldKey:     null,
                stageName:    self::NAME,
            );
        }

        // WordPress adds slashes to $_POST via wp_magic_quotes(). Strip them before decoding.
        if (function_exists('wp_unslash')) {
            $raw = (string) wp_unslash($raw);
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new PipelineException(
                errorCode:    'MALFORMED_JSON',
                errorMessage: 'Failed to parse fieldforge_payload: ' . $e->getMessage(),
                fieldKey:     null,
                stageName:    self::NAME,
                previous:     $e,
            );
        }

        if (! is_array($decoded)) {
            throw new PipelineException(
                errorCode:    'INVALID_PAYLOAD_STRUCTURE',
                errorMessage: 'fieldforge_payload must decode to a JSON object.',
                fieldKey:     null,
                stageName:    self::NAME,
            );
        }

        $context->fields = $decoded;
    }
}
