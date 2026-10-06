<?php

declare(strict_types=1);

namespace CtrlField\Enums;

enum SanitizationMode: string
{
    case STRICT = 'strict';
    case HTML   = 'html';
    case RAW    = 'raw';
}
