<?php

declare(strict_types=1);

namespace FieldForge\Enums;

enum FieldType: string
{
    case TEXT     = 'text';
    case TEXTAREA = 'textarea';
    case NUMBER   = 'number';
    case EMAIL    = 'email';
    case URL      = 'url';
    case SELECT   = 'select';
    case CHECKBOX = 'checkbox';
    case RADIO    = 'radio';
    case IMAGE    = 'image';
    case FILE     = 'file';
    case GROUP    = 'group';
    case REPEATER = 'repeater';
    case WYSIWYG  = 'wysiwyg';
    case DATE     = 'date';
    case TIME     = 'time';
    case DATETIME = 'datetime';
    case COLOR    = 'color';
    case LINK     = 'link';
    case RANGE            = 'range';
    case OEMBED           = 'oembed';
    case FLEXIBLE_CONTENT = 'flexible_content';
    // Pro relational fields (B-2)
    case POST_OBJECT      = 'post_object';
    case TAXONOMY_TERM    = 'taxonomy_term';
    case RELATIONSHIP     = 'relationship';
    // Pro media + map fields (B-5)
    case GALLERY          = 'gallery';
    case MAP              = 'map';
    // Pro clone field (B-6)
    case CLONE            = 'clone';
    // Computed fields (A-10)
    case COMPUTED         = 'computed';
    // Simple boolean toggle (A-extra)
    case TRUE_FALSE       = 'true_false';
}
