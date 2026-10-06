<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

/**
 * CtrlField → Post Types: list, create, edit and delete post types without code.
 */
final class PostTypesPage
{
    public const SLUG       = 'ctrlfield-post-types';
    private const CAP       = 'manage_options';
    private const ERRORS_TX = 'ctrlfield_post_type_errors_';

    // Query arg naming the post type being edited. Not "post_type": admin.php
    // reads that one itself and fails with "Cannot load" for our page.
    private const ARG = 'cpt';

    /** Common Dashicons offered in the picker; any dashicons-* class is accepted. */
    private const ICONS = [
        'dashicons-admin-post', 'dashicons-portfolio', 'dashicons-format-quote', 'dashicons-calendar-alt',
        'dashicons-groups', 'dashicons-businessperson', 'dashicons-products', 'dashicons-cart',
        'dashicons-location', 'dashicons-building', 'dashicons-book', 'dashicons-format-video',
        'dashicons-format-gallery', 'dashicons-megaphone', 'dashicons-awards', 'dashicons-heart',
        'dashicons-star-filled', 'dashicons-clipboard', 'dashicons-media-document', 'dashicons-food',
    ];

    public function __construct(
        private readonly PostTypeRepository $repo,
        private readonly PostTypeCodeExporter $exporter,
    ) {}

