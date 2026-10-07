# CtrlField

**Enterprise-grade code-first custom fields engine for WordPress.**

CtrlField replaces ACF and Carbon Fields with a deterministic, PHP-first schema layer. The database stores values. PHP defines the structure. Schema files live in your theme or project — never locked inside a plugin admin UI.

---

## Why CtrlField

| Problem with ACF / Carbon Fields | CtrlField |
|-----------------------------------|-----------|
| Schema in the database — invisible in git diffs | Schema in PHP — committed alongside the code |
| UI → export JSON → import → repeat on every environment | Define once in PHP, works everywhere |
| Row-per-field storage doubles meta rows | Single JSON blob per post |
| `_reference_key` rows for every field | Eliminated entirely |
| Silent runtime schema migrations | Explicit CLI only: `wp ctrlfield migrate` |
| Global REST API pollution | Opt-in per field: `->showInRest()` |

---

## Requirements

| Dependency | Minimum |
|-----------|---------|
| PHP | 8.2 |
| WordPress | 6.5 |
| Composer | 2.x |

---

## Installation

```bash
composer require ctrlcheese/ctrlfield
```

Or place the plugin directory in `wp-content/plugins/ctrlfield/` and run:

```bash
composer install
```

Activate via WP admin or WP-CLI:

```bash
wp plugin activate ctrlfield
```

---

## Quickstart

Field definitions live in your theme or a project-specific directory — not in the plugin.

**1. Point CtrlField to your schema directory**

In `wp-config.php`:

```php
define('CTRLFIELD_SCHEMA_PATH', get_template_directory() . '/ctrlfield/');
```

Or via filter in `functions.php` (recommended — no `get_template_directory()` in wp-config):

```php
add_filter('ctrlfield/schema_paths', function (array $paths): array {
    $paths[] = get_template_directory() . '/ctrlfield/';
    return $paths;
});
```

**2. Create a schema file in your theme**

`wp-content/themes/my-theme/ctrlfield/portfolio.php`:

```php
<?php

use CtrlField\Builder\CPT;
use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Field;

add_action('init', static function (): void {

    CPT::make('portfolio')
        ->label('Portfolio', 'Portfolios')
        ->menuIcon('dashicons-portfolio')
        ->supports(['title', 'thumbnail'])
        ->register();

    FieldGroup::make('portfolio_details')
        ->title('Project Details')
        ->where('post_type', '==', 'portfolio')
        ->fields([

            Field::text('client_name')
                ->label('Client Name')
                ->required()
                ->setIndex(true),

            Field::select('project_type')
                ->label('Project Type')
                ->options([
                    'web'   => 'Web Design',
                    'brand' => 'Branding',
                    'print' => 'Print',
                ]),

            Field::text('client_contact')
                ->label('Client Contact')
                ->visibleWhen('project_type', '==', 'web'),

            Field::image('featured_image')
                ->label('Featured Image'),

            Field::repeater('team_members')          // Pro
                ->label('Team Members')
                ->fields([
                    Field::text('name')->label('Name'),
                    Field::text('role')->label('Role'),
                ]),

        ])
        ->register();

}, 1);
```

**3. Read values in your templates**

```php
// Single field
$clientName = ctrlfield_get('client_name', $postId);

// All fields for a post
$fields = ctrlfield_get_all($postId);

// Options page value
$tagline = ctrlf_option('site_tagline', 'theme_settings');
```

**4. Blade directives** (requires Illuminate/View)

```blade
@field('client_name')

@repeater('team_members')
    <li>{{ $name }} — {{ $role }}</li>
@endrepeater
```

---

## Field Types

Same Free/Pro split as ACF, plus extra Free fields ACF does not have. Full reference: `docs/fields/`.

| Category | Field | Factory | Plan |
|---|---|---|---|
| Basic | Text, Textarea, Number, Range, Email, URL, Password | `Field::text()`, `::textarea()`, `::number()`, `::range()`, `::email()`, `::url()`, `::password()` | Free |
| Content | Image, File, WYSIWYG, oEmbed | `Field::image()`, `::file()`, `::wysiwyg()`, `::oembed()` | Free |
| Content | Gallery | `Field::gallery()` | Pro |
| Choice | Select, Checkbox, Radio, Button Group, True / False | `Field::select()`, `::checkbox()`, `::radio()`, `::buttonGroup()`, `::trueFalse()` | Free |
| Relational | Page Link, Post Object, Relationship, Taxonomy, User, Link | `Field::pageLink()`, `::postObject()`, `::relationship()`, `::taxonomyTerm()`, `::user()`, `::link()` | Free |
| Pickers | Date, Time, Date Time, Color, Map | `Field::date()`, `::time()`, `::datetime()`, `::color()`, `::map()` | Free |
| Layout | Group, Tab, Accordion, Message, Separator | `Field::object()`, `::tab()`, `::accordion()` + `::accordionEnd()`, `::message()`, `::separator()` | Free |
| Layout | Repeater, Flexible Content, Clone | `Field::repeater()`, `::flexibleContent()`, `::clone()` | Pro |
| CtrlField only | Code, Icon, Computed | `Field::code()`, `::icon()`, `::computed()` | Free |

