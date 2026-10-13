<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

use CtrlField\Admin\MetaBox\MetaBoxServiceProvider;
use CtrlField\Builder\CPT;
use CtrlField\Builder\Taxonomy;
use CtrlField\Fields\Renderers\MessageRenderer;

/**
 * CtrlField → Post Types: post types and taxonomies without code — list,
 * create, edit, delete, export to PHP. Two tabs on one page; the form has a
 * sidebar with the URLs it will produce.
 *
 * Excluded from PHPStan — WP admin functions.
 */
final class PostTypesPage
{
    public const SLUG       = 'ctrlfield-post-types';
    private const CAP       = 'manage_options';
    private const ERRORS_TX = 'ctrlfield_post_type_errors_';

    // Query args naming what is edited. Not "post_type"/"taxonomy": admin.php
    // reads those itself and fails with "Cannot load" for our page.
    private const ARG     = 'cpt';
    private const TAX_ARG = 'tax';

    private const FLAGS = ['public', 'hierarchical', 'has_archive', 'show_in_rest', 'exclude_from_search', 'show_admin_column'];

    private string $hook = '';

    public function __construct(
        private readonly PostTypeRepository $repo,
        private readonly PostTypeCodeExporter $exporter,
    ) {}

    public function registerMenu(): void
    {
        $hook       = add_submenu_page(
            'ctrlfield',
            __('Post Types', 'ctrlfield'),
            __('Post Types', 'ctrlfield'),
            self::CAP,
            self::SLUG,
            [$this, 'render'],
        );
        $this->hook = is_string($hook) ? $hook : '';
    }

