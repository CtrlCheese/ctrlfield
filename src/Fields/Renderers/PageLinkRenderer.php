<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\PageLinkField;

/** A <select> of published entries of the field's post types. */
final class PageLinkRenderer extends AbstractRenderer
{
    private const LIMIT = 500;

    public function render(FieldDefinition $field, string $statePath): string
    {
        $postTypes = $field instanceof PageLinkField ? $field->getPostTypes() : ['page'];
        $multiple  = $field instanceof PageLinkField && $field->isMultiple();
        $options   = $multiple ? '' : '<option value=""></option>';

        $posts = function_exists('get_posts') ? get_posts([
            'post_type'      => $postTypes,
            'post_status'    => 'publish',
            'posts_per_page' => self::LIMIT,
            'orderby'        => ['post_type' => 'ASC', 'title' => 'ASC'],
        ]) : [];

        foreach ($posts as $post) {
            $label = $post->post_title !== '' ? $post->post_title : '#' . $post->ID;
            if (count($postTypes) > 1) {
                $label .= ' (' . $post->post_type . ')';
            }
            $options .= sprintf('<option value="%d">%s</option>', $post->ID, $this->esc($label));
        }

        return sprintf(
            '<select id="%s" x-model%s="%s"%s class="ctrlf-input ctrlf-input--select">%s</select>',
            $this->esc($this->inputId($field)),
            $multiple ? '' : '.number',
            $this->esc($statePath),
            $multiple ? ' multiple size="6"' : '',
            $options,
        );
    }
}
