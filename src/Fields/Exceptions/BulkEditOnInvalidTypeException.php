<?php

declare(strict_types=1);

namespace CtrlField\Fields\Exceptions;

use RuntimeException;

class BulkEditOnInvalidTypeException extends RuntimeException
{
    public function __construct(string $fieldKey, string $fieldType)
    {
        parent::__construct(
            "Field '{$fieldKey}' (type: {$fieldType}) cannot use bulkEdit() — "
            . 'only select, radio, and checkbox fields with ->options() are supported.'
        );
    }
}
