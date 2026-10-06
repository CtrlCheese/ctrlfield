<?php

declare(strict_types=1);

namespace CtrlField\Builder\Exceptions;

use RuntimeException;

class MissingBlockRendererException extends RuntimeException
{
    public function __construct(string $groupKey)
    {
        parent::__construct(
            "FieldGroup '{$groupKey}' was registered with asBlock() but has neither a renderTemplate nor a renderCallback. "
            . 'Provide at least one: ->asBlock(renderTemplate: "blocks/my.blade.php") or ->asBlock(renderCallback: fn($attrs) => \'...\')'
        );
    }
}
