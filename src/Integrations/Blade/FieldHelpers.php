<?php

declare(strict_types=1);

namespace FieldForge\Integrations\Blade;

use FieldForge\Fields\Field;
use FieldForge\Fields\Types\SelectField;
use FieldForge\Fields\Types\CheckboxField;

/**
 * Reusable field builder helpers for themes.
 *
 * These are the FieldForge equivalents of CF3's field_background(),
 * field_layout(), field_show_when(), etc. in _base/field-options.php.
 *
 * Usage in a component's fields.php:
 *   use FieldForge\Integrations\Blade\FieldHelpers;
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
            ->label('Background')
            ->options([
                'no-bg'  => 'No Background',
                'light'  => 'Primary — Light (20%)',
                'medium' => 'Primary — Medium (35%)',
                'dark'   => 'Primary — Dark (60%)',
            ])
            ->default($default)
            ->instructions('Section background tint. Uses the Primary Color from Theme Options.');
    }

    /**
     * Title color selector.
     *
     * @param string $default One of: default, primary, secondary, accent
     */
    public static function titleColor(string $default = 'default'): SelectField
    {
        return Field::select('title_color')
            ->label('Title Color')
            ->options([
                'default'   => 'Default',
                'primary'   => 'Primary',
                'secondary' => 'Secondary',
                'accent'    => 'Accent',
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
            ->label('Layout')
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
            $options[(string) $i] = $i . ' columns';
        }

        return Field::select('columns')
            ->label('Columns')
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
            ->label('Heading Level')
            ->options($options)
            ->default($default);
    }

    /**
     * Autoplay toggle for carousels/sliders.
     */
    public static function autoplay(string $default = ''): CheckboxField
    {
        return Field::checkbox('autoplay')
            ->label('Autoplay')
            ->options(['yes' => 'Enable autoplay'])
            ->default($default)
            ->instructions('Automatically advance slides.');
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
