<?php

declare(strict_types=1);

namespace FieldForge\Enums;

enum AdminTab: string
{
    case CONTENT  = 'content';
    case SIDEBAR  = 'sidebar';
    case SETTINGS = 'settings';
}
