# Using CtrlField in code — step by step

This guide builds a complete content type in PHP: a **post type**, a **taxonomy** and a **field group** with Free and Pro fields. Then it reads the values in a template. Everything lives in files, so it goes into git and deploys with your theme.

> Prefer clicking to typing? See [Using CtrlField in the admin](./using-ctrlfield-in-the-admin.md). Both routes produce the same result, and anything made in the admin can be exported to the PHP shown here.

---

## 1. Tell CtrlField where your schema files live

A schema file is a plain PHP file that registers post types, taxonomies or field groups. CtrlField loads every `.php` file in the schema folder on `init`.

**Option A**: add a constant to `wp-config.php`:

```php
define('CTRLFIELD_SCHEMA_PATH', __DIR__ . '/wp-content/ctrlfield-schemas/');
```

**Option B**: load a folder from your theme's `functions.php`:

```php
add_action('init', function () {
    ctrlfield_load_schemas(get_template_directory() . '/ctrlfield/');
}, 5);
```

Create the folder. Files load in alphabetical order, so number them: `01-content-types.php`, `02-fields.php`…

---

## 2. Register the post type

`ctrlfield-schemas/01-content-types.php`:

```php
<?php

declare(strict_types=1);

use CtrlField\Builder\CPT;
use CtrlField\Builder\Taxonomy;

CPT::make('example')
    ->label('Example', 'Examples')            // singular, plural
    ->menuIcon('dashicons-forms')
    ->supports(['title', 'thumbnail'])         // no 'editor' → no block editor, only your fields
    ->hasArchive(true)                         // /example/ lists every entry
    ->showInRest(true)
    ->taxonomies(['category'])                 // use an existing taxonomy too
    ->register();

Taxonomy::make('service')
    ->label('Service', 'Services')
    ->attachTo(['example'])
    ->hierarchical(true)                       // true: like categories · false: like tags
    ->showAdminColumn(true)
    ->register();
```

**What `supports` changes:**

| `supports` | Edit screen |
|---|---|
| `['title']` | Title and your fields only, like a form |
| `['title', 'editor']` | The block editor (Gutenberg), with your fields below it |

Keys must be lowercase. A post type key can have up to 20 characters and a taxonomy key up to 32. WordPress reserves some names (`post`, `page`, `year`, `name`, `type`…), so avoid them.

> When two or more post types are registered this way, they share one **CPTs** menu item, sorted alphabetically. To turn this off, add `add_filter('ctrlfield/cpt_menu_folder', '__return_false');`.

---

## 3. Add a field group with Free fields

A field group is a set of fields shown on an edit screen. `->where()` decides **where**; here, on the `example` post type.

`ctrlfield-schemas/02-example-fields.php`:

```php
<?php

declare(strict_types=1);

use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Field;

FieldGroup::make('example_fields')
    ->title('Example details')
    ->where('post_type', '==', 'example')
    ->fields([
        // Tabs split a long group into pages.
        Field::tab('tab_text')->label('Text'),
        Field::text('headline')->label('Headline')->required()->width(50)->maxLength(80),
        Field::number('price')->label('Price')->width(25)->prepend('€')->min(0),
        Field::number('days')->label('Duration')->width(25)->append('days'),
        Field::textarea('summary')->label('Summary')->rows(3),
        Field::wysiwyg('body')->label('Content'),

        Field::tab('tab_choices')->label('Choices'),
        Field::select('country')->label('Country')->searchable()->options([
            'pt' => 'Portugal', 'de' => 'Germany', 'br' => 'Brazil',
        ]),
        Field::buttonGroup('status')->label('Status')->options([
            'draft' => 'Draft', 'ready' => 'Ready',
        ])->default('draft'),
        Field::checkbox('services')->label('Services')->layout('horizontal')->options([
            'design' => 'Design', 'dev' => 'Development', 'seo' => 'SEO',
        ]),
        Field::trueFalse('featured')->label('Featured')->message('Show on the home page'),
        // Shown only while "Featured" is on.
        Field::text('featured_note')->label('Featured note')->visibleWhen('featured', '==', '1'),

        Field::tab('tab_media')->label('Media & links'),
        Field::image('cover')->label('Cover')->returnFormat('array'),
        Field::file('brief')->label('Brief (PDF)'),
        Field::oembed('video')->label('Video'),
        Field::link('buttons')->label('Buttons')->multiple()->maxItems(2),
        Field::relationship('related')->label('Related projects')->postType('project')->maxItems(4),
        Field::taxonomyTerm('category')->label('Category')->taxonomy('category'),

        Field::tab('tab_more')->label('More'),
        Field::date('date')->label('Date')->width(33),
        Field::time('time')->label('Time')->width(33),
        Field::color('color')->label('Colour')->width(33),
        Field::icon('icon')->label('Icon'),
        Field::map('location')->label('Location'),
        Field::object('contact')->label('Contact')->layout('row')->fields([
            Field::text('name')->label('Name')->width(50),
            Field::text('phone')->label('Phone')->width(50),
        ]),
    ])
    ->register();
```

Reload any **Examples** entry: the fields appear, split into four tabs.

