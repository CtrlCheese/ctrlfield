<?php

declare(strict_types=1);

namespace FieldForge\Admin\QuickEdit;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\FieldDefinition;

/**
 * Renders plain HTML inputs for the WP_List_Table inline edit row.
 *
 * Quick Edit does not support reactive JavaScript — inputs are plain HTML,
 * prefixed with 'ff_qe_' to avoid collisions with other fields.
 *
 * Excluded from PHPStan — references WP escaping functions.
 */
final class QuickEditRenderer
{
    /**
     * @param FieldDefinition[] $fields
     */
    public function render(array $fields): void
    {
        foreach ($fields as $field) {
            $this->renderField($field);
        }
    }

    private function renderField(FieldDefinition $field): void
    {
        $key   = $field->getKey();
        $label = $field->getQuickEditLabel();

        echo '<fieldset class="inline-edit-col-right">';
        echo '<div class="inline-edit-col">';
        echo '<label>';
        echo '<span class="title">' . esc_html($label) . '</span>';
        $this->renderInput($field);
        echo '</label>';
        echo '</div>';
        echo '</fieldset>';
    }

    private function renderInput(FieldDefinition $field): void
    {
        $key  = $field->getKey();
        $name = 'ff_qe_' . $key;
        $def  = $field->getDefinition();

        match ($field->getType()) {
            FieldType::SELECT   => $this->renderSelect($name, $key, $def),
            FieldType::CHECKBOX => $this->renderCheckboxes($name, $key, $def),
            FieldType::RADIO    => $this->renderRadios($name, $key, $def),
            FieldType::NUMBER,
            FieldType::RANGE    => $this->renderTextInput($name, $key, 'number'),
            FieldType::EMAIL    => $this->renderTextInput($name, $key, 'email'),
            FieldType::URL      => $this->renderTextInput($name, $key, 'url'),
            FieldType::DATE     => $this->renderTextInput($name, $key, 'date'),
            default             => $this->renderTextInput($name, $key, 'text'),
        };
    }

    private function renderTextInput(string $name, string $key, string $type): void
    {
        echo '<input type="' . esc_attr($type) . '" '
            . 'name="' . esc_attr($name) . '" '
            . 'data-ff-key="' . esc_attr($key) . '" '
            . 'value="" class="ptitle">';
    }

    /** @param array<string, mixed> $def */
    private function renderSelect(string $name, string $key, array $def): void
    {
        $options = is_array($def['options'] ?? null) ? $def['options'] : [];

        echo '<select name="' . esc_attr($name) . '" data-ff-key="' . esc_attr($key) . '">';
        echo '<option value="">— ' . esc_html__('No change', 'fieldforge') . ' —</option>';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr((string) $value) . '">' . esc_html((string) $label) . '</option>';
        }
        echo '</select>';
    }

    /** @param array<string, mixed> $def */
    private function renderCheckboxes(string $name, string $key, array $def): void
    {
        $options = is_array($def['options'] ?? null) ? $def['options'] : [];

        foreach ($options as $value => $label) {
            echo '<label>';
            echo '<input type="checkbox" '
                . 'name="' . esc_attr($name) . '[]" '
                . 'data-ff-key="' . esc_attr($key) . '" '
                . 'value="' . esc_attr((string) $value) . '">';
            echo esc_html((string) $label);
            echo '</label>';
        }
    }

    /** @param array<string, mixed> $def */
    private function renderRadios(string $name, string $key, array $def): void
    {
        $options = is_array($def['options'] ?? null) ? $def['options'] : [];

        foreach ($options as $value => $label) {
            echo '<label>';
            echo '<input type="radio" '
                . 'name="' . esc_attr($name) . '" '
                . 'data-ff-key="' . esc_attr($key) . '" '
                . 'value="' . esc_attr((string) $value) . '">';
            echo esc_html((string) $label);
            echo '</label>';
        }
    }
}