`Field::group()` builds a whole **field group** (with `where()` and `register()`); a group *field* nested inside `fields([...])` is `Field::object()`.

Pro factories need an active license. Without it they throw a clear error, and a schema file that uses them is skipped with an admin notice — the rest of the site keeps working.

---

## Location rules

Show a group only where it belongs — same rule set as ACF and Carbon Fields:

```php
->where('post_type', '==', 'page')
->where('page_template', '==', 'templates/landing.php')   // also: page_type, post_parent,
                                                          // post_status, post_format, post_term,
                                                          // post, current_user_role,
                                                          // current_user_can, user_role
```

Full list and OR groups (`whereAny()`): `docs/getting-started/location-rules.mdx`.

---

## Field groups without code

**CtrlField → Field Groups** creates groups with a form, like ACF. Each group is saved as a JSON file in `<theme>/ctrlfield-json/` (or the database on read-only servers), so it is versioned and deployed with the theme. Groups defined in PHP take precedence and are listed read-only; any JSON group can be exported to PHP.

Details: `docs/getting-started/field-groups-admin.mdx`.

---

## Coming from ACF

**CtrlField → Field Groups → Import from ACF** (or `wp ctrlfield acf-import --data`) converts ACF field groups and copies their values; ACF's data is only read. The importer is **Pro**. Free: with ACF deactivated, CtrlField provides `get_field()`, `the_field()`, `have_rows()` / `get_sub_field()`, `update_field()`, `acf_add_local_field_group()` and `acf_add_options_page()`, so ACF themes keep working.

Details: `docs/getting-started/migrating-from-acf.mdx`.

---

## Fluent API

Every field inherits these chainable methods:

```php
Field::text('key')
    ->label('Label')
    ->instructions('Help text shown in admin')
    ->placeholder('Placeholder text')
    ->default('Default value')
    ->required()
    ->setIndex()            // enables WP_Query meta_query on this field
    ->showInRest()          // exposes via REST API
    ->adminColumn()         // shows in WP post list table (requires setIndex)
    ->width(50)             // field width in the meta box: 25 / 50 / 75 / 100
    ->readOnly()
    ->visibleWhen('other_field', '==', 'value')
    ->visibleWhenAll([['field', '==', 'val'], ['other', '!=', 'x']])
    ->visibleWhenAny([['type', '==', 'a'], ['type', '==', 'b']])
    ->quickEdit()           // editable from WP post list table
    ->bulkEdit()            // editable from bulk edit dropdown (select/radio/checkbox only)
    ->notifyOnChange(toValue: 'approved', to: 'hr@company.com', subject: '...', message: '...')
    ->returnFormat('url')   // image/file: 'id' | 'url' | 'array'
    ->translate(false)      // shared across WPML/Polylang translations
```

---

## Registering Content Types

### Custom Post Type

```php
CPT::make('portfolio')
    ->label('Portfolio', 'Portfolios')
    ->menuIcon('dashicons-portfolio')
    ->supports(['title', 'editor', 'thumbnail'])
    ->hasArchive(true)
    ->rewriteSlug('work')
    ->showInRest(true)
    ->register();
```

### Taxonomy

```php
use CtrlField\Builder\Taxonomy;

Taxonomy::make('project_category')
    ->label('Category', 'Categories')
    ->attachTo(['portfolio'])
    ->hierarchical(true)
    ->register();
```

### Options Page

```php
use CtrlField\Builder\OptionsPage;

OptionsPage::make('theme_settings')
    ->title('Theme Settings')
    ->menuSlug('theme-settings')
    ->parent('ctrlfield')          // sub-menu under CtrlField
    ->fields([
        Field::text('site_tagline')->label('Site Tagline'),
        Field::image('site_logo')->label('Logo'),
    ])
    ->register();
```

---

## Conditional Logic

```php
// Single condition (backwards compatible)
->visibleWhen('type', '==', 'external')

// AND — all must match
->visibleWhenAll([
    ['type', '==', 'external'],
    ['tier', '!=', 'free'],
])

// OR — any must match
->visibleWhenAny([
    ['type', '==', 'external'],
    ['type', '==', 'agency'],
])
```

**Operators:** `==` `!=` `<` `<=` `>` `>=` `contains` `not_contains` `empty` `not_empty` `in` `not_in`

