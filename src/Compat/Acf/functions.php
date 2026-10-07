<?php

declare(strict_types=1);

/**
 * ACF's template API on top of CtrlField. Loaded only when ACF itself is not
 * active (AcfServiceProvider), so code written for ACF keeps working after
 * the switch. Each function is defined only if nothing else defined it.
 */

use CtrlField\Compat\Acf\AcfApi;
use CtrlField\Compat\Acf\AcfRows;

if (! function_exists('get_field')) {
    function get_field(string $selector, mixed $post_id = false, bool $format_value = true, bool $escape_html = false): mixed
    {
        $value = AcfApi::getField($selector, $post_id, $format_value);

        return $escape_html && is_string($value) ? wp_kses_post($value) : $value;
    }
}

if (! function_exists('the_field')) {
    function the_field(string $selector, mixed $post_id = false, bool $format_value = true): void
    {
        $value = AcfApi::getField($selector, $post_id, $format_value);
        if (is_array($value)) {
            $value = implode(', ', array_filter($value, 'is_scalar'));
        }
        // ACF 6.2.5+ escapes the_field() output the same way.
        echo wp_kses_post(is_scalar($value) ? (string) $value : '');
    }
}

if (! function_exists('get_fields')) {
    /** @return array<string, mixed>|false */
    function get_fields(mixed $post_id = false, bool $format_value = true): array|false
    {
        return AcfApi::getFields($post_id, $format_value);
    }
}

if (! function_exists('get_field_object')) {
    /** @return array<string, mixed>|false */
    function get_field_object(string $selector, mixed $post_id = false, bool $format_value = true, bool $load_value = true): array|false
    {
        return AcfApi::getFieldObject($selector, $post_id, $format_value, $load_value);
    }
}

if (! function_exists('update_field')) {
    function update_field(string $selector, mixed $value, mixed $post_id = false): bool
    {
        return AcfApi::updateField($selector, $value, $post_id);
    }
}

if (! function_exists('delete_field')) {
    function delete_field(string $selector, mixed $post_id = false): bool
    {
        return AcfApi::deleteField($selector, $post_id);
    }
}

if (! function_exists('add_row')) {
    function add_row(string $selector, mixed $row = false, mixed $post_id = false): int|false
    {
        return AcfApi::addRow($selector, $row, $post_id);
    }
}

// ── Loops ────────────────────────────────────────────────────────────────────

if (! function_exists('have_rows')) {
    function have_rows(string $selector, mixed $post_id = false): bool
    {
        return AcfRows::have($selector, $post_id);
    }
}

if (! function_exists('the_row')) {
    /** @return array<string, mixed>|false */
    function the_row(bool $format = false): array|false
    {
        $row = AcfRows::the();

        return $format ? $row : AcfRows::row(false);
    }
}

if (! function_exists('get_sub_field')) {
    function get_sub_field(string $selector = '', bool $format_value = true, bool $escape_html = false): mixed
    {
        $value = AcfRows::sub($selector, $format_value);

        return $escape_html && is_string($value) ? wp_kses_post($value) : $value;
    }
}

if (! function_exists('the_sub_field')) {
    function the_sub_field(string $field_name, bool $format_value = true): void
    {
        $value = AcfRows::sub($field_name, $format_value);
        if (is_array($value)) {
            $value = implode(', ', array_filter($value, 'is_scalar'));
        }
        echo wp_kses_post(is_scalar($value) ? (string) $value : '');
    }
}

if (! function_exists('has_sub_field')) {
    function has_sub_field(string $selector, mixed $post_id = false): bool
    {
        $has = AcfRows::have($selector, $post_id);
        if ($has) {
            AcfRows::the();
        }

        return $has;
    }
}

if (! function_exists('get_row')) {
    /** @return array<string, mixed>|false */
    function get_row(bool $format = false): array|false
    {
        return AcfRows::row($format);
    }
}

if (! function_exists('get_row_index')) {
    function get_row_index(): int
    {
        return AcfRows::index();
    }
}

if (! function_exists('get_row_layout')) {
    function get_row_layout(): string|false
    {
        return AcfRows::layout();
    }
}

if (! function_exists('reset_rows')) {
    function reset_rows(): bool
    {
        return AcfRows::reset();
    }
}

// ── Registration from code ───────────────────────────────────────────────────

if (! function_exists('acf_add_local_field_group')) {
    /** @param array<string, mixed> $field_group */
    function acf_add_local_field_group(array $field_group): bool
    {
        return AcfApi::addLocalFieldGroup($field_group);
    }
}

if (! function_exists('acf_add_options_page')) {
    /**
     * @param  array<string, mixed>|string $page
     * @return array<string, mixed>
     */
    function acf_add_options_page(array|string $page = ''): array
    {
        return AcfApi::addOptionsPage($page);
    }
}

if (! function_exists('acf_add_options_sub_page')) {
    /**
     * @param  array<string, mixed>|string $page
     * @return array<string, mixed>
     */
    function acf_add_options_sub_page(array|string $page = ''): array
    {
        return AcfApi::addOptionsPage($page, AcfApi::firstOptionsPage() ?? 'options-general.php');
    }
}
