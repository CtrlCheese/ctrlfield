<?php

declare(strict_types=1);

namespace FieldForge\Integrations\WordPress;

use FieldForge\Bootstrap\ServiceProvider;
use FieldForge\Builder\CPT;
use FieldForge\Builder\OptionsPage;
use FieldForge\Builder\Taxonomy;
use FieldForge\Core\Migration\SchemaVersion;
use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\SelectField;
use FieldForge\Storage\Drivers\WpOptionsDriver;
use FieldForge\Storage\OptionsAdapter;

/**
 * Excluded from PHPStan — references WP functions not available outside WP runtime.
 */
class WordPressServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        // Priority 20 ensures user code that registers builders on init (priority 10)
        // has already run before we call register_post_type / register_taxonomy.
        add_action('init', [$this, 'registerCpts'], 20);
        add_action('init', [$this, 'registerTaxonomies'], 20);
        add_action('init', [$this, 'bindTaxonomyTermHooks'], 20);
        add_action('init', [$this, 'bindOptionsPageSaveHooks'], 20);
        add_action('admin_menu', [$this, 'registerOptionsPages']);
    }

    // -------------------------------------------------------------------------
    // CPT
    // -------------------------------------------------------------------------

    public function registerCpts(): void
    {
        foreach (CPT::all() as $cpt) {
            $singular = $cpt->getSingularLabel() ?: ucfirst($cpt->getPostType());
            $plural   = $cpt->getPluralLabel()   ?: $singular . 's';

            register_post_type($cpt->getPostType(), [
                'labels' => [
                    'name'                  => $plural,
                    'singular_name'         => $singular,
                    'add_new'               => "Add New",
                    'add_new_item'          => "Add New {$singular}",
                    'edit_item'             => "Edit {$singular}",
                    'new_item'              => "New {$singular}",
                    'view_item'             => "View {$singular}",
                    'view_items'            => "View {$plural}",
                    'search_items'          => "Search {$plural}",
                    'not_found'             => "No {$plural} found.",
                    'not_found_in_trash'    => "No {$plural} found in Trash.",
                    'all_items'             => "All {$plural}",
                    'archives'              => "{$singular} Archives",
                    'attributes'            => "{$singular} Attributes",
                    'insert_into_item'      => "Insert into {$singular}",
                    'uploaded_to_this_item' => "Uploaded to this {$singular}",
                    'featured_image'        => "Featured Image",
                    'set_featured_image'    => "Set featured image",
                    'remove_featured_image' => "Remove featured image",
                    'use_featured_image'    => "Use as featured image",
                    'menu_name'             => $plural,
                    'name_admin_bar'        => $singular,
                    'items_list'            => "{$plural} list",
                    'items_list_navigation' => "{$plural} list navigation",
                    'filter_items_list'     => "Filter {$plural} list",
                ],
                'description'         => $cpt->getDescription(),
                'public'              => $cpt->isPublic(),
                'hierarchical'        => $cpt->isHierarchical(),
                'exclude_from_search' => $cpt->isExcludeFromSearch(),
                'publicly_queryable'  => $cpt->isPubliclyQueryable(),
                'show_ui'             => $cpt->isShowUi(),
                'show_in_menu'        => $cpt->getShowInMenu(),
                'show_in_nav_menus'   => $cpt->isPublic(),
                'show_in_admin_bar'   => $cpt->isShowInAdminBar(),
                'show_in_rest'        => $cpt->isShowInRest(),
                'menu_position'       => $cpt->getMenuPosition(),
                'menu_icon'           => $cpt->getMenuIcon(),
                'capability_type'     => $cpt->getCapabilityType(),
                'supports'            => $cpt->getSupports(),
                'has_archive'         => $cpt->getHasArchive(),
                'rewrite'             => ['slug' => $cpt->getRewriteSlug() ?: $cpt->getPostType()],
                'can_export'          => $cpt->canExportPosts(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Taxonomy
    // -------------------------------------------------------------------------

    public function registerTaxonomies(): void
    {
        foreach (Taxonomy::all() as $taxonomy) {
            $singular = $taxonomy->getSingularLabel() ?: ucfirst($taxonomy->getTaxonomy());
            $plural   = $taxonomy->getPluralLabel()   ?: $singular . 's';

            // Passing $object_types to register_taxonomy() also calls
            // register_taxonomy_for_object_type() for each post type internally.
            register_taxonomy($taxonomy->getTaxonomy(), $taxonomy->getAttachTo(), [
                'labels' => [
                    'name'                       => $plural,
                    'singular_name'              => $singular,
                    'menu_name'                  => $plural,
                    'all_items'                  => "All {$plural}",
                    'edit_item'                  => "Edit {$singular}",
                    'view_item'                  => "View {$singular}",
                    'update_item'                => "Update {$singular}",
                    'add_new_item'               => "Add New {$singular}",
                    'new_item_name'              => "New {$singular} Name",
                    'parent_item'                => "Parent {$singular}",
                    'parent_item_colon'          => "Parent {$singular}:",
                    'search_items'               => "Search {$plural}",
                    'popular_items'              => "Popular {$plural}",
                    'separate_items_with_commas' => "Separate {$plural} with commas",
                    'add_or_remove_items'        => "Add or remove {$plural}",
                    'choose_from_most_used'      => "Choose from the most used {$plural}",
                    'not_found'                  => "No {$plural} found.",
                    'no_terms'                   => "No {$plural}",
                    'items_list'                 => "{$plural} list",
                    'items_list_navigation'      => "{$plural} list navigation",
                ],
                'description'       => $taxonomy->getDescription(),
                'hierarchical'      => $taxonomy->isHierarchical(),
                'public'            => $taxonomy->isPublic(),
                'show_ui'           => true,
                'show_in_menu'      => true,
                'show_in_nav_menus' => $taxonomy->isShowInNavMenus(),
                'show_admin_column' => $taxonomy->isShowAdminColumn(),
                'show_tagcloud'     => $taxonomy->isShowTagCloud(),
                'show_in_rest'      => $taxonomy->isShowInRest(),
                'rewrite'           => ['slug' => $taxonomy->getRewriteSlug() ?: $taxonomy->getTaxonomy()],
            ]);
        }
    }

    public function bindTaxonomyTermHooks(): void
    {
        foreach (Taxonomy::all() as $taxonomy) {
            $fields = $taxonomy->getTermFields();

            if (empty($fields)) {
                continue;
            }

            $tax = $taxonomy->getTaxonomy();

            add_action("{$tax}_add_form_fields", function (string $taxName) use ($tax, $fields): void {
                $this->renderTermFields($tax, $fields, null);
            });

            add_action("{$tax}_edit_form_fields", function (\WP_Term $term) use ($tax, $fields): void {
                $this->renderTermFields($tax, $fields, $term->term_id);
            }, 10, 1);

            add_action("created_{$tax}", function (int $termId) use ($tax, $fields): void {
                $this->saveTermFields($tax, $termId, $fields);
            });

            add_action("edited_{$tax}", function (int $termId) use ($tax, $fields): void {
                $this->saveTermFields($tax, $termId, $fields);
            });
        }
    }

    // -------------------------------------------------------------------------
    // Options page
    // -------------------------------------------------------------------------

    public function registerOptionsPages(): void
    {
        foreach (OptionsPage::all() as $page) {
            $slug     = $page->getMenuSlug() ?: $page->getKey();
            $callback = function () use ($page): void {
                $this->renderOptionsPage($page);
            };

            if ($page->getParent() !== '') {
                add_submenu_page(
                    $page->getParent(),
                    $page->getTitle(),
                    $page->getTitle(),
                    $page->getCapability(),
                    $slug,
                    $callback,
                );
            } else {
                add_menu_page(
                    $page->getTitle(),
                    $page->getTitle(),
                    $page->getCapability(),
                    $slug,
                    $callback,
                    $page->getIcon(),
                    $page->getPosition(),
                );
            }
        }
    }

    public function bindOptionsPageSaveHooks(): void
    {
        foreach (OptionsPage::all() as $page) {
            $action = $this->saveAction($page->getKey());
            add_action("admin_post_{$action}", function () use ($page): void {
                $this->saveOptionsPage($page);
            });
        }
    }

    private function renderOptionsPage(OptionsPage $page): void
    {
        if (! current_user_can($page->getCapability())) {
            wp_die(esc_html__('You do not have permission to access this page.', 'fieldforge'));
        }

        $key     = $page->getKey();
        $action  = $this->saveAction($key);
        $adapter = new OptionsAdapter(new WpOptionsDriver());
        $saved   = $adapter->load($key) ?? [];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($page->getTitle()); ?></h1>
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved.', 'fieldforge'); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field($action, "_fieldforge_nonce_{$key}"); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
                <table class="form-table" role="presentation">
                    <?php foreach ($page->getFields() as $field): ?>
                        <tr>
                            <th scope="row">
                                <label for="fieldforge_<?php echo esc_attr($field->getKey()); ?>">
                                    <?php echo esc_html($field->getDefinition()['label'] ?: $field->getKey()); ?>
                                </label>
                            </th>
                            <td>
                                <?php $this->renderBasicField($field, $saved[$field->getKey()] ?? null, 'fieldforge_field'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function saveOptionsPage(OptionsPage $page): void
    {
        $key    = $page->getKey();
        $action = $this->saveAction($key);
        $nonce  = isset($_POST["_fieldforge_nonce_{$key}"]) && is_string($_POST["_fieldforge_nonce_{$key}"])
            ? $_POST["_fieldforge_nonce_{$key}"]
            : '';

        if (! wp_verify_nonce($nonce, $action)) {
            wp_die(esc_html__('Security check failed.', 'fieldforge'));
        }

        if (! current_user_can($page->getCapability())) {
            wp_die(esc_html__('You do not have permission to save these settings.', 'fieldforge'));
        }

        $posted = isset($_POST['fieldforge_field']) && is_array($_POST['fieldforge_field'])
            ? $_POST['fieldforge_field']
            : [];

        $data = [];
        foreach ($page->getFields() as $field) {
            $raw                      = $posted[$field->getKey()] ?? null;
            $data[$field->getKey()]   = $this->sanitizeField($field, $raw);
        }

        $adapter = new OptionsAdapter(new WpOptionsDriver());
        $adapter->save($key, $data, SchemaVersion::CURRENT);

        $slug        = $page->getMenuSlug() ?: $key;
        $redirectUrl = add_query_arg(
            ['page' => $slug, 'settings-updated' => '1'],
            admin_url('admin.php')
        );
        wp_redirect($redirectUrl);
        exit;
    }

    // -------------------------------------------------------------------------
    // Taxonomy term fields
    // -------------------------------------------------------------------------

    /**
     * @param array<int, FieldDefinition> $fields
     */
    private function renderTermFields(string $taxonomy, array $fields, ?int $termId): void
    {
        $nonce = wp_create_nonce("fieldforge_term_{$taxonomy}");

        printf(
            '<input type="hidden" name="_fieldforge_term_nonce_%s" value="%s">',
            esc_attr($taxonomy),
            esc_attr($nonce)
        );

        foreach ($fields as $field) {
            $value = $termId !== null
                ? get_term_meta($termId, "_fieldforge_term_{$field->getKey()}", true)
                : null;

            $label = esc_html($field->getDefinition()['label'] ?: $field->getKey());

            if ($termId === null) {
                echo '<div class="form-field">';
                echo "<label>{$label}</label>";
                $this->renderBasicField($field, $value ?: null, 'fieldforge_term');
                echo '</div>';
            } else {
                echo '<tr class="form-field">';
                echo "<th scope=\"row\"><label>{$label}</label></th>";
                echo '<td>';
                $this->renderBasicField($field, $value ?: null, 'fieldforge_term');
                echo '</td>';
                echo '</tr>';
            }
        }
    }

    /**
     * @param array<int, FieldDefinition> $fields
     */
    private function saveTermFields(string $taxonomy, int $termId, array $fields): void
    {
        $nonceKey = "_fieldforge_term_nonce_{$taxonomy}";
        $nonce    = isset($_POST[$nonceKey]) && is_string($_POST[$nonceKey]) ? $_POST[$nonceKey] : '';

        if (! wp_verify_nonce($nonce, "fieldforge_term_{$taxonomy}")) {
            return;
        }

        $posted = isset($_POST['fieldforge_term']) && is_array($_POST['fieldforge_term'])
            ? $_POST['fieldforge_term']
            : [];

        foreach ($fields as $field) {
            $raw   = $posted[$field->getKey()] ?? null;
            $value = $this->sanitizeField($field, $raw);
            update_term_meta($termId, "_fieldforge_term_{$field->getKey()}", $value);
        }
    }

    // -------------------------------------------------------------------------
    // Basic field renderer (Cycle 7 replaces with full Alpine.js renderer)
    // -------------------------------------------------------------------------

    private function renderBasicField(FieldDefinition $field, mixed $value, string $namespace): void
    {
        $key  = $field->getKey();
        $id   = "fieldforge_{$key}";
        $name = "{$namespace}[{$key}]";

        switch ($field->getType()->value) {
            case 'text':
            case 'email':
            case 'url':
                $inputType = $field->getType()->value;
                printf(
                    '<input type="%s" id="%s" name="%s" value="%s" class="regular-text">',
                    esc_attr($inputType),
                    esc_attr($id),
                    esc_attr($name),
                    esc_attr((string) ($value ?? ''))
                );
                break;

            case 'number':
                printf(
                    '<input type="number" id="%s" name="%s" value="%s" class="small-text">',
                    esc_attr($id),
                    esc_attr($name),
                    esc_attr((string) ($value ?? ''))
                );
                break;

            case 'textarea':
                printf(
                    '<textarea id="%s" name="%s" class="large-text" rows="5">%s</textarea>',
                    esc_attr($id),
                    esc_attr($name),
                    esc_textarea((string) ($value ?? ''))
                );
                break;

            case 'select':
                if ($field instanceof SelectField) {
                    printf('<select id="%s" name="%s">', esc_attr($id), esc_attr($name));
                    echo '<option value=""></option>';
                    foreach ($field->getOptions() as $optValue => $optLabel) {
                        printf(
                            '<option value="%s"%s>%s</option>',
                            esc_attr($optValue),
                            selected($value, $optValue, false),
                            esc_html($optLabel)
                        );
                    }
                    echo '</select>';
                }
                break;

            case 'image':
                $attachmentId = (int) ($value ?? 0);
                $src          = $attachmentId > 0
                    ? wp_get_attachment_image_url($attachmentId, 'thumbnail')
                    : false;

                printf('<input type="hidden" id="%s" name="%s" value="%d">', esc_attr($id), esc_attr($name), $attachmentId);

                if ($src) {
                    printf(
                        '<img src="%s" style="max-width:150px;display:block;margin-bottom:5px;" alt="">',
                        esc_url($src)
                    );
                }

                // JS binding is wired in Cycle 7 (Alpine.js media library integration).
                printf(
                    '<button type="button" class="button fieldforge-upload-btn" data-target="%s">%s</button>',
                    esc_attr($id),
                    esc_html__('Select Image', 'fieldforge')
                );
                break;

            default:
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" class="regular-text">',
                    esc_attr($id),
                    esc_attr($name),
                    esc_attr((string) ($value ?? ''))
                );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function sanitizeField(FieldDefinition $field, mixed $raw): mixed
    {
        $scalar = is_scalar($raw) ? (string) $raw : '';

        return match ($field->getType()->value) {
            'text'     => sanitize_text_field($scalar),
            'textarea' => sanitize_textarea_field($scalar),
            'email'    => sanitize_email($scalar),
            'url'      => esc_url_raw($scalar),
            'number'   => is_numeric($raw) ? $raw + 0 : 0,
            'select'   => sanitize_text_field($scalar),
            'image',
            'file'     => absint($raw ?? 0),
            default    => sanitize_text_field($scalar),
        };
    }

    private function saveAction(string $pageKey): string
    {
        return "fieldforge_save_options_{$pageKey}";
    }
}
