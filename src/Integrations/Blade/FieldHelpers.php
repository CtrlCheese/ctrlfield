<?php

declare(strict_types=1);

namespace CtrlField\Integrations\Blade;

use CtrlField\Fields\Field;
use CtrlField\Fields\Types\SelectField;
use CtrlField\Fields\Types\CheckboxField;

/**
 * Reusable field builder helpers for themes.
 *
 * These are the CtrlField equivalents of CF3's field_background(),
 * field_layout(), field_show_when(), etc. in _base/field-options.php.
 *
 * Usage in a component's fields.php:
 *   use CtrlField\Integrations\Blade\FieldHelpers;
 *
 *   Field::group('module_hero')
 *       ->where('post_type', '==', 'page')
 *       ->fields([
 *           Field::text('tag')->label('Tag'),
 *           Field::wysiwyg('title')->label('Title'),
 *           FieldHelpers::layout([
 *               'centro-midia' => 'A · Centre + Media',
 *               'split'        => 'B · Split',
 *               'full-bleed'   => 'C · Full Bleed',
 *           ], 'centro-midia'),
 *           FieldHelpers::background(),
 *           FieldHelpers::titleColor(),
 *       ])
 *       ->register();
 *
 * Each helper returns the field, so anything can be changed by chaining —
 * keys, values and defaults stay the same:
 *   FieldHelpers::background()->label('Fundo')->instructions('Cor de fundo da seção.')
 *   FieldHelpers::autoplay()->instructions('')   // no help text
 *
 * Labels and texts are translatable (text domain "ctrlfield").
 */
final class FieldHelpers
{
    /**
     * Background color selector.
     * Theme's Blade template maps values to CSS classes (e.g. Tailwind bg-primary/{opacity}).
     *
     * @param string $default One of: no-bg, light, medium, dark
     */
    public static function background(string $default = 'no-bg'): SelectField
    {
        return Field::select('background')
            ->label(__('Background', 'ctrlfield'))
            ->options([
                'no-bg'  => __('No Background', 'ctrlfield'),
                'light'  => __('Primary — Light (20%)', 'ctrlfield'),
                'medium' => __('Primary — Medium (35%)', 'ctrlfield'),
                'dark'   => __('Primary — Dark (60%)', 'ctrlfield'),
            ])
            ->default($default)
            ->instructions(__('Section background tint. Uses the Primary Color from Theme Options.', 'ctrlfield'));
    }

    /**
     * Title color selector.
     *
     * @param string $default One of: default, primary, secondary, accent
     */
    public static function titleColor(string $default = 'default'): SelectField
    {
        return Field::select('title_color')
            ->label(__('Title Color', 'ctrlfield'))
            ->options([
                'default'   => __('Default', 'ctrlfield'),
                'primary'   => __('Primary', 'ctrlfield'),
                'secondary' => __('Secondary', 'ctrlfield'),
                'accent'    => __('Accent', 'ctrlfield'),
            ])
            ->default($default);
    }

    /**
     * Layout variant selector.
     * Pass your component-specific options.
     *
     * @param array<string, string> $options ['value' => 'Label', ...]
     */
    public static function layout(array $options, string $default = ''): SelectField
    {
        $field = Field::select('layout')
            ->label(__('Layout', 'ctrlfield'))
            ->options($options);

        if ($default !== '') {
            $field->default($default);
        }

        return $field;
    }

    /**
     * Column count selector.
     */
    public static function columns(int $min = 2, int $max = 4, int $default = 3): SelectField
    {
        $options = [];
        for ($i = $min; $i <= $max; $i++) {
            /* translators: %d: number of columns */
            $options[(string) $i] = sprintf(_n('%d column', '%d columns', $i, 'ctrlfield'), $i);
        }

        return Field::select('columns')
            ->label(__('Columns', 'ctrlfield'))
            ->options($options)
            ->default((string) $default);
    }

    /**
     * Heading level selector.
     *
     * @param string[] $levels Available heading levels, e.g. ['h1','h2','h3']
     */
    public static function headingLevel(array $levels = ['h2', 'h3', 'h4'], string $default = 'h2'): SelectField
    {
        $options = [];
        foreach ($levels as $l) {
            $options[$l] = strtoupper($l);
        }

        return Field::select('heading_level')
            ->label(__('Heading Level', 'ctrlfield'))
            ->options($options)
            ->default($default);
    }

    /**
     * Autoplay toggle for carousels/sliders.
     */
    public static function autoplay(string $default = ''): CheckboxField
    {
        return Field::checkbox('autoplay')
            ->label(__('Autoplay', 'ctrlfield'))
            ->options(['yes' => __('Enable autoplay', 'ctrlfield')])
            ->default($default)
            ->instructions(__('Automatically advance slides.', 'ctrlfield'));
    }

    /**
     * Build a visibleWhen condition array (used with ->visibleWhen()).
     * Equivalent to CF3's field_show_when().
     *
     * Usage:
     *   Field::select('image_position')
     *       ->visibleWhen(...FieldHelpers::showWhen('layout', 'split'))
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function showWhen(string $field, string $value): array
    {
        return [$field, '==', $value];
    }

    /**
     * Build a NOT-equal visibleWhen condition.
     * Equivalent to CF3's field_hide_when().
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function hideWhen(string $field, string $value): array
    {
        return [$field, '!=', $value];
    }
}
