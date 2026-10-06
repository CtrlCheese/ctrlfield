<?php

declare(strict_types=1);

namespace CtrlField\Fields\Conditions;

enum ConditionOperator: string
{
    case Equals             = '==';
    case NotEquals          = '!=';
    case LessThan           = '<';
    case LessThanOrEqual    = '<=';
    case GreaterThan        = '>';
    case GreaterThanOrEqual = '>=';
    case Contains           = 'contains';
    case NotContains        = 'not_contains';
    case Empty              = 'empty';
    case NotEmpty           = 'not_empty';
    case In                 = 'in';
    case NotIn              = 'not_in';
}
