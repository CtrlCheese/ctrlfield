<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

/** '==' keeps the result, '!=' negates it. */
final class PostRuleOperator
{
    public static function compare(string $operator, bool $result): bool
    {
        return $operator === '!=' ? ! $result : $result;
    }
}
