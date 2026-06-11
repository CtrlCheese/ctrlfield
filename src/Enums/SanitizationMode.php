<?php

declare(strict_types=1);

namespace FieldForge\Enums;

enum SanitizationMode: string
{
    case STRICT = 'strict';
    case HTML   = 'html';
    case RAW    = 'raw';
}