---

## Data Storage

All field values are stored as a single JSON blob in `wp_postmeta` under `_ctrlfield_data`. Fields marked with `->setIndex()` additionally write a separate `_ctrlfield_idx_{key}` row for native `WP_Query` `meta_query` support.

```php
// WP_Query with indexed field
$query = new WP_Query([
    'post_type'  => 'portfolio',
    'meta_query' => [[
        'key'   => '_ctrlfield_idx_client_name',
        'value' => 'Acme',
    ]],
]);
```

---

## Helper Functions

```php
// Post fields
ctrlfield_get('key', $postId)
ctrlfield_get_all($postId)

// Options pages
ctrlfield_get_options('key', 'page_slug')
ctrlfield_get_all_options('page_slug')
ctrlf_option('key', 'page_slug', $default)   // shorthand with default

// User meta
ctrlfield_get_user('key', $userId)
ctrlfield_get_all_user($userId)

// Term meta
ctrlfield_get_term('key', $termId)
ctrlfield_get_all_term($termId)

// Theme integration
ctrlfield_load_schemas('/path/to/schema/dir/')
ctrlfield_load_components('/path/to/components/')  // auto-discovers fields.php per component
```

---

## WP-CLI

```bash
wp ctrlfield migrate              # Run schema migrations
wp ctrlfield migrate --dry-run    # Preview without writing
wp ctrlfield export               # Export PHP schema as JSON artifact
wp ctrlfield import               # Import and validate a JSON artifact
wp ctrlfield validate             # Validate schema against stored versions

wp ctrlfield export --post-type=portfolio > portfolio.csv   # CSV export
wp ctrlfield import portfolio.csv --post-type=portfolio     # CSV import

wp ctrlfield scaffold post-type portfolio \
    --fields="title:text,client:text,image:image"            # Scaffold CPT + field group
```

---

## Free and Pro

CtrlField is **one plugin**. Everything below ships in it; a Pro license (**CtrlField → Pro License**) unlocks it. Pro code lives in `pro/`.

| Pro feature | Description |
|---------|-------------|
| **Visual Field Builder** | Drag-and-drop UI that generates PHP code — never stores schema in the DB |
| **Repeater** | Rows of sub-fields (Pro in ACF too) |
| **Flexible Content** | Page builder with named layouts, each with its own sub-fields |
| **Gallery** | Multi-image field with drag-to-reorder |
| **Clone Field** | Reuse field groups across multiple contexts without duplication |
| **Custom Table Storage (CCT)** | One DB row per record — for 50k+ record use cases |
| **Gutenberg Blocks** | Register any field group as a native block |
| **Elementor / Bricks / WooCommerce** | Dynamic tags, dynamic data and shop fields |
| **Audit log, webhooks, field permissions** | Change history, webhook on change, per-role access |

Without a license the site runs the Free feature set and shows no license notices.

---

## Admin UI

CtrlField adds a **CtrlField** top-level menu in WP Admin with:

- **Schema Inspector** — read-only view of all registered field groups, coverage stats, version mismatch warnings, and CSV export/import buttons.
- **Visual Builder** — available with CtrlField Pro.

---

## Integrations

| Integration | Status | Notes |
|------------|--------|-------|
| Blade (Illuminate/View) | Core | `@field`, `@repeater` directives |
| Gutenberg sidebar | Core | React adapter for block editor |
| Timber / Twig | Core | `ctrlf()`, `ctrlf_all()`, `ctrlf_repeater()` functions |
| REST API | Core | Opt-in per field via `->showInRest()` |
| WP-CLI | Core | `wp ctrlfield` commands |
| WPML / Polylang | Core | Per-field translation control |
| Elementor Dynamic Tags | Pro | 6 tag categories |
| Bricks Builder | Pro | Dynamic data provider |
| WooCommerce | Pro | Product, variation, order, customer meta |

---

## Schema in Theme (Recommended Pattern)

The plugin is the engine. The schema belongs to the project.

```
wp-content/
├── plugins/
│   └── ctrlfield/           ← engine only, never edit for client work
└── themes/
    └── my-theme/
        ├── functions.php     ← one filter to wire up CtrlField
        └── ctrlfield/
            ├── portfolio.php ← one file per content model
            ├── testimonials.php
            └── theme-options.php
```

`functions.php`:

```php
add_filter('ctrlfield/schema_paths', function (array $paths): array {
    $paths[] = get_template_directory() . '/ctrlfield/';
    return $paths;
});
```

When the theme moves to a new server, the schema comes with it. No import steps. No UI clicks.

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)

Pro features ship inside the same plugin and are unlocked with a CtrlField Pro license from [ctrlcheese.de](https://ctrlcheese.de).