    /** The script (Alpine, the icon dialog) and what it needs. */
    public function enqueueAssets(string $hook): void
    {
        if ($this->hook === '' || $hook !== $this->hook) {
            return;
        }
        wp_enqueue_style('ctrlfield-admin', CTRLFIELD_URL . 'assets/admin/ctrlfield.css', [], MetaBoxServiceProvider::assetVersion('ctrlfield.css'));
        wp_enqueue_script('ctrlfield-admin', CTRLFIELD_URL . 'assets/admin/ctrlfield.js', [], MetaBoxServiceProvider::assetVersion('ctrlfield.js'), true);
        wp_localize_script('ctrlfield-admin', 'ctrlfieldData', [
            'restUrl'   => rest_url(),
            'restNonce' => wp_create_nonce('wp_rest'),
            'i18n'      => ['icon' => MetaBoxServiceProvider::iconStrings()],
            'icons'     => \CtrlField\Data\IconLibrary::clientConfig(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'ctrlfield'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- which screen to show
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';
        $tab    = isset($_GET['tab']) && $_GET['tab'] === 'taxonomies' ? 'taxonomies' : 'post_types';
        $slug   = isset($_GET[self::ARG]) ? sanitize_key(wp_unslash((string) $_GET[self::ARG])) : '';
        $tax    = isset($_GET[self::TAX_ARG]) ? sanitize_key(wp_unslash((string) $_GET[self::TAX_ARG])) : '';
        // phpcs:enable

        echo '<div class="wrap ctrlf-types-page">';

        if ($tab === 'taxonomies') {
            $def = $this->repo->taxonomy($tax);
            match (true) {
                $action === 'new'                     => $this->renderTaxonomyForm(null),
                $action === 'edit' && $def !== null   => $this->renderTaxonomyForm($def),
                $action === 'code' && $def !== null   => $this->renderCode($def->plural, $this->exporter->exportTaxonomy($def), 'taxonomies'),
                default                               => $this->renderList('taxonomies', in_array($action, ['edit', 'code'], true)),
            };
        } else {
            $def = $this->repo->get($slug);
            match (true) {
                $action === 'new'                     => $this->renderForm(null),
                $action === 'edit' && $def !== null   => $this->renderForm($def),
                $action === 'code' && $def !== null   => $this->renderCode($def->plural, $this->exporter->export($def), 'post_types'),
                default                               => $this->renderList('post_types', in_array($action, ['edit', 'code'], true)),
            };
        }

        echo '</div>';
    }

    // ── List ─────────────────────────────────────────────────────────────────

    private function renderList(string $tab, bool $notFound): void
    {
        $types = $this->repo->all();
        $taxes = $this->repo->taxonomies();
        $isTax = $tab === 'taxonomies';
        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e('Post Types', 'ctrlfield'); ?></h1>
        <a href="<?php echo esc_url($this->url(['action' => 'new'], $tab)); ?>" class="page-title-action"><?php echo esc_html($isTax ? __('Add New Taxonomy', 'ctrlfield') : __('Add New Post Type', 'ctrlfield')); ?></a>
        <hr class="wp-header-end">
        <nav class="nav-tab-wrapper ctrlf-types__tabs">
            <a href="<?php echo esc_url($this->url([], 'post_types')); ?>" class="nav-tab<?php echo $isTax ? '' : ' nav-tab-active'; ?>">
                <?php echo esc_html(sprintf(/* translators: %d: number of post types */ __('Post types (%d)', 'ctrlfield'), count($types))); ?>
            </a>
            <a href="<?php echo esc_url($this->url([], 'taxonomies')); ?>" class="nav-tab<?php echo $isTax ? ' nav-tab-active' : ''; ?>">
                <?php echo esc_html(sprintf(/* translators: %d: number of taxonomies */ __('Taxonomies (%d)', 'ctrlfield'), count($taxes))); ?>
            </a>
        </nav>
        <p class="ctrlf-types__lead"><?php echo esc_html($isTax
            ? __('Categories and tags for your content — e.g. "Services" for Projects. Each one can be used with several post types.', 'ctrlfield')
            : __('Content types such as Projects, Events or Team members, without code. Developers can export any of them to a PHP schema file.', 'ctrlfield')); ?></p>
        <?php
        if ($notFound) {
            $text = $isTax ? __('That taxonomy does not exist (any more).', 'ctrlfield') : __('That post type does not exist (any more).', 'ctrlfield');
            echo MessageRenderer::dismissible('error', esc_html($text)); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped
        }
        $this->renderNotice();

        if ($isTax) {
            $this->taxonomyRows($taxes);
        } else {
            $this->postTypeRows($types);
        }
    }

    /** @param array<string, PostTypeDefinition> $defs */
    private function postTypeRows(array $defs): void
    {
        $shadowed = PostTypesServiceProvider::shadowed();
        $taxNames = $this->taxonomyNames();

        if ($defs === []) {
            $this->emptyState(
                __('No post types yet', 'ctrlfield'),
                __('Create one for each kind of content that is not a post or a page — Projects, Events, Team members…', 'ctrlfield'),
                __('Add New Post Type', 'ctrlfield'),
                'post_types'
            );
        } else {
            echo '<ul class="ctrlf-types">';
            foreach ($defs as $def) {
                $object   = get_post_type_object($def->slug);
                $inCode   = in_array($def->slug, $shadowed, true);
                // Registered later by code (after init:20): WordPress keeps the last one.
                $overrode = ! $inCode && $object !== null && (string) $object->labels->name !== $def->plural;
                $active   = ! $inCode && ! $overrode;
                $count    = $active ? array_sum(array_map('intval', (array) wp_count_posts($def->slug))) : null;
                $editUrl  = $this->url(['action' => 'edit', self::ARG => $def->slug], 'post_types');
                $meta     = array_filter([
                    $def->public ? '/' . ($def->rewriteSlug !== '' ? $def->rewriteSlug : $def->slug) . '/' : __('admin only', 'ctrlfield'),
                    $def->public && $def->archive() !== false ? __('archive', 'ctrlfield') : '',
                    $def->hierarchical ? __('hierarchical', 'ctrlfield') : '',
                    $def->showInRest ? __('block editor', 'ctrlfield') : '',
                    implode(', ', array_map(static fn (string $t): string => $taxNames[$t] ?? $t, $def->taxonomies)),
                ]);
                $warning = match (true) {
                    $inCode   => __('Not active: a post type with this key is defined in code, which takes precedence.', 'ctrlfield'),
                    $overrode => __('Not active: code registered later replaces it.', 'ctrlfield'),
                    default   => '',
                };
                $this->row(
                    '<span class="dashicons ' . esc_attr($def->icon) . '" aria-hidden="true"></span>',
                    $def->plural,
                    $def->slug,
                    $editUrl,
                    $meta,
                    $count,
                    $warning,
                    array_filter([
                        __('Edit', 'ctrlfield')       => $editUrl,
                        __('Entries', 'ctrlfield')    => $active ? admin_url('edit.php?post_type=' . $def->slug) : '',
                        __('Export PHP', 'ctrlfield') => $this->url(['action' => 'code', self::ARG => $def->slug], 'post_types'),
                    ]),
                    $this->deleteUrl('ctrlfield_delete_post_type', self::ARG, $def->slug),
                    __('Delete this post type? Its entries stay in the database and come back if you recreate it with the same key.', 'ctrlfield')
                );
            }
            echo '</ul>';
        }

        // Post types defined in code (schema files, the theme): listed, edited in code.
        $inCode = array_diff_key(CPT::all(), $defs);
        if ($inCode !== []) {
            echo '<h2 class="ctrlf-types__subhead">' . esc_html__('Defined in code', 'ctrlfield') . '</h2><ul class="ctrlf-types ctrlf-types--code">';
            foreach ($inCode as $key => $cpt) {
                $this->row(
                    '<span class="dashicons ' . esc_attr($cpt->getMenuIcon()) . '" aria-hidden="true"></span>',
                    $cpt->getPluralLabel() !== '' ? $cpt->getPluralLabel() : (string) $key,
                    (string) $key,
                    '',
                    [__('CPT::make() in a schema file', 'ctrlfield')],
                    array_sum(array_map('intval', (array) wp_count_posts((string) $key))),
                    '',
                    [__('Entries', 'ctrlfield') => admin_url('edit.php?post_type=' . $key)],
                    '',
                    ''
                );
            }
            echo '</ul>';
        }
    }

    /** @param array<string, TaxonomyDefinition> $defs */
    private function taxonomyRows(array $defs): void
    {
        $shadowed  = PostTypesServiceProvider::shadowedTaxonomies();
        $typeNames = $this->postTypeNames();

        if ($defs === []) {
            $this->emptyState(
                __('No taxonomies yet', 'ctrlfield'),
                __('Group entries by topic: "Services" for Projects, "Locations" for Events… Hierarchical ones work like categories, flat ones like tags.', 'ctrlfield'),
                __('Add New Taxonomy', 'ctrlfield'),
                'taxonomies'
            );
        } else {
            echo '<ul class="ctrlf-types">';
            foreach ($defs as $def) {
                $active  = ! in_array($def->slug, $shadowed, true);
                $editUrl = $this->url(['action' => 'edit', self::TAX_ARG => $def->slug], 'taxonomies');
                $first   = $def->postTypes[0] ?? '';
                $this->row(
                    '<span class="dashicons ' . ($def->hierarchical ? 'dashicons-category' : 'dashicons-tag') . '" aria-hidden="true"></span>',
                    $def->plural,
                    $def->slug,
                    $editUrl,
                    array_filter([
                        $def->hierarchical ? __('like categories', 'ctrlfield') : __('like tags', 'ctrlfield'),
                        $def->postTypes !== []
                            ? implode(', ', array_map(static fn (string $t): string => $typeNames[$t] ?? $t, $def->postTypes))
                            : __('not used by any post type yet', 'ctrlfield'),
                        $def->public ? '/' . ($def->rewriteSlug !== '' ? $def->rewriteSlug : $def->slug) . '/' : __('admin only', 'ctrlfield'),
                    ]),
                    $active ? self::termCount($def->slug) : null,
                    $active ? '' : __('Not active: a taxonomy with this key is defined in code or by another plugin.', 'ctrlfield'),
                    array_filter([
                        __('Edit', 'ctrlfield')       => $editUrl,
                        __('Terms', 'ctrlfield')      => $active ? admin_url('edit-tags.php?taxonomy=' . $def->slug . ($first !== '' ? '&post_type=' . $first : '')) : '',
                        __('Export PHP', 'ctrlfield') => $this->url(['action' => 'code', self::TAX_ARG => $def->slug], 'taxonomies'),
                    ]),
                    $this->deleteUrl('ctrlfield_delete_taxonomy', self::TAX_ARG, $def->slug),
                    __('Delete this taxonomy? Its terms stay in the database and come back if you recreate it with the same key.', 'ctrlfield'),
                    __('terms', 'ctrlfield')
                );
            }
            echo '</ul>';
        }

        $inCode = array_diff_key(Taxonomy::all(), $defs);
        if ($inCode !== []) {
            echo '<h2 class="ctrlf-types__subhead">' . esc_html__('Defined in code', 'ctrlfield') . '</h2><ul class="ctrlf-types ctrlf-types--code">';
            foreach ($inCode as $key => $taxonomy) {
                $this->row(
                    '<span class="dashicons ' . ($taxonomy->isHierarchical() ? 'dashicons-category' : 'dashicons-tag') . '" aria-hidden="true"></span>',
                    $taxonomy->getPluralLabel() !== '' ? $taxonomy->getPluralLabel() : (string) $key,
                    (string) $key,
                    '',
                    [__('Taxonomy::make() in a schema file', 'ctrlfield')],
                    self::termCount((string) $key),
                    '',
                    [],
                    '',
                    '',
                    __('terms', 'ctrlfield')
                );
            }
            echo '</ul>';
        }
    }

    /**
     * One row of the list.
     *
     * @param array<int, string>    $meta
     * @param array<string, string> $actions label => url
     */
    private function row(string $iconHtml, string $name, string $key, string $editUrl, array $meta, ?int $count, string $warning, array $actions, string $deleteUrl, string $confirm, string $unit = ''): void
    {
        $unit = $unit !== '' ? $unit : __('entries', 'ctrlfield');
        ?>
        <li class="ctrlf-types__row<?php echo $warning !== '' ? ' is-inactive' : ''; ?>">
            <span class="ctrlf-types__icon"><?php echo $iconHtml; // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_attr ?></span>
            <div class="ctrlf-types__main">
                <div class="ctrlf-types__title">
                    <?php if ($editUrl !== ''): ?>
                        <a href="<?php echo esc_url($editUrl); ?>"><?php echo esc_html($name); ?></a>
                    <?php else: ?>
                        <strong><?php echo esc_html($name); ?></strong>
                    <?php endif; ?>
                    <code><?php echo esc_html($key); ?></code>
                </div>
                <div class="ctrlf-types__meta"><?php echo esc_html(implode(' · ', $meta)); ?></div>
                <?php if ($warning !== ''): ?><div class="ctrlf-types__warning"><?php echo esc_html($warning); ?></div><?php endif; ?>
                <?php if ($actions !== [] || $deleteUrl !== ''): ?>
                    <div class="ctrlf-types__actions">
                        <?php foreach ($actions as $label => $url): ?>
                            <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
                        <?php endforeach; ?>
                        <?php if ($deleteUrl !== ''): ?>
                            <a href="<?php echo esc_url($deleteUrl); ?>" class="is-danger" onclick="return confirm(<?php echo esc_attr((string) wp_json_encode($confirm)); ?>)"><?php esc_html_e('Delete', 'ctrlfield'); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="ctrlf-types__count">
                <?php if ($count !== null): ?>
                    <strong><?php echo esc_html(number_format_i18n($count)); ?></strong> <span><?php echo esc_html($unit); ?></span>
                <?php endif; ?>
            </div>
        </li>
        <?php
    }

    private function emptyState(string $title, string $text, string $button, string $tab): void
    {
        ?>
        <div class="ctrlf-types__empty">
            <strong><?php echo esc_html($title); ?></strong>
            <p><?php echo esc_html($text); ?></p>
            <a class="button button-primary" href="<?php echo esc_url($this->url(['action' => 'new'], $tab)); ?>"><?php echo esc_html($button); ?></a>
        </div>
        <?php
    }

    // ── Post type form ───────────────────────────────────────────────────────

    private function renderForm(?PostTypeDefinition $def): void
    {
        $isNew  = $def === null;
        $errors = $this->takeErrors();
        $values = $errors['_input'] ?? ($def?->toArray() ?? (new PostTypeDefinition('', '', ''))->toArray());
        unset($errors['_input']);
        $values = $this->normaliseValues((array) $values, ['supports', 'taxonomies']);

        // Taxonomies that list this post type in their own settings: shown ticked, changed there.
        $fromTaxonomy = [];
        foreach ($this->repo->taxonomies() as $tax) {
            if (! $isNew && in_array($def->slug, $tax->postTypes, true)) {
                $fromTaxonomy[] = $tax->slug;
            }
        }
        $taxonomies = $this->taxonomyNames();
        $slug       = $isNew ? '' : $def->slug;

        $this->formHeader(
            $isNew ? __('Add New Post Type', 'ctrlfield') : sprintf(/* translators: %s: post type name */ __('Edit Post Type: %s', 'ctrlfield'), $def->plural),
            'post_types',
            $errors
        );
        $this->openForm('ctrlfield_save_post_type', $slug, $values, 20, ReservedNames::POST_TYPES, array_values(array_diff(array_keys($this->repo->all()), [$slug])), $isNew);
        ?>
        <div class="ctrlf-typeform__main">
            <?php $this->section(__('Names', 'ctrlfield')); ?>
                <?php $this->text('plural', __('Plural name', 'ctrlfield'), $errors, __('Shown in the admin menu, e.g. "Projects".', 'ctrlfield'), true); ?>
                <?php $this->text('singular', __('Singular name', 'ctrlfield'), $errors, __('e.g. "Project".', 'ctrlfield'), true); ?>
                <?php $this->keyRow($errors, __('Internal name, made from the singular name. Lowercase, max. 20 characters. Changing it later hides the existing entries.', 'ctrlfield')); ?>
                <?php $this->text('description', __('Description', 'ctrlfield'), $errors, __('Optional. Some themes show it on the archive page.', 'ctrlfield'), false); ?>
            <?php $this->endSection(); ?>

            <?php $this->section(__('Admin menu', 'ctrlfield')); ?>
                <div class="ctrlf-typeform__row">
                    <span class="ctrlf-typeform__label"><?php esc_html_e('Icon', 'ctrlfield'); ?></span>
                    <div class="ctrlf-typeform__control">
                        <input type="hidden" name="icon" :value="v.icon">
                        <div class="ctrlf-icon-field">
                            <button type="button" class="ctrlf-icon-chip"
                                    @click="$ctrlfIconPicker(v.icon, (icon) => { v.icon = icon || 'dashicons-admin-post'; }, { libraries: ['dashicons'] }, $el)">
                                <span class="ctrlf-icon-chip__glyph" x-html="$ctrlfIcon(v.icon, 20)"></span>
                                <span class="ctrlf-icon-chip__name" x-text="v.icon.replace(/^dashicons-/, '')"></span>
                            </button>
                        </div>
                        <?php $this->help(__('WordPress menus use Dashicons.', 'ctrlfield'), (string) ($errors['icon'] ?? '')); ?>
                    </div>
                </div>
                <div class="ctrlf-typeform__row">
                    <label class="ctrlf-typeform__label" for="ctrlf-tf-menu_position"><?php esc_html_e('Position', 'ctrlfield'); ?></label>
                    <div class="ctrlf-typeform__control">
                        <select id="ctrlf-tf-menu_position" name="menu_position" class="ctrlf-input" x-model.number="v.menu_position">
                            <?php foreach ($this->positions((int) $values['menu_position']) as $position => $label): ?>
                                <option value="<?php echo esc_attr((string) $position); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php $this->endSection(); ?>

            <?php $this->section(__('Editor', 'ctrlfield'), __('What the edit screen of an entry has.', 'ctrlfield')); ?>
                <div class="ctrlf-typeform__checks">
                    <?php foreach (PostTypeDefinition::SUPPORTS as $feature): ?>
                        <label class="ctrlf-typeform__check">
                            <input type="checkbox" name="supports[]" value="<?php echo esc_attr($feature); ?>" x-model="v.supports">
                            <?php echo esc_html($this->featureLabel($feature)); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php $this->endSection(); ?>

            <?php $this->section(__('Website & URLs', 'ctrlfield')); ?>
                <?php $this->toggle('public', __('Public', 'ctrlfield'), __('Entries have their own page on the website. Off: only editors see them, in the admin.', 'ctrlfield')); ?>
                <div x-show="v.public" x-collapse>
                    <?php $this->text('rewrite_slug', __('URL slug', 'ctrlfield'), $errors, __('The part of the URL before the entry, e.g. "work" for /work/my-project/. Empty: the key.', 'ctrlfield'), false, 'v.slug'); ?>
                    <?php $this->toggle('has_archive', __('Archive page', 'ctrlfield'), __('A page listing every entry.', 'ctrlfield')); ?>
                    <div x-show="v.has_archive" x-collapse>
                        <?php $this->text('archive_slug', __('Archive URL', 'ctrlfield'), $errors, __('Empty: the same as the URL slug.', 'ctrlfield'), false, 'v.rewrite_slug || v.slug'); ?>
                    </div>
                    <?php $this->toggle('exclude_from_search', __('Leave out of search', 'ctrlfield'), __('Entries do not appear in the website search.', 'ctrlfield')); ?>
                </div>
                <?php $this->toggle('hierarchical', __('Hierarchical', 'ctrlfield'), __('Entries can have a parent entry, like Pages.', 'ctrlfield')); ?>
                <?php $this->toggle('show_in_rest', __('Block editor & REST API', 'ctrlfield'), __('Edit entries with the block editor; they are available at /wp-json/wp/v2/.', 'ctrlfield')); ?>
                <div x-show="v.show_in_rest" x-collapse>
                    <?php $this->text('rest_base', __('REST base', 'ctrlfield'), $errors, __('Empty: the key.', 'ctrlfield'), false, 'v.slug'); ?>
                </div>
            <?php $this->endSection(); ?>

            <?php $this->section(__('Taxonomies', 'ctrlfield'), __('Categories and tags the entries can be filed under.', 'ctrlfield')); ?>
                <div class="ctrlf-typeform__checks">
                    <?php foreach ($taxonomies as $tax => $label): ?>
                        <?php if (in_array($tax, $fromTaxonomy, true)): ?>
                            <label class="ctrlf-typeform__check is-locked" title="<?php esc_attr_e('Set in the taxonomy\'s own settings.', 'ctrlfield'); ?>">
                                <input type="checkbox" checked disabled> <?php echo esc_html($label); ?>
                            </label>
                        <?php else: ?>
                            <label class="ctrlf-typeform__check">
                                <input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr($tax); ?>" x-model="v.taxonomies"> <?php echo esc_html($label); ?>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <p class="ctrlf-typeform__note"><a href="<?php echo esc_url($this->url(['action' => 'new'], 'taxonomies')); ?>"><?php esc_html_e('+ New taxonomy', 'ctrlfield'); ?></a></p>
            <?php $this->endSection(); ?>

            <details class="ctrlf-typeform__section ctrlf-typeform__advanced">
                <summary><?php esc_html_e('Advanced', 'ctrlfield'); ?></summary>
                <div class="ctrlf-typeform__row">
                    <span class="ctrlf-typeform__label"><?php esc_html_e('Permissions', 'ctrlfield'); ?></span>
                    <div class="ctrlf-typeform__control">
                        <div class="ctrlf-button-group" role="radiogroup">
                            <?php foreach (['post' => __('Like posts', 'ctrlfield'), 'page' => __('Like pages', 'ctrlfield')] as $cap => $label): ?>
                                <label class="ctrlf-btn-group-item" :class="{ 'is-active': v.capability_type === '<?php echo esc_attr($cap); ?>' }">
                                    <input type="radio" name="capability_type" value="<?php echo esc_attr($cap); ?>" x-model="v.capability_type" class="screen-reader-text"> <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php $this->help(__('Who may edit entries: the roles that may edit posts (Authors and up) or pages (Editors and up).', 'ctrlfield')); ?>
                    </div>
                </div>
            </details>
        </div>

        <aside class="ctrlf-typeform__side">
            <div class="ctrlf-typeform__card">
                <div class="ctrlf-typeform__preview">
                    <span class="ctrlf-typeform__preview-icon" x-html="$ctrlfIcon(v.icon, 22)"></span>
                    <div>
                        <strong x-text="v.plural || <?php echo esc_attr((string) wp_json_encode(__('New post type', 'ctrlfield'))); ?>"></strong>
                        <code x-text="v.slug || '—'"></code>
                    </div>
                </div>
                <dl class="ctrlf-typeform__urls">
                    <div x-show="v.public"><dt><?php esc_html_e('Entry', 'ctrlfield'); ?></dt><dd x-text="entryUrl"></dd></div>
                    <div x-show="v.public && v.has_archive"><dt><?php esc_html_e('Archive', 'ctrlfield'); ?></dt><dd x-text="archiveUrl"></dd></div>
                    <div x-show="!v.public"><dt><?php esc_html_e('Website', 'ctrlfield'); ?></dt><dd><?php esc_html_e('Admin only — no pages', 'ctrlfield'); ?></dd></div>
                    <div x-show="v.show_in_rest"><dt>REST</dt><dd x-text="restUrl"></dd></div>
                </dl>
                <?php $this->sideActions($isNew, $isNew ? '' : $this->url(['action' => 'code', self::ARG => $slug], 'post_types'), $isNew ? '' : $this->deleteUrl('ctrlfield_delete_post_type', self::ARG, $slug), __('Create Post Type', 'ctrlfield'), __('Delete this post type? Its entries stay in the database and come back if you recreate it with the same key.', 'ctrlfield')); ?>
            </div>
        </aside>
        <?php
        echo '</form>';
    }

    // ── Taxonomy form ────────────────────────────────────────────────────────

    private function renderTaxonomyForm(?TaxonomyDefinition $def): void
    {
        $isNew  = $def === null;
        $errors = $this->takeErrors();
        $values = $errors['_input'] ?? ($def?->toArray() ?? (new TaxonomyDefinition('', '', ''))->toArray());
        unset($errors['_input']);
        $values = $this->normaliseValues((array) $values, ['post_types']);
        $slug   = $isNew ? '' : $def->slug;

        $this->formHeader(
            $isNew ? __('Add New Taxonomy', 'ctrlfield') : sprintf(/* translators: %s: taxonomy name */ __('Edit Taxonomy: %s', 'ctrlfield'), $def->plural),
            'taxonomies',
            $errors
        );
        $this->openForm('ctrlfield_save_taxonomy', $slug, $values, 32, ReservedNames::TAXONOMIES, array_values(array_diff(array_keys($this->repo->taxonomies()), [$slug])), $isNew);
        ?>
        <div class="ctrlf-typeform__main">
            <?php $this->section(__('Names', 'ctrlfield')); ?>
                <?php $this->text('plural', __('Plural name', 'ctrlfield'), $errors, __('e.g. "Services".', 'ctrlfield'), true); ?>
                <?php $this->text('singular', __('Singular name', 'ctrlfield'), $errors, __('e.g. "Service".', 'ctrlfield'), true); ?>
                <?php $this->keyRow($errors, __('Internal name, made from the singular name. Lowercase, max. 32 characters. Changing it later hides the existing terms.', 'ctrlfield')); ?>
                <?php $this->text('description', __('Description', 'ctrlfield'), $errors, '', false); ?>
            <?php $this->endSection(); ?>

            <?php $this->section(__('Kind', 'ctrlfield')); ?>
                <input type="hidden" name="hierarchical" :value="v.hierarchical ? '1' : ''">
                <div class="ctrlf-typeform__kinds" role="radiogroup">
                    <button type="button" role="radio" class="ctrlf-typeform__kind" :class="{ 'is-active': v.hierarchical }" :aria-checked="v.hierarchical" @click="v.hierarchical = true">
                        <span class="dashicons dashicons-category" aria-hidden="true"></span>
                        <strong><?php esc_html_e('Like categories', 'ctrlfield'); ?></strong>
                        <span><?php esc_html_e('Terms can have parent terms; editors tick them in a list.', 'ctrlfield'); ?></span>
                    </button>
                    <button type="button" role="radio" class="ctrlf-typeform__kind" :class="{ 'is-active': !v.hierarchical }" :aria-checked="!v.hierarchical" @click="v.hierarchical = false">
                        <span class="dashicons dashicons-tag" aria-hidden="true"></span>
                        <strong><?php esc_html_e('Like tags', 'ctrlfield'); ?></strong>
                        <span><?php esc_html_e('A flat list; editors type the terms.', 'ctrlfield'); ?></span>
                    </button>
                </div>
            <?php $this->endSection(); ?>

            <?php $this->section(__('Used with', 'ctrlfield'), __('The post types whose entries can be filed under it.', 'ctrlfield')); ?>
                <div class="ctrlf-typeform__checks">
                    <?php foreach ($this->postTypeNames() as $type => $label): ?>
                        <label class="ctrlf-typeform__check">
                            <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($type); ?>" x-model="v.post_types"> <?php echo esc_html($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php $this->endSection(); ?>

            <?php $this->section(__('Website & URLs', 'ctrlfield')); ?>
                <?php $this->toggle('public', __('Public', 'ctrlfield'), __('Each term has a page listing its entries.', 'ctrlfield')); ?>
                <div x-show="v.public" x-collapse>
                    <?php $this->text('rewrite_slug', __('URL slug', 'ctrlfield'), $errors, __('e.g. "service" for /service/branding/. Empty: the key.', 'ctrlfield'), false, 'v.slug'); ?>
                </div>
                <?php $this->toggle('show_in_rest', __('Block editor & REST API', 'ctrlfield'), __('Needed for the panel in the block editor\'s sidebar.', 'ctrlfield')); ?>
                <?php $this->toggle('show_admin_column', __('Column in the list', 'ctrlfield'), __('The entries list shows the terms of each entry.', 'ctrlfield')); ?>
            <?php $this->endSection(); ?>
        </div>

        <aside class="ctrlf-typeform__side">
            <div class="ctrlf-typeform__card">
                <div class="ctrlf-typeform__preview">
                    <span class="ctrlf-typeform__preview-icon"><span class="dashicons" :class="v.hierarchical ? 'dashicons-category' : 'dashicons-tag'" aria-hidden="true"></span></span>
                    <div>
                        <strong x-text="v.plural || <?php echo esc_attr((string) wp_json_encode(__('New taxonomy', 'ctrlfield'))); ?>"></strong>
                        <code x-text="v.slug || '—'"></code>
                    </div>
                </div>
                <dl class="ctrlf-typeform__urls">
                    <div x-show="v.public"><dt><?php esc_html_e('Term', 'ctrlfield'); ?></dt><dd x-text="entryUrl"></dd></div>
                    <div><dt><?php esc_html_e('Used with', 'ctrlfield'); ?></dt><dd x-text="v.post_types.length ? v.post_types.join(', ') : '—'"></dd></div>
                </dl>
                <?php $this->sideActions($isNew, $isNew ? '' : $this->url(['action' => 'code', self::TAX_ARG => $slug], 'taxonomies'), $isNew ? '' : $this->deleteUrl('ctrlfield_delete_taxonomy', self::TAX_ARG, $slug), __('Create Taxonomy', 'ctrlfield'), __('Delete this taxonomy? Its terms stay in the database and come back if you recreate it with the same key.', 'ctrlfield')); ?>
            </div>
        </aside>
        <?php
        echo '</form>';
    }

    // ── Form pieces ──────────────────────────────────────────────────────────

    /** @param array<string, mixed> $errors */
    private function formHeader(string $title, string $tab, array $errors): void
    {
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p><a href="' . esc_url($this->url([], $tab)) . '">&larr; ' . esc_html($tab === 'taxonomies' ? __('All taxonomies', 'ctrlfield') : __('All post types', 'ctrlfield')) . '</a></p>';
        if ($errors !== []) {
            echo MessageRenderer::box('error', esc_html__('Please fix the highlighted fields.', 'ctrlfield')); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped
        }
    }

    /**
     * @param array<string, mixed> $values
     * @param array<int, string>   $reserved
     * @param array<int, string>   $taken
     */
    private function openForm(string $action, string $previous, array $values, int $max, array $reserved, array $taken, bool $isNew): void
    {
        $config = [
            'values'   => $values,
            'max'      => $max,
            'reserved' => array_values(array_unique([...$reserved, ...ReservedNames::TERMS])),
            'taken'    => $taken,
            'home'     => untrailingslashit((string) preg_replace('#^https?://#', '', home_url())),
            'isNew'    => $isNew,
            'i18n'     => [
                /* translators: %d: max length */
                'format'   => sprintf(__('Use 1–%d lowercase letters, numbers, "-" or "_", starting with a letter.', 'ctrlfield'), $max),
                'reserved' => __('This key is reserved by WordPress.', 'ctrlfield'),
                'taken'    => __('Another one created here already uses this key.', 'ctrlfield'),
                'sample'   => __('sample', 'ctrlfield'),
            ],
        ];
        printf(
            '<form method="post" action="%s" class="ctrlf-typeform" x-data="ctrlfTypeForm(%s)">',
            esc_url(admin_url('admin-post.php')),
            esc_attr((string) wp_json_encode($config))
        );
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '">';
        echo '<input type="hidden" name="previous_slug" value="' . esc_attr($previous) . '">';
        wp_nonce_field($action);
    }

    private function section(string $title, string $intro = ''): void
    {
        echo '<section class="ctrlf-typeform__section"><h2>' . esc_html($title) . '</h2>';
        if ($intro !== '') {
            echo '<p class="ctrlf-typeform__intro">' . esc_html($intro) . '</p>';
        }
    }

    private function endSection(): void
    {
        echo '</section>';
    }

    /** @param array<string, mixed> $errors */
    private function text(string $name, string $label, array $errors, string $help, bool $required, string $placeholderExpr = ''): void
    {
        $id = 'ctrlf-tf-' . $name;
        ?>
        <div class="ctrlf-typeform__row<?php echo isset($errors[$name]) ? ' has-error' : ''; ?>">
            <label class="ctrlf-typeform__label" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?><?php echo $required ? ' <span class="ctrlf-typeform__req" aria-hidden="true">*</span>' : ''; ?></label>
            <div class="ctrlf-typeform__control">
                <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" class="ctrlf-input" x-model="v.<?php echo esc_attr($name); ?>"
                    <?php echo $placeholderExpr !== '' ? ':placeholder="' . esc_attr($placeholderExpr) . '"' : ''; ?>
                    <?php echo $required ? 'required' : ''; ?> <?php echo isset($errors[$name]) ? 'aria-invalid="true"' : ''; ?>>
                <?php $this->help($help, (string) ($errors[$name] ?? '')); ?>
            </div>
        </div>
        <?php
    }

    /** @param array<string, mixed> $errors */
    private function keyRow(array $errors, string $help): void
    {
        ?>
        <div class="ctrlf-typeform__row<?php echo isset($errors['slug']) ? ' has-error' : ''; ?>" :class="{ 'has-error': keyError }">
            <label class="ctrlf-typeform__label" for="ctrlf-tf-slug"><?php esc_html_e('Key', 'ctrlfield'); ?> <span class="ctrlf-typeform__req" aria-hidden="true">*</span></label>
            <div class="ctrlf-typeform__control">
                <input type="text" id="ctrlf-tf-slug" name="slug" class="ctrlf-input code" x-model="v.slug" @input="editKey()" required spellcheck="false" autocomplete="off">
                <p class="ctrlf-typeform__error" x-show="keyError" x-text="keyError" x-cloak></p>
                <?php if (isset($errors['slug'])): ?>
                    <p class="ctrlf-typeform__error" x-show="!keyError"><?php echo esc_html((string) $errors['slug']); ?></p>
                <?php endif; ?>
                <p class="ctrlf-typeform__help"><?php echo esc_html($help); ?></p>
            </div>
        </div>
        <?php
    }

    private function toggle(string $name, string $label, string $help): void
    {
        ?>
        <label class="ctrlf-typeform__toggle">
            <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" x-model="v.<?php echo esc_attr($name); ?>">
            <span><strong><?php echo esc_html($label); ?></strong><span><?php echo esc_html($help); ?></span></span>
        </label>
        <?php
    }

    private function help(string $help, string $error = ''): void
    {
        if ($error !== '') {
            echo '<p class="ctrlf-typeform__error">' . esc_html($error) . '</p>';
        } elseif ($help !== '') {
            echo '<p class="ctrlf-typeform__help">' . esc_html($help) . '</p>';
        }
    }

    private function sideActions(bool $isNew, string $codeUrl, string $deleteUrl, string $createLabel, string $confirm): void
    {
        ?>
        <div class="ctrlf-typeform__buttons">
            <button type="submit" class="button button-primary button-large"><?php echo esc_html($isNew ? $createLabel : __('Save Changes', 'ctrlfield')); ?></button>
        </div>
        <?php if (! $isNew): ?>
            <div class="ctrlf-typeform__links">
                <a href="<?php echo esc_url($codeUrl); ?>"><?php esc_html_e('Export PHP', 'ctrlfield'); ?></a>
                <a href="<?php echo esc_url($deleteUrl); ?>" class="is-danger" onclick="return confirm(<?php echo esc_attr((string) wp_json_encode($confirm)); ?>)"><?php esc_html_e('Delete', 'ctrlfield'); ?></a>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Form values as the Alpine state needs them: booleans and lists.
     *
     * @param  array<string, mixed> $values
     * @param  array<int, string>   $lists
     * @return array<string, mixed>
     */
    private function normaliseValues(array $values, array $lists): array
    {
        foreach ($lists as $list) {
            $values[$list] = array_values(array_map('strval', (array) ($values[$list] ?? [])));
        }
        foreach (self::FLAGS as $flag) {
            $values[$flag] = in_array($values[$flag] ?? false, [true, 1, '1', 'on', 'yes', 'true'], true);
        }
        $values['icon']            = (string) ($values['icon'] ?? '') !== '' ? (string) $values['icon'] : 'dashicons-admin-post';
        $values['menu_position']   = (int) ($values['menu_position'] ?? 25);
        $values['capability_type'] = (string) ($values['capability_type'] ?? 'post');
        foreach (['slug', 'singular', 'plural', 'description', 'rewrite_slug', 'archive_slug', 'rest_base'] as $text) {
            $values[$text] = (string) ($values[$text] ?? '');
        }

        return $values;
    }

    /** @return array<int, string> position => label */
    private function positions(int $current): array
    {
        $labels = [
            5   => __('Below Posts', 'ctrlfield'),
            10  => __('Below Media', 'ctrlfield'),
            20  => __('Below Pages', 'ctrlfield'),
            25  => __('Below Comments', 'ctrlfield'),
            60  => __('Below Appearance', 'ctrlfield'),
            80  => __('Below Settings', 'ctrlfield'),
            100 => __('At the end', 'ctrlfield'),
        ];
        if (! isset($labels[$current])) {
            /* translators: %d: menu position */
            $labels[$current] = sprintf(__('Position %d', 'ctrlfield'), $current);
            ksort($labels);
        }

        return $labels;
    }

    // ── Code view ────────────────────────────────────────────────────────────

    private function renderCode(string $name, string $code, string $tab): void
    {
        ?>
        <h1><?php echo esc_html(sprintf(/* translators: %s: post type or taxonomy name */ __('Export PHP: %s', 'ctrlfield'), $name)); ?></h1>
        <p><a href="<?php echo esc_url($this->url([], $tab)); ?>">&larr; <?php echo esc_html($tab === 'taxonomies' ? __('All taxonomies', 'ctrlfield') : __('All post types', 'ctrlfield')); ?></a></p>
        <p class="description"><?php esc_html_e('Save this as a .php file in your CtrlField schema folder to version it in git, then delete it here — code takes precedence.', 'ctrlfield'); ?></p>
        <div class="ctrlf-php-export" x-data="{ copied: false }">
            <div class="ctrlf-php-export__bar">
                <strong>PHP</strong>
                <button type="button" class="button button-small" @click="navigator.clipboard.writeText($root.querySelector('code').textContent); copied = true; setTimeout(() => copied = false, 1500)">
                    <span x-text="copied ? <?php echo esc_attr((string) wp_json_encode(__('Copied', 'ctrlfield'))); ?> : <?php echo esc_attr((string) wp_json_encode(__('Copy', 'ctrlfield'))); ?>"><?php esc_html_e('Copy', 'ctrlfield'); ?></span>
                </button>
            </div>
            <pre><code><?php echo esc_html($code); ?></code></pre>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Handlers (admin-post.php)
    // -------------------------------------------------------------------------

    public function handleSave(): void
    {
        $this->guard('ctrlfield_save_post_type');

        $input        = (array) wp_unslash($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by guard()
        $previousSlug = sanitize_key((string) ($input['previous_slug'] ?? ''));
        [$def, $errors] = PostTypeDefinition::fromInput($input);

        if ($def !== null) {
            $isRename = $previousSlug !== '' && $previousSlug !== $def->slug;
            if (($previousSlug === '' || $isRename) && $this->repo->get($def->slug) !== null) {
                $errors['slug'] = __('Another post type created here already uses this key.', 'ctrlfield');
            } elseif (($previousSlug === '' || $isRename) && post_type_exists($def->slug)) {
                $errors['slug'] = __('This key is already used by WordPress, a plugin or your theme code.', 'ctrlfield');
            }
        }

        if ($def === null || $errors !== []) {
            $this->failed($errors, $input, $previousSlug !== '' ? ['action' => 'edit', self::ARG => $previousSlug] : ['action' => 'new'], 'post_types');
        }

        $this->repo->save($def, $previousSlug !== '' ? $previousSlug : null);
        wp_safe_redirect($this->url(['notice' => $previousSlug === '' ? 'created' : 'saved', self::ARG => $def->slug], 'post_types'));
        exit;
    }

    public function handleDelete(): void
    {
        $slug = isset($_GET[self::ARG]) ? sanitize_key(wp_unslash((string) $_GET[self::ARG])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked by guard()
        $this->guard('ctrlfield_delete_post_type_' . $slug);

        $this->repo->delete($slug);
        wp_safe_redirect($this->url(['notice' => 'deleted'], 'post_types'));
        exit;
    }

    public function handleSaveTaxonomy(): void
    {
        $this->guard('ctrlfield_save_taxonomy');

        $input        = (array) wp_unslash($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by guard()
        $previousSlug = sanitize_key((string) ($input['previous_slug'] ?? ''));
        [$def, $errors] = TaxonomyDefinition::fromInput($input);

        if ($def !== null) {
            $isRename = $previousSlug !== '' && $previousSlug !== $def->slug;
            if (($previousSlug === '' || $isRename) && $this->repo->taxonomy($def->slug) !== null) {
                $errors['slug'] = __('Another taxonomy created here already uses this key.', 'ctrlfield');
            } elseif (($previousSlug === '' || $isRename) && taxonomy_exists($def->slug)) {
                $errors['slug'] = __('This key is already used by WordPress, a plugin or your theme code.', 'ctrlfield');
            }
        }

        if ($def === null || $errors !== []) {
            $this->failed($errors, $input, $previousSlug !== '' ? ['action' => 'edit', self::TAX_ARG => $previousSlug] : ['action' => 'new'], 'taxonomies');
        }

        $this->repo->saveTaxonomy($def, $previousSlug !== '' ? $previousSlug : null);
        wp_safe_redirect($this->url(['notice' => $previousSlug === '' ? 'tax_created' : 'tax_saved'], 'taxonomies'));
        exit;
    }

    public function handleDeleteTaxonomy(): void
    {
        $slug = isset($_GET[self::TAX_ARG]) ? sanitize_key(wp_unslash((string) $_GET[self::TAX_ARG])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked by guard()
        $this->guard('ctrlfield_delete_taxonomy_' . $slug);

        $this->repo->deleteTaxonomy($slug);
        wp_safe_redirect($this->url(['notice' => 'tax_deleted'], 'taxonomies'));
        exit;
    }

    private function guard(string $nonce): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to do this.', 'ctrlfield'), 403);
        }
        check_admin_referer($nonce);
    }

    /**
     * Back to the form with the errors and what was typed (never the nonce).
     *
     * @param array<string, string> $errors
     * @param array<string, mixed>  $input
     * @param array<string, string> $args
     */
    private function failed(array $errors, array $input, array $args, string $tab): never
    {
        unset($input['_wpnonce'], $input['_wp_http_referer'], $input['action'], $input['previous_slug']);
        $errors['_input'] = $input;
        set_transient(self::ERRORS_TX . get_current_user_id(), $errors, 300);
        wp_safe_redirect($this->url($args, $tab));
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param array<string, string> $args */
    private function url(array $args = [], string $tab = 'post_types'): string
    {
        return add_query_arg(array_merge(['page' => self::SLUG], $tab === 'taxonomies' ? ['tab' => 'taxonomies'] : [], $args), admin_url('admin.php'));
    }

    private function deleteUrl(string $action, string $arg, string $slug): string
    {
        return wp_nonce_url(
            add_query_arg(['action' => $action, $arg => $slug], admin_url('admin-post.php')),
            $action . '_' . $slug
        );
    }

    /**
     * Taxonomies a post type can use: core's, this page's, and others with an
     * admin screen (not menus, link categories, formats or block themes').
     *
     * @return array<string, string> key => plural name
     */
    private function taxonomyNames(): array
    {
        $out = [];
        foreach (get_taxonomies(['show_ui' => true], 'objects') as $key => $object) {
            if (! in_array($key, ['nav_menu', 'link_category', 'post_format', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category'], true)) {
                $out[(string) $key] = (string) $object->labels->name;
            }
        }
        foreach ($this->repo->taxonomies() as $def) {
            $out[$def->slug] ??= $def->plural;
        }

        return $out;
    }

    /**
     * Post types a taxonomy can be used with (those with an admin screen).
     *
     * @return array<string, string> key => plural name
     */
    private function postTypeNames(): array
    {
        $out = [];
        foreach (get_post_types(['show_ui' => true], 'objects') as $key => $object) {
            if (! in_array($key, ['wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'ctrlf_block'], true)) {
                $out[(string) $key] = (string) $object->labels->name;
            }
        }
        foreach ($this->repo->all() as $def) {
            $out[$def->slug] ??= $def->plural;
        }

        return $out;
    }

    private static function termCount(string $taxonomy): int
    {
        $count = wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);

        return is_numeric($count) ? (int) $count : 0;
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
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- display of an action's result
        $notice = isset($_GET['notice']) ? sanitize_key(wp_unslash((string) $_GET['notice'])) : '';
        $slug   = isset($_GET[self::ARG]) ? sanitize_key(wp_unslash((string) $_GET[self::ARG])) : '';
        // phpcs:enable
        $messages = [
            'created'     => __('Post type created. It is now in the admin menu.', 'ctrlfield'),
            'saved'       => __('Post type saved.', 'ctrlfield'),
            'deleted'     => __('Post type deleted. Its entries are still in the database.', 'ctrlfield'),
            'tax_created' => __('Taxonomy created.', 'ctrlfield'),
            'tax_saved'   => __('Taxonomy saved.', 'ctrlfield'),
            'tax_deleted' => __('Taxonomy deleted. Its terms are still in the database.', 'ctrlfield'),
        ];
        if (! isset($messages[$notice])) {
            return;
        }

        $html = esc_html($messages[$notice]);
        $def  = $notice === 'created' && $slug !== '' ? $this->repo->get($slug) : null;
        if ($def !== null) {
            $html .= sprintf(
                ' <a href="%s">%s</a>',
                esc_url(admin_url('post-new.php?post_type=' . $def->slug)),
                /* translators: %s: post type singular name */
                esc_html(sprintf(__('Add the first %s', 'ctrlfield'), $def->singular))
            );
        }
        echo MessageRenderer::dismissible('success', $html); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped parts
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
