<?php

declare(strict_types=1);

namespace FieldForge\Core\Pipeline;

use RuntimeException;

class PipelineException extends RuntimeException
{
    public function __construct(
        public readonly string  $errorCode,
        string                  $errorMessage,
        public readonly ?string $fieldKey,
        public readonly string  $stageName,
        \Throwable|null         $previous = null,
    ) {
        parent::__construct($errorMessage, 0, $previous);
    }

    /**
     * @return array{code: string, message: string, field: string|null, stage: string}
     */
    public function toArray(): array
    {
        return [
            'code'    => $this->errorCode,
            'message' => $this->getMessage(),
            'field'   => $this->fieldKey,
            'stage'   => $this->stageName,
        ];
    }
}
