<?php

declare(strict_types=1);

namespace CtrlField\Enums;

enum AdminTab: string
{
    case CONTENT  = 'content';
    case SIDEBAR  = 'sidebar';
    case SETTINGS = 'settings';
}