**Settings every field accepts:** `->label()`, `->instructions()` (help text under the label), `->required()`, `->width(25|33|50|75|100)`, `->default()`, `->visibleWhen($field, $operator, $value)`, `->adminColumn()` (a column in the list of entries) and `->setIndex(true)` (makes the field queryable, see step 6).

---

## 4. Add Pro fields

The repeater, flexible content, gallery and clone fields need a valid Pro license.

```php
use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Field;
use CtrlField\Pro\Fields\FlexLayout;

// A "library" group: no ->where(), so it shows nowhere on its own.
// Other groups reuse it with Field::clone().
FieldGroup::make('lib_seo')
    ->title('SEO')
    ->fields([
        Field::text('seo_title')->label('SEO title'),
        Field::textarea('seo_description')->label('Meta description'),
    ])
    ->register();

FieldGroup::make('example_pro_fields')
    ->title('Example — Pro')
    ->where('post_type', '==', 'example')
    ->fields([
        Field::gallery('gallery')->label('Gallery')->maxItems(12),

        Field::repeater('team')->label('Team')->layout('table')->buttonLabel('Add person')->fields([
            Field::image('photo')->label('Photo'),
            Field::text('name')->label('Name'),
            Field::text('role')->label('Role'),
        ]),

        Field::flexibleContent('sections')->label('Sections')->buttonLabel('Add section')->layouts([
            FlexLayout::make('text')->label('Text')->fields([
                Field::text('title')->label('Title'),
                Field::wysiwyg('text')->label('Text'),
            ]),
            FlexLayout::make('quote')->label('Quote')->fields([
                Field::textarea('quote')->label('Quote'),
                Field::text('author')->label('Author'),
            ]),
        ]),

        // Only library groups can be cloned. Cloning one that has ->where()
        // stops the whole group from loading.
        Field::clone('seo')->label('SEO')->from('lib_seo')->prefix('example')->display('group'),
    ])
    ->register();
```

---

## 5. Show the values in a template

`single-example.php`:

```php
<?php
$headline = ctrlfield_get('headline');            // string
$cover    = ctrlfield_get('cover');               // ['id', 'url', 'width', 'height', 'alt'] (returnFormat 'array')
$buttons  = ctrlfield_get('buttons');             // list of links
$team     = ctrlfield_get('team') ?: [];          // rows of the repeater
$sections = ctrlfield_get('sections') ?: [];      // rows, each with '_layout'
?>

<h1><?= esc_html($headline) ?></h1>

<?php if ($cover): ?>
    <img src="<?= esc_url($cover['url']) ?>" alt="<?= esc_attr($cover['alt']) ?>">
<?php endif; ?>

<?php foreach ($buttons ?: [] as $link): ?>
    <?= ctrlfield_button($link) ?>
<?php endforeach; ?>

<?php foreach ($team as $person): ?>
    <p><?= esc_html($person['name']) ?> — <?= esc_html($person['role']) ?></p>
<?php endforeach; ?>

<?php foreach ($sections as $section): ?>
    <?php if ($section['_layout'] === 'text'): ?>
        <h2><?= esc_html($section['title']) ?></h2>
        <?= wp_kses_post($section['text']) ?>
    <?php elseif ($section['_layout'] === 'quote'): ?>
        <blockquote><?= esc_html($section['quote']) ?> — <?= esc_html($section['author']) ?></blockquote>
    <?php endif; ?>
<?php endforeach; ?>
```

**Useful helpers:**

| Helper | Returns |
|---|---|
| `ctrlfield_get('key', $postId)` | One value, in the field's return format |
| `ctrlfield_get_all($postId)` | Every stored value, raw |
| `ctrlfield_get_gallery('gallery', 'large')` | The gallery's images at a size |
| `ctrlfield_get_options('key', 'page_slug')` | A value from an options page |
| `ctrlfield_get_term('key', $termId)` | A value from a term |
| `ctrlfield_get_user('key', $userId)` | A value from a user |
| `ctrlfield_button($link)` | A link field as an `<a>` button |
| `ctrlfield_icon($value)` | An icon field as SVG or Dashicon HTML |

Blade users get `@field('headline')`, `@repeater('team') … @endrepeater`, `@flexible('sections') … @endflexible` and `@gallery('gallery') … @endgallery`.

> **Autocomplete:** CtrlField → Schema Inspector → a group → **Types (PHP / TypeScript)** gives you a `@var array{…}` docblock and a TypeScript interface for your editor.

---

## 6. Query entries by a field value

Add `->setIndex(true)` to a field, then query it like any meta key, prefixed with `_ctrlfield_idx_`:

```php
Field::text('city')->label('City')->setIndex(true),

$query = new WP_Query([
    'post_type'  => 'example',
    'meta_key'   => '_ctrlfield_idx_city',
    'meta_value' => 'Lisbon',
]);
```

---

## 7. Check your work

**CtrlField → Schema Inspector** shows every group and where it appears, how often each field is filled in, a post's stored values next to what `ctrlfield_get()` returns, and any problems. Problems include a file that does not load, the same key used twice, a condition pointing at a missing field, and values left behind by a renamed field.
