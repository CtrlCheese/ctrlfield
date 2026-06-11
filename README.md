# FieldForge

**Enterprise-grade code-first custom fields engine for WordPress.**

FieldForge replaces ACF and Carbon Fields with a deterministic, PHP-first schema layer. The database stores values. PHP defines the structure. Schema files live in your theme or project — never locked inside a plugin admin UI.

---

## Why FieldForge

| Problem with ACF / Carbon Fields | FieldForge |
|-----------------------------------|-----------|
| Schema in the database — invisible in git diffs | Schema in PHP — committed alongside the code |
| UI → export JSON → import → repeat on every environment | Define once in PHP, works everywhere |
| Row-per-field storage doubles meta rows | Single JSON blob per post |
| `_reference_key` rows for every field | Eliminated entirely |
| Silent runtime schema migrations | Explicit CLI only: `wp fieldforge migrate` |
| Global REST API pollution | Opt-in per field: `->showInRest()` |

---

## Requirements

| Dependency | Minimum |
|-----------|---------|
| PHP | 8.2 |
| WordPress | 6.4 |
| Composer | 2.x |

---

## Installation

```bash
composer require fieldforge/fieldforge
```

Or place the plugin directory in `wp-content/plugins/fieldforge/` and run:

```bash
composer install
```

Activate via WP admin or WP-CLI:

```bash
wp plugin activate fieldforge
```

---

## Quickstart

Field definitions live in your theme or a project-specific directory — not in the plugin.

**1. Point FieldForge to your schema directory**

In `wp-config.php`:

```php
define('FIELDFORGE_SCHEMA_PATH', get_template_directory() . '/fieldforge/');
```

Or via filter in `functions.php` (recommended — no `get_template_directory()` in wp-config):

```php
add_filter('fieldforge/schema_paths', function (array $paths): array {
    $paths[] = get_template_directory() . '/fieldforge/';
    return $paths;
});
```

**2. Create a schema file in your theme**

`wp-content/themes/my-theme/fieldforge/portfolio.php`:

```php
<?php

use FieldForge\Builder\CPT;
use FieldForge\Builder\FieldGroup;
use FieldForge\Fields\Field;

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

            Field::repeater('team_members')
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
$clientName = fieldforge_get('client_name', $postId);

// All fields for a post
$fields = fieldforge_get_all($postId);

// Options page value
$tagline = ff_option('site_tagline', 'theme_settings');
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

### Basic
| Type | Factory | Notes |
|------|---------|-------|
| Text | `Field::text('key')` | |
| Textarea | `Field::textarea('key')` | |
| Number | `Field::number('key')` | |
| Email | `Field::email('key')` | |
| URL | `Field::url('key')` | |
| WYSIWYG | `Field::wysiwyg('key')` | TinyMCE |

### Choice
| Type | Factory | Notes |
|------|---------|-------|
| Select | `Field::select('key')->options([...])` | |
| Checkbox | `Field::checkbox('key')->options([...])` | |
| Radio | `Field::radio('key')->options([...])` | |

### Media
| Type | Factory | Notes |
|------|---------|-------|
| Image | `Field::image('key')` | Stores attachment ID |
| File | `Field::file('key')` | Stores attachment ID |

### Date & Time
| Type | Factory | Notes |
|------|---------|-------|
| Date | `Field::date('key')` | ISO 8601 |
| Time | `Field::time('key')` | HH:MM |
| DateTime | `Field::datetime('key')` | ISO 8601 |

### Extra
| Type | Factory | Notes |
|------|---------|-------|
| Color | `Field::color('key')` | Hex string |
| Link | `Field::link('key')` | `{url, title, target}` |
| Range | `Field::range('key')` | Slider |
| oEmbed | `Field::oembed('key')` | Via `wp_oembed_get()` |

### Structured
| Type | Factory | Notes |
|------|---------|-------|
| Group | `Field::group('key')->fields([...])` | Single nested object |
| Repeater | `Field::repeater('key')->fields([...])` | Array of objects, max depth 3 |
| Computed | `Field::computed('key', fn($fields) => ...)` | Calculated on save |

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
use FieldForge\Builder\Taxonomy;

Taxonomy::make('project_category')
    ->label('Category', 'Categories')
    ->attachTo(['portfolio'])
    ->hierarchical(true)
    ->register();
```

### Options Page

```php
use FieldForge\Builder\OptionsPage;

OptionsPage::make('theme_settings')
    ->title('Theme Settings')
    ->menuSlug('theme-settings')
    ->parent('fieldforge')          // sub-menu under FieldForge
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

All field values are stored as a single JSON blob in `wp_postmeta` under `_fieldforge_data`. Fields marked with `->setIndex()` additionally write a separate `_fieldforge_idx_{key}` row for native `WP_Query` `meta_query` support.

```php
// WP_Query with indexed field
$query = new WP_Query([
    'post_type'  => 'portfolio',
    'meta_query' => [[
        'key'   => '_fieldforge_idx_client_name',
        'value' => 'Acme',
    ]],
]);
```

---

## Helper Functions

```php
// Post fields
fieldforge_get('key', $postId)
fieldforge_get_all($postId)

// Options pages
fieldforge_get_options('key', 'page_slug')
fieldforge_get_all_options('page_slug')
ff_option('key', 'page_slug', $default)   // shorthand with default

// User meta
fieldforge_get_user('key', $userId)
fieldforge_get_all_user($userId)

// Term meta
fieldforge_get_term('key', $termId)
fieldforge_get_all_term($termId)

// Theme integration
fieldforge_load_schemas('/path/to/schema/dir/')
fieldforge_load_components('/path/to/components/')  // auto-discovers fields.php per component
```

---

## WP-CLI

```bash
wp fieldforge migrate              # Run schema migrations
wp fieldforge migrate --dry-run    # Preview without writing
wp fieldforge export               # Export PHP schema as JSON artifact
wp fieldforge import               # Import and validate a JSON artifact
wp fieldforge validate             # Validate schema against stored versions

wp fieldforge export --post-type=portfolio > portfolio.csv   # CSV export
wp fieldforge import portfolio.csv --post-type=portfolio     # CSV import

wp fieldforge scaffold post-type portfolio \
    --fields="title:text,client:text,image:image"            # Scaffold CPT + field group
```

---

## Admin UI

FieldForge adds a **FieldForge** top-level menu in WP Admin with:

- **Schema Inspector** — read-only view of all registered field groups, coverage stats, version mismatch warnings, and CSV export/import buttons.
- **Visual Builder** — available with FieldForge Pro.

---

## Integrations

| Integration | Status | Notes |
|------------|--------|-------|
| Blade (Illuminate/View) | Core | `@field`, `@repeater` directives |
| Gutenberg sidebar | Core | React adapter for block editor |
| Timber / Twig | Core | `ff()`, `ff_all()`, `ff_repeater()` functions |
| REST API | Core | Opt-in per field via `->showInRest()` |
| WP-CLI | Core | `wp fieldforge` commands |
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
│   └── fieldforge/           ← engine only, never edit for client work
└── themes/
    └── my-theme/
        ├── functions.php     ← one filter to wire up FieldForge
        └── fieldforge/
            ├── portfolio.php ← one file per content model
            ├── testimonials.php
            └── theme-options.php
```

`functions.php`:

```php
add_filter('fieldforge/schema_paths', function (array $paths): array {
    $paths[] = get_template_directory() . '/fieldforge/';
    return $paths;
});
```

When the theme moves to a new server, the schema comes with it. No import steps. No UI clicks.

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)

FieldForge Pro (commercial extension) is licensed separately. See [fieldforge.io](https://fieldforge.io).
