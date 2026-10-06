<?php

declare(strict_types=1);

namespace CtrlField\Fields\Conditions;

/**
 * Valid context keys for FieldGroup::where() conditions.
 * Built-in keys only — custom keys are registered via ContextRegistry::register().
 */
enum ContextKey: string
{
    case PostType    = 'post_type';
    case OptionsPage = 'options_page';
    case Taxonomy    = 'taxonomy';
    case Context     = 'context';   // value: 'user_profile' | 'comment'
}
