<?php

declare(strict_types=1);

namespace CtrlField\Fields;

use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Types\AccordionEndField;
use CtrlField\Fields\Types\AccordionField;
use CtrlField\Fields\Types\ButtonGroupField;
use CtrlField\Fields\Types\CheckboxField;
use CtrlField\Fields\Types\CodeField;
use CtrlField\Fields\Types\ColorField;
use CtrlField\Fields\Types\DateField;
use CtrlField\Fields\Types\DateTimeField;
use CtrlField\Fields\Types\EmailField;
use CtrlField\Fields\Types\FileField;
use CtrlField\Fields\Types\GroupField;
use CtrlField\Fields\Types\IconField;
use CtrlField\Fields\Types\ImageField;
use CtrlField\Fields\Types\LinkField;
use CtrlField\Fields\Types\MessageField;
use CtrlField\Fields\Types\NumberField;
use CtrlField\Fields\Types\ComputedField;
use CtrlField\Fields\Types\OembedField;
use CtrlField\Fields\Types\RadioField;
use CtrlField\Fields\Types\RangeField;
use CtrlField\Fields\Types\RepeaterField;
use CtrlField\Fields\Types\SelectField;
use CtrlField\Fields\Types\SeparatorField;
use CtrlField\Fields\Types\TabField;
use CtrlField\Fields\Types\TextareaField;
use CtrlField\Fields\Types\TextField;
use CtrlField\Fields\Types\TimeField;
use CtrlField\Fields\Types\TrueFalseField;
use CtrlField\Fields\Types\UrlField;
use CtrlField\Fields\Types\UserField;
use CtrlField\Fields\Types\WysiwygField;

/**
 * Static factory for field types and field group registration.
 *
 * Field::group(key)  → FieldGroup builder (registration container, use where/fields/register).
 * Field::object(key) → GroupField type  (nested single-object field inside a fields() array).
 */
final class Field
{
    // -------------------------------------------------------------------------
    // Clone factory — registered by Pro at boot time (valid license)
    // -------------------------------------------------------------------------

    private static ?\Closure $cloneFieldFactory = null;

    /**
     * Register the factory for Field::clone() — called by CtrlField Pro bootstrap.
     */
    public static function registerCloneFactory(\Closure $factory): void
    {
        self::$cloneFieldFactory = $factory;
    }

    /**
     * Create a CloneField instance. Requires a valid CtrlField Pro license.
     *
     * @throws \RuntimeException if the clone field factory has not been registered
     */
    public static function clone(string $key): FieldDefinition
    {
        if (self::$cloneFieldFactory === null) {
            throw new \RuntimeException(
                'Field::clone() requires a CtrlField Pro license. The clone factory has not been registered.'
            );
        }

        return (self::$cloneFieldFactory)($key);
    }

    /** Reset clone factory — for testing only. */
    public static function resetCloneFactory(): void
    {
        self::$cloneFieldFactory = null;
    }


    public static function text(string $key): TextField
    {
        return new TextField($key);
    }

    public static function textarea(string $key): TextareaField
    {
        return new TextareaField($key);
    }

    public static function number(string $key): NumberField
    {
        return new NumberField($key);
    }

    public static function email(string $key): EmailField
    {
        return new EmailField($key);
    }

    public static function url(string $key): UrlField
    {
        return new UrlField($key);
    }

    public static function select(string $key): SelectField
    {
        return new SelectField($key);
    }

    public static function checkbox(string $key): CheckboxField
    {
        return new CheckboxField($key);
    }

    public static function radio(string $key): RadioField
    {
        return new RadioField($key);
    }

    public static function image(string $key): ImageField
    {
        return new ImageField($key);
    }

    public static function file(string $key): FileField
    {
        return new FileField($key);
    }

    public static function wysiwyg(string $key): WysiwygField
    {
        return new WysiwygField($key);
    }

    public static function group(string $key): FieldGroup
    {
        return FieldGroup::make($key);
    }

    public static function object(string $key): GroupField
    {
        return new GroupField($key);
    }

    public static function repeater(string $key): RepeaterField
    {
        return new RepeaterField($key);
    }

    public static function date(string $key): DateField
    {
        return new DateField($key);
    }

    public static function time(string $key): TimeField
    {
        return new TimeField($key);
    }

    public static function datetime(string $key): DateTimeField
    {
        return new DateTimeField($key);
    }

    public static function color(string $key): ColorField
    {
        return new ColorField($key);
    }

    public static function link(string $key): LinkField
    {
        return new LinkField($key);
    }

    public static function range(string $key): RangeField
    {
        return new RangeField($key);
    }

    public static function oembed(string $key): OembedField
    {
        return new OembedField($key);
    }

    public static function computed(string $key, \Closure $callback): ComputedField
    {
        return new ComputedField($key, $callback);
    }

    /**
     * Boolean toggle — stores 1 (on) or 0 (off).
     * Renders as a CSS toggle switch in the admin UI.
     */
    public static function trueFalse(string $key): TrueFalseField
    {
        return new TrueFalseField($key);
    }

    // -------------------------------------------------------------------------
    // C-1: Tab + Accordion
    // -------------------------------------------------------------------------

    /**
     * Creates a tab divider. Tabs within the same field group are grouped
     * automatically by MetaBoxRenderer. The key becomes the tab's identifier.
     */
    public static function tab(string $key): TabField
    {
        return new TabField($key);
    }

    /**
     * Opens an accordion section. Must be paired with accordionEnd().
     */
    public static function accordion(string $key): AccordionField
    {
        return new AccordionField($key);
    }

    /**
     * Closes the nearest open accordion section.
     */
    public static function accordionEnd(string $key): AccordionEndField
    {
        return new AccordionEndField($key);
    }

    // -------------------------------------------------------------------------
    // C-2: Message + Separator
    // -------------------------------------------------------------------------

    /**
     * A read-only informational message rendered in the meta box.
     * Use content() to set the HTML and type() to control the style (info/warning/error/success).
     */
    public static function message(string $key): MessageField
    {
        return new MessageField($key);
    }

    /**
     * A horizontal rule between fields.
     */
    public static function separator(string $key): SeparatorField
    {
        return new SeparatorField($key);
    }

    // -------------------------------------------------------------------------
    // C-3: Button Group
    // -------------------------------------------------------------------------

    /**
     * A set of mutually-exclusive toggle buttons (radio group).
     */
    public static function buttonGroup(string $key): ButtonGroupField
    {
        return new ButtonGroupField($key);
    }

    // -------------------------------------------------------------------------
    // C-4: User
    // -------------------------------------------------------------------------

    /**
     * Select one or more WordPress users via live search.
     */
    public static function user(string $key): UserField
    {
        return new UserField($key);
    }

    // -------------------------------------------------------------------------
    // C-5: Icon Picker
    // -------------------------------------------------------------------------

    /**
     * A Dashicons icon picker with modal search.
     */
    public static function icon(string $key): IconField
    {
        return new IconField($key);
    }

    // -------------------------------------------------------------------------
    // C-6: Code Editor
    // -------------------------------------------------------------------------

    /**
     * A CodeMirror-backed code editor field.
     */
    public static function code(string $key): CodeField
    {
        return new CodeField($key);
    }
}
