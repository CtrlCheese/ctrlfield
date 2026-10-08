<?php

declare(strict_types=1);

namespace CtrlField\Integrations\WordPress;

use CtrlField\Bootstrap\ServiceProvider;
use CtrlField\Builder\CPT;
use CtrlField\Builder\OptionsPage;
use CtrlField\Builder\Taxonomy;
use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\SelectField;
use CtrlField\Storage\Drivers\WpOptionsDriver;
use CtrlField\Storage\OptionsAdapter;

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
        add_action('deleted_post', [\CtrlField\Storage\RelationshipAdapter::class, 'deleteForPost']);
        add_action('init', [$this, 'registerTaxonomies'], 20);
        add_action('init', [$this, 'bindTaxonomyTermHooks'], 20);
        add_action('init', [$this, 'bindOptionsPageSaveHooks'], 20);
        // After top-level menus (priority 10, CtrlField's own at 9): a submenu added
        // before its parent exists gets no page hook and opens "page not found".
        add_action('admin_menu', [$this, 'registerOptionsPages'], 20);
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
                'labels'              => self::postTypeLabels($singular, $plural),
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
    // Labels — translatable. %s is the name the site owner typed, so the
    // phrases are built to read naturally in gendered languages too.
    // -------------------------------------------------------------------------

    /** @return array<string, string> */
    private static function postTypeLabels(string $singular, string $plural): array
    {
        return [
            'name'                  => $plural,
            'singular_name'         => $singular,
            'menu_name'             => $plural,
            'name_admin_bar'        => $singular,
            'add_new'               => __('Add New', 'ctrlfield'),
            /* translators: %s: post type singular name */
            'add_new_item'          => sprintf(__('Add %s', 'ctrlfield'), $singular),
            /* translators: %s: post type singular name */
            'edit_item'             => sprintf(__('Edit %s', 'ctrlfield'), $singular),
            /* translators: %s: post type singular name */
            'new_item'              => sprintf(__('New entry: %s', 'ctrlfield'), $singular),
            /* translators: %s: post type singular name */
            'view_item'             => sprintf(__('View %s', 'ctrlfield'), $singular),
            /* translators: %s: post type plural name */
            'view_items'            => sprintf(__('View %s', 'ctrlfield'), $plural),
            /* translators: %s: post type plural name */
            'search_items'          => sprintf(__('Search %s', 'ctrlfield'), $plural),
            'not_found'             => __('No entries found.', 'ctrlfield'),
            'not_found_in_trash'    => __('No entries found in Trash.', 'ctrlfield'),
            /* translators: %s: post type plural name */
            'all_items'             => sprintf(__('All %s', 'ctrlfield'), $plural),
            /* translators: %s: post type plural name */
            'archives'              => sprintf(__('%s archive', 'ctrlfield'), $plural),
            'attributes'            => __('Attributes', 'ctrlfield'),
            'insert_into_item'      => __('Insert into entry', 'ctrlfield'),
            'uploaded_to_this_item' => __('Uploaded to this entry', 'ctrlfield'),
            'featured_image'        => __('Featured image', 'ctrlfield'),
            'set_featured_image'    => __('Set featured image', 'ctrlfield'),
            'remove_featured_image' => __('Remove featured image', 'ctrlfield'),
            'use_featured_image'    => __('Use as featured image', 'ctrlfield'),
            /* translators: %s: post type plural name */
            'items_list'            => sprintf(__('%s list', 'ctrlfield'), $plural),
            /* translators: %s: post type plural name */
            'items_list_navigation' => sprintf(__('%s list navigation', 'ctrlfield'), $plural),
            /* translators: %s: post type plural name */
            'filter_items_list'     => sprintf(__('Filter %s list', 'ctrlfield'), $plural),
        ];
    }

    /** @return array<string, string> */
    private static function taxonomyLabels(string $singular, string $plural): array
    {
        return [
            'name'                       => $plural,
            'singular_name'              => $singular,
            'menu_name'                  => $plural,
            /* translators: %s: taxonomy plural name */
            'all_items'                  => sprintf(__('All %s', 'ctrlfield'), $plural),
            /* translators: %s: taxonomy singular name */
            'edit_item'                  => sprintf(__('Edit %s', 'ctrlfield'), $singular),
            /* translators: %s: taxonomy singular name */
            'view_item'                  => sprintf(__('View %s', 'ctrlfield'), $singular),
            /* translators: %s: taxonomy singular name */
            'update_item'                => sprintf(__('Update %s', 'ctrlfield'), $singular),
            /* translators: %s: taxonomy singular name */
            'add_new_item'               => sprintf(__('Add %s', 'ctrlfield'), $singular),
            'new_item_name'              => __('Name', 'ctrlfield'),
            'parent_item'                => __('Parent', 'ctrlfield'),
            'parent_item_colon'          => __('Parent:', 'ctrlfield'),
            /* translators: %s: taxonomy plural name */
            'search_items'               => sprintf(__('Search %s', 'ctrlfield'), $plural),
            'popular_items'              => __('Most used', 'ctrlfield'),
            'separate_items_with_commas' => __('Separate with commas', 'ctrlfield'),
            'add_or_remove_items'        => __('Add or remove', 'ctrlfield'),
            'choose_from_most_used'      => __('Choose from the most used', 'ctrlfield'),
            'not_found'                  => __('Nothing found.', 'ctrlfield'),
            'no_terms'                   => __('None', 'ctrlfield'),
            /* translators: %s: taxonomy plural name */
            'items_list'                 => sprintf(__('%s list', 'ctrlfield'), $plural),
            /* translators: %s: taxonomy plural name */
            'items_list_navigation'      => sprintf(__('%s list navigation', 'ctrlfield'), $plural),
        ];
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
                'labels' => self::taxonomyLabels($singular, $plural),
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
            wp_die(esc_html__('You do not have permission to access this page.', 'ctrlfield'));
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
                    <p><?php esc_html_e('Settings saved.', 'ctrlfield'); ?></p>
                </div>
            <?php endif; ?>
            <?php foreach ($this->takeOptionsErrors($key) as $error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endforeach; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field($action, "_ctrlfield_nonce_{$key}"); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
                <?php
                // The same field UI as posts: tabs, groups, repeaters, WYSIWYG, drag and drop.
                $renderer = new \CtrlField\Admin\MetaBox\MetaBoxRenderer();
                foreach ($this->optionsPageGroups($page) as $group) {
                    echo '<div class="postbox ctrlfield-options-group"><div class="inside">';
                    if ($group->getTitle() !== '' && $group->getStyle() !== 'seamless') {
                        echo '<h2 class="ctrlfield-options-group__title">' . esc_html($group->getTitle()) . '</h2>';
                    }
                    $renderer->renderStoredGroup($group, $saved);
                    echo '</div></div>';
                }
                ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * The page's own fields plus every field group located on it
     * (where('options_page', '==', key): ACF imports, admin-made groups).
     *
     * @return list<FieldDefinition>
     */
    private function optionsPageFields(OptionsPage $page): array
    {
        $fields = [];
        foreach ($this->optionsPageGroups($page) as $group) {
            foreach ($group->getFields() as $field) {
                $fields[$field->getKey()] = $field;
            }
        }

        return array_values($fields);
    }

    /** @return list<\CtrlField\Builder\FieldGroup> */
    private function optionsPageGroups(OptionsPage $page): array
    {
        return array_values(\CtrlField\Registry\ContextRegistry::resolve(new \CtrlField\Builder\AdminContext(optionsPage: $page->getKey())));
    }

    /** @return list<string> */
    private function takeOptionsErrors(string $key): array
    {
        $tx     = 'ctrlfield_options_errors_' . get_current_user_id() . '_' . md5($key);
        $errors = get_transient($tx);
        delete_transient($tx);

        return is_array($errors) ? array_values(array_map('strval', $errors)) : [];
    }

    private function saveOptionsPage(OptionsPage $page): void
    {
        $key    = $page->getKey();
        $action = $this->saveAction($key);
        $nonce  = isset($_POST["_ctrlfield_nonce_{$key}"]) && is_string($_POST["_ctrlfield_nonce_{$key}"])
            ? $_POST["_ctrlfield_nonce_{$key}"]
            : '';

        if (! wp_verify_nonce($nonce, $action)) {
            wp_die(esc_html__('Security check failed.', 'ctrlfield'));
        }

        if (! current_user_can($page->getCapability())) {
            wp_die(esc_html__('You do not have permission to save these settings.', 'ctrlfield'));
        }

        // One JSON payload per group (MetaBoxRenderer), validated and sanitized
        // like a post save; invalid fields are skipped and reported.
        $payloads = isset($_POST['ctrlfield_payload']) && is_array($_POST['ctrlfield_payload'])
            ? (array) wp_unslash($_POST['ctrlfield_payload'])
            : [];
        $values = [];
        foreach ($payloads as $json) {
            $decoded = is_string($json) ? json_decode($json, true) : null;
            if (is_array($decoded)) {
                $values = array_merge($values, $decoded);
            }
        }

        $defs = [];
        foreach ($this->optionsPageFields($page) as $field) {
            if (! $field->isUiOnly()) {
                $defs[$field->getKey()] = $field;
            }
        }
        $values  = array_intersect_key($values, $defs);
        $skipped = \CtrlField\Data\FieldWriter::write('options', $key, $values, true, $defs);
        if ($skipped !== []) {
            $messages = [];
            foreach ($skipped as $fieldKey => $why) {
                $messages[] = sprintf('%s: %s', $fieldKey, $why);
            }
            set_transient('ctrlfield_options_errors_' . get_current_user_id() . '_' . md5($key), $messages, 300);
        }

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
        $nonce = wp_create_nonce("ctrlfield_term_{$taxonomy}");

        printf(
            '<input type="hidden" name="_ctrlfield_term_nonce_%s" value="%s">',
            esc_attr($taxonomy),
            esc_attr($nonce)
        );

        foreach ($fields as $field) {
            $value = $termId !== null
                ? get_term_meta($termId, "_ctrlfield_term_{$field->getKey()}", true)
                : null;

            $label = esc_html($field->getDefinition()['label'] ?: $field->getKey());

            if ($termId === null) {
                echo '<div class="form-field">';
                echo "<label>{$label}</label>";
                $this->renderBasicField($field, $value ?: null, 'ctrlfield_term');
                echo '</div>';
            } else {
                echo '<tr class="form-field">';
                echo "<th scope=\"row\"><label>{$label}</label></th>";
                echo '<td>';
                $this->renderBasicField($field, $value ?: null, 'ctrlfield_term');
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
        $nonceKey = "_ctrlfield_term_nonce_{$taxonomy}";
        $nonce    = isset($_POST[$nonceKey]) && is_string($_POST[$nonceKey]) ? $_POST[$nonceKey] : '';

        if (! wp_verify_nonce($nonce, "ctrlfield_term_{$taxonomy}")) {
            return;
        }

        $posted = isset($_POST['ctrlfield_term']) && is_array($_POST['ctrlfield_term'])
            ? $_POST['ctrlfield_term']
            : [];

        foreach ($fields as $field) {
            $raw   = $posted[$field->getKey()] ?? null;
            $value = $this->sanitizeField($field, $raw);
            update_term_meta($termId, "_ctrlfield_term_{$field->getKey()}", $value);
        }
    }

    // -------------------------------------------------------------------------
    // Basic field renderer (Cycle 7 replaces with full Alpine.js renderer)
    // -------------------------------------------------------------------------

    private function renderBasicField(FieldDefinition $field, mixed $value, string $namespace): void
    {
        $key  = $field->getKey();
        $id   = "ctrlfield_{$key}";
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
                    '<button type="button" class="button ctrlfield-upload-btn" data-target="%s">%s</button>',
                    esc_attr($id),
                    esc_html__('Select Image', 'ctrlfield')
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
        return "ctrlfield_save_options_{$pageKey}";
    }
}