    public function registerMenu(): void
    {
        add_submenu_page(
            'ctrlfield',
            __('Post Types', 'ctrlfield'),
            __('Post Types', 'ctrlfield'),
            self::CAP,
            self::SLUG,
            [$this, 'render'],
        );
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'ctrlfield'));
        }

        $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
        $slug   = isset($_GET[self::ARG]) ? sanitize_key((string) $_GET[self::ARG]) : '';

        echo '<div class="wrap">';

        if ($action === 'new' || $action === 'edit') {
            $this->renderForm($action === 'edit' ? $this->repo->get($slug) : null);
        } elseif ($action === 'code' && ($def = $this->repo->get($slug)) !== null) {
            $this->renderCode($def);
        } else {
            $this->renderList();
        }

        echo '</div>';
    }

    private function renderList(): void
    {
        $defs     = $this->repo->all();
        $shadowed = PostTypesServiceProvider::shadowed();
        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e('Post Types', 'ctrlfield'); ?></h1>
        <a href="<?php echo esc_url($this->url(['action' => 'new'])); ?>" class="page-title-action"><?php esc_html_e('Add New Post Type', 'ctrlfield'); ?></a>
        <hr class="wp-header-end">
        <p class="description"><?php esc_html_e('Create content types such as Projects, Events or Team members without writing code. Developers can export any of them to a PHP schema file.', 'ctrlfield'); ?></p>
        <?php $this->renderNotice(); ?>

        <table class="wp-list-table widefat fixed striped" style="margin-top:16px">
            <thead>
                <tr>
                    <th style="width:40%"><?php esc_html_e('Name', 'ctrlfield'); ?></th>
                    <th><?php esc_html_e('Key', 'ctrlfield'); ?></th>
                    <th><?php esc_html_e('Entries', 'ctrlfield'); ?></th>
                    <th><?php esc_html_e('Public', 'ctrlfield'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($defs === []): ?>
                <tr><td colspan="4"><?php esc_html_e('No post types yet. Click "Add New Post Type" to create your first one.', 'ctrlfield'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($defs as $def):
                $total = array_sum(array_map('intval', (array) wp_count_posts($def->slug)));
                ?>
                <tr>
                    <td>
                        <strong>
                            <span class="dashicons <?php echo esc_attr($def->icon); ?>" style="color:#646970"></span>
                            <a class="row-title" href="<?php echo esc_url($this->url(['action' => 'edit', self::ARG => $def->slug])); ?>"><?php echo esc_html($def->plural); ?></a>
                        </strong>
                        <?php if (in_array($def->slug, $shadowed, true)): ?>
                            <p class="description" style="color:#b32d2e"><?php esc_html_e('Not active: a post type with this key is already defined in code, which takes precedence.', 'ctrlfield'); ?></p>
                        <?php endif; ?>
                        <div class="row-actions">
                            <span><a href="<?php echo esc_url($this->url(['action' => 'edit', self::ARG => $def->slug])); ?>"><?php esc_html_e('Edit', 'ctrlfield'); ?></a> | </span>
                            <span><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $def->slug)); ?>"><?php esc_html_e('View entries', 'ctrlfield'); ?></a> | </span>
                            <span><a href="<?php echo esc_url($this->url(['action' => 'code', self::ARG => $def->slug])); ?>"><?php esc_html_e('Export PHP', 'ctrlfield'); ?></a> | </span>
                            <span class="trash"><a href="<?php echo esc_url($this->deleteUrl($def->slug)); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this post type? Its entries stay in the database and come back if you recreate it with the same key.', 'ctrlfield')); ?>');"><?php esc_html_e('Delete', 'ctrlfield'); ?></a></span>
                        </div>
                    </td>
                    <td><code><?php echo esc_html($def->slug); ?></code></td>
                    <td><?php echo esc_html((string) $total); ?></td>
                    <td><?php echo $def->public ? esc_html__('Yes', 'ctrlfield') : esc_html__('No', 'ctrlfield'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderForm(?PostTypeDefinition $def): void
    {
        $isNew  = $def === null;
        $errors = $this->takeErrors();
        // Re-fill the form with what the user typed when validation failed.
        $values = $errors['_input'] ?? ($def?->toArray() ?? (new PostTypeDefinition('', '', ''))->toArray());
        unset($errors['_input']);
        $v = static fn (string $k) => $values[$k] ?? '';
        ?>
        <h1><?php echo $isNew ? esc_html__('Add New Post Type', 'ctrlfield') : esc_html(sprintf(__('Edit Post Type: %s', 'ctrlfield'), $def->plural)); ?></h1>
        <p><a href="<?php echo esc_url($this->url()); ?>">&larr; <?php esc_html_e('All post types', 'ctrlfield'); ?></a></p>

        <?php if ($errors !== []): ?>
            <div class="notice notice-error"><p><?php esc_html_e('Please fix the highlighted fields.', 'ctrlfield'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="ctrlfield_save_post_type">
            <input type="hidden" name="previous_slug" value="<?php echo esc_attr($isNew ? '' : $def->slug); ?>">
            <?php wp_nonce_field('ctrlfield_save_post_type'); ?>

            <h2><?php esc_html_e('Names', 'ctrlfield'); ?></h2>
            <table class="form-table" role="presentation">
                <?php $this->textRow('plural', __('Plural name', 'ctrlfield'), (string) $v('plural'), $errors, __('Shown in the admin menu, e.g. "Projects".', 'ctrlfield'), true); ?>
                <?php $this->textRow('singular', __('Singular name', 'ctrlfield'), (string) $v('singular'), $errors, __('e.g. "Project".', 'ctrlfield'), true); ?>
                <?php $this->textRow('slug', __('Key', 'ctrlfield'), (string) $v('slug'), $errors, __('Internal name, e.g. "project". Lowercase, max. 20 characters. Changing it later hides existing entries.', 'ctrlfield'), true); ?>
                <?php $this->textRow('description', __('Description', 'ctrlfield'), (string) $v('description'), $errors, '', false); ?>
            </table>

            <h2><?php esc_html_e('Editor', 'ctrlfield'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Features', 'ctrlfield'); ?></th>
                    <td>
                        <fieldset>
                        <?php foreach (PostTypeDefinition::SUPPORTS as $feature): ?>
                            <label style="display:inline-block;min-width:170px;margin:0 0 6px">
                                <input type="checkbox" name="supports[]" value="<?php echo esc_attr($feature); ?>" <?php checked(in_array($feature, (array) $v('supports'), true)); ?>>
                                <?php echo esc_html($this->featureLabel($feature)); ?>
                            </label>
                        <?php endforeach; ?>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ctrlfield-icon"><?php esc_html_e('Menu icon', 'ctrlfield'); ?></label></th>
                    <td>
                        <fieldset style="display:flex;flex-wrap:wrap;gap:6px;max-width:560px">
                        <?php foreach (self::ICONS as $icon): ?>
                            <label title="<?php echo esc_attr($icon); ?>" style="border:1px solid #c3c4c7;border-radius:4px;padding:6px;cursor:pointer;background:#fff">
                                <input type="radio" name="icon" value="<?php echo esc_attr($icon); ?>" <?php checked($v('icon'), $icon); ?> style="margin:0 4px 0 0">
                                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                            </label>
                        <?php endforeach; ?>
                        </fieldset>
                        <?php if (isset($errors['icon'])): ?><p class="description" style="color:#b32d2e"><?php echo esc_html($errors['icon']); ?></p><?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ctrlfield-menu_position"><?php esc_html_e('Menu position', 'ctrlfield'); ?></label></th>
                    <td>
                        <input type="number" id="ctrlfield-menu_position" name="menu_position" min="5" max="100" value="<?php echo esc_attr((string) $v('menu_position')); ?>" class="small-text">
                        <p class="description"><?php esc_html_e('5 = below Posts, 20 = below Pages, 25 = below Comments.', 'ctrlfield'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Visibility', 'ctrlfield'); ?></h2>
            <table class="form-table" role="presentation">
                <?php $this->checkboxRow('public', __('Public', 'ctrlfield'), (bool) $v('public'), __('Entries have their own page on the website and appear in search.', 'ctrlfield')); ?>
                <?php $this->checkboxRow('has_archive', __('Archive page', 'ctrlfield'), (bool) $v('has_archive'), __('A listing page with all entries, e.g. /projects/.', 'ctrlfield')); ?>
                <?php $this->checkboxRow('hierarchical', __('Hierarchical', 'ctrlfield'), (bool) $v('hierarchical'), __('Entries can have parent entries, like Pages.', 'ctrlfield')); ?>
                <?php $this->checkboxRow('show_in_rest', __('Block editor & REST API', 'ctrlfield'), (bool) $v('show_in_rest'), __('Use the block editor and expose entries in the REST API.', 'ctrlfield')); ?>
                <?php $this->textRow('rewrite_slug', __('URL slug', 'ctrlfield'), (string) $v('rewrite_slug'), $errors, __('Optional. The part of the URL before the entry name, e.g. "work" for /work/my-project/. Defaults to the key.', 'ctrlfield'), false); ?>
            </table>

            <?php submit_button($isNew ? __('Create Post Type', 'ctrlfield') : __('Save Changes', 'ctrlfield')); ?>
        </form>
        <?php
    }

    private function renderCode(PostTypeDefinition $def): void
    {
        ?>
        <h1><?php echo esc_html(sprintf(__('Export PHP: %s', 'ctrlfield'), $def->plural)); ?></h1>
        <p><a href="<?php echo esc_url($this->url()); ?>">&larr; <?php esc_html_e('All post types', 'ctrlfield'); ?></a></p>
        <p><?php esc_html_e('Save this as a .php file in your CtrlField schema directory to version the post type in git. Then delete it here — code takes precedence.', 'ctrlfield'); ?></p>
        <textarea class="large-text code" rows="18" readonly onclick="this.select()"><?php echo esc_textarea($this->exporter->export($def)); ?></textarea>
        <?php
    }

    // -------------------------------------------------------------------------
    // Handlers (admin-post.php)
    // -------------------------------------------------------------------------

    public function handleSave(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to do this.', 'ctrlfield'), 403);
        }
        check_admin_referer('ctrlfield_save_post_type');

        $input        = wp_unslash($_POST);
        $previousSlug = sanitize_key((string) ($input['previous_slug'] ?? ''));
        [$def, $errors] = PostTypeDefinition::fromInput((array) $input);

        if ($def !== null) {
            $existing = $this->repo->all();
            $isRename = $previousSlug !== '' && $previousSlug !== $def->slug;

            if (($previousSlug === '' || $isRename) && isset($existing[$def->slug])) {
                $errors['slug'] = __('Another post type created here already uses this key.', 'ctrlfield');
            } elseif (($previousSlug === '' || $isRename) && post_type_exists($def->slug)) {
                $errors['slug'] = __('This key is already used by WordPress, a plugin or your theme code.', 'ctrlfield');
            }
        }

        if ($def === null || $errors !== []) {
            $errors['_input'] = (array) $input;
            set_transient(self::ERRORS_TX . get_current_user_id(), $errors, 300);
            wp_safe_redirect($this->url(
                $previousSlug !== '' ? ['action' => 'edit', self::ARG => $previousSlug] : ['action' => 'new']
            ));
            exit;
        }

        $this->repo->save($def, $previousSlug !== '' ? $previousSlug : null);
        wp_safe_redirect($this->url(['notice' => $previousSlug === '' ? 'created' : 'saved', self::ARG => $def->slug]));
        exit;
    }

    public function handleDelete(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to do this.', 'ctrlfield'), 403);
        }

        $slug = isset($_GET[self::ARG]) ? sanitize_key((string) $_GET[self::ARG]) : '';
        check_admin_referer('ctrlfield_delete_post_type_' . $slug);

        $this->repo->delete($slug);
        wp_safe_redirect($this->url(['notice' => 'deleted']));
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param array<string, string> $args */
    private function url(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => self::SLUG], $args), admin_url('admin.php'));
    }

    private function deleteUrl(string $slug): string
    {
        return wp_nonce_url(
            add_query_arg(['action' => 'ctrlfield_delete_post_type', self::ARG => $slug], admin_url('admin-post.php')),
            'ctrlfield_delete_post_type_' . $slug
        );
    }

    /** @return array<string, mixed> */
    private function takeErrors(): array
    {
        $key    = self::ERRORS_TX . get_current_user_id();
        $errors = get_transient($key);
        delete_transient($key);

        return is_array($errors) ? $errors : [];
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['notice']) ? sanitize_key((string) $_GET['notice']) : '';
        $slug   = isset($_GET[self::ARG]) ? sanitize_key((string) $_GET[self::ARG]) : '';
        $def    = $slug !== '' ? $this->repo->get($slug) : null;

        $messages = [
            'created' => __('Post type created. It is now in the admin menu.', 'ctrlfield'),
            'saved'   => __('Post type saved.', 'ctrlfield'),
            'deleted' => __('Post type deleted. Its entries are still in the database.', 'ctrlfield'),
        ];

        if (! isset($messages[$notice])) {
            return;
        }

        printf('<div class="notice notice-success is-dismissible"><p>%s', esc_html($messages[$notice]));
        if ($def !== null && $notice === 'created') {
            printf(
                ' <a href="%s">%s</a>',
                esc_url(admin_url('post-new.php?post_type=' . $def->slug)),
                esc_html(sprintf(__('Add the first %s', 'ctrlfield'), $def->singular))
            );
        }
        echo '</p></div>';
    }

    /** @param array<string, mixed> $errors */
    private function textRow(string $name, string $label, string $value, array $errors, string $help, bool $required): void
    {
        $id = 'ctrlfield-' . $name;
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?><?php echo $required ? ' <span aria-hidden="true" style="color:#b32d2e">*</span>' : ''; ?></label></th>
            <td>
                <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" <?php echo $required ? 'required' : ''; ?> <?php echo isset($errors[$name]) ? 'aria-invalid="true" style="border-color:#b32d2e"' : ''; ?>>
                <?php if (isset($errors[$name])): ?>
                    <p class="description" style="color:#b32d2e"><?php echo esc_html((string) $errors[$name]); ?></p>
                <?php elseif ($help !== ''): ?>
                    <p class="description"><?php echo esc_html($help); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function checkboxRow(string $name, string $label, bool $checked, string $help): void
    {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <label><input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($checked); ?>> <?php echo esc_html($help); ?></label>
            </td>
        </tr>
        <?php
    }

    private function featureLabel(string $feature): string
    {
        return match ($feature) {
            'title'           => __('Title', 'ctrlfield'),
            'editor'          => __('Content editor', 'ctrlfield'),
            'thumbnail'       => __('Featured image', 'ctrlfield'),
            'excerpt'         => __('Excerpt', 'ctrlfield'),
            'author'          => __('Author', 'ctrlfield'),
            'comments'        => __('Comments', 'ctrlfield'),
            'revisions'       => __('Revisions', 'ctrlfield'),
            'page-attributes' => __('Order & parent', 'ctrlfield'),
            'custom-fields'   => __('Custom fields (WP)', 'ctrlfield'),
            default           => $feature,
        };
    }
}
