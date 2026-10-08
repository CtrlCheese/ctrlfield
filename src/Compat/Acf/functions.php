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

if (! function_exists('acf_add_local_field')) {
    /** @param array<string, mixed> $field Needs 'parent': the ACF key of a local field group. */
    function acf_add_local_field(array $field): bool
    {
        return AcfApi::addLocalField($field);
    }
}

// ── Helpers themes and libraries (Timber, Flynt) call ────────────────────────

if (! function_exists('acf_get_setting')) {
    function acf_get_setting(string $name, mixed $default = null): mixed
    {
        return \CtrlField\Compat\Acf\AcfSettings::get($name, $default);
    }
}

if (! function_exists('acf_update_setting')) {
    function acf_update_setting(string $name, mixed $value): bool
    {
        \CtrlField\Compat\Acf\AcfSettings::set($name, $value);
        return true;
    }
}

if (! function_exists('acf_get_field_type')) {
    function acf_get_field_type(string $name): \CtrlField\Compat\Acf\AcfFieldType
    {
        return \CtrlField\Compat\Acf\AcfFieldType::get($name);
    }
}

if (! function_exists('acf_get_field')) {
    /** @return array<string, mixed>|false */
    function acf_get_field(string $selector): array|false
    {
        return AcfApi::getFieldObject($selector, false, true, false);
    }
}

if (! function_exists('acf_format_date')) {
    /** ACF stores dates as Ymd; returns the date in $format (site-local, no timezone shift). */
    function acf_format_date(mixed $value, string $format): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        $ts = ctype_digit($value) && strlen($value) === 8
            ? (int) strtotime(substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2))
            : strtotime(str_replace('T', ' ', $value));

        return $ts === false ? $value : date_i18n($format, $ts, true);
    }
}

// ── "Is ACF there?" ──────────────────────────────────────────────────────────
// Themes built for ACF (Flynt, Timber's ACF integration) only switch on when
// class ACF exists. This stand-in answers that question; it is not ACF.
// Turn it off with add_filter('ctrlfield/acf_compat_class', '__return_false').

if (! class_exists('ACF', false) && apply_filters('ctrlfield/acf_compat_class', true)) {
    // phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace, Squiz.Classes.ValidClassName
    final class ACF
    {
        /** Marks this as CtrlField's stand-in (see AcfApi::isRealAcf()). */
        public const CTRLFIELD_SHIM = true;

        public string $version = '6.3.0';

        public function get_setting(string $name, mixed $default = null): mixed // phpcs:ignore
        {
            return acf_get_setting($name, $default);
        }

        public function update_setting(string $name, mixed $value): bool // phpcs:ignore
        {
            return acf_update_setting($name, $value);
        }
    }
}

if (! function_exists('acf')) {
    function acf(): ?object
    {
        static $instance = null;
        if ($instance === null && class_exists('ACF', false)) {
            $instance = new ACF();
        }

        return $instance;
    }
}
