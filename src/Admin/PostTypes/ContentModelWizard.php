<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

use CtrlField\Admin\FieldGroups\FieldGroupRepository;
use CtrlField\Admin\FieldGroups\FieldGroupsPage;
use CtrlField\Admin\MetaBox\MetaBoxServiceProvider;
use CtrlField\Fields\Renderers\MessageRenderer;
use CtrlField\Registry\FieldRegistry;

/**
 * CtrlField → Visual Builder: a new content model in four steps — the post
 * type, its taxonomies, a field group, a review — saved in one go through
 * the Post Types and Field Groups pages' own storage, then the field group
 * opens in its editor to add the fields.
 *
 * Excluded from PHPStan — WP admin functions.
 */
final class ContentModelWizard
{
    public const SLUG       = 'ctrlfield-new-model';
    private const CAP       = 'manage_options';
    private const ERRORS_TX = 'ctrlfield_model_errors_';
    private const ACTION    = 'ctrlfield_create_content_model';

    private string $hook = '';

    public function __construct(
        private readonly PostTypeRepository $types,
        private readonly FieldGroupRepository $groups,
    ) {}

    public function registerMenu(): void
    {
        $hook       = add_submenu_page(
            'ctrlfield',
            __('New Content Model', 'ctrlfield'),
            __('Visual Builder', 'ctrlfield'),
            self::CAP,
            self::SLUG,
            [$this, 'render'],
        );
        $this->hook = is_string($hook) ? $hook : '';
    }

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

    public function render(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'ctrlfield'));
        }

        $tx     = self::ERRORS_TX . get_current_user_id();
        $saved  = get_transient($tx);
        delete_transient($tx);
        $errors = is_array($saved) ? (array) ($saved['errors'] ?? []) : [];
        $input  = is_array($saved) ? (array) ($saved['input'] ?? []) : [];

        $existing = [];
        foreach (get_taxonomies(['show_ui' => true], 'objects') as $key => $object) {
            if (! in_array($key, ['nav_menu', 'link_category', 'post_format', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category'], true)) {
                $existing[(string) $key] = (string) $object->labels->name;
            }
        }

        $type   = (array) ($input['type'] ?? []);
        $config = [
            'type'   => [
                'plural'      => (string) ($type['plural'] ?? ''),
                'singular'    => (string) ($type['singular'] ?? ''),
                'slug'        => (string) ($type['slug'] ?? ''),
                'icon'        => (string) ($type['icon'] ?? '') !== '' ? (string) $type['icon'] : 'dashicons-admin-post',
                'public'      => $input === [] || ! empty($type['public']),
                'has_archive' => $input === [] || ! empty($type['has_archive']),
                'supports'    => array_values(array_map('strval', (array) ($type['supports'] ?? ['title', 'editor', 'thumbnail']))),
            ],
            'link'   => array_values(array_map('strval', (array) ($input['link'] ?? []))),
            'newTax' => array_values(array_map(static fn ($row): array => [
                'plural'       => (string) ($row['plural'] ?? ''),
                'singular'     => (string) ($row['singular'] ?? ''),
                'slug'         => (string) ($row['slug'] ?? ''),
                'hierarchical' => ! empty($row['hierarchical']),
            ], array_filter((array) ($input['new'] ?? []), 'is_array'))),
            'fields' => [
                'create' => $input === [] || ! empty($input['fields']['create']),
                'title'  => (string) ($input['fields']['title'] ?? ''),
            ],
            'step'   => self::firstErrorStep($errors),
            'reserved' => [
                'types' => array_values(array_unique([...ReservedNames::POST_TYPES, ...ReservedNames::TERMS])),
                'taxes' => array_values(array_unique([...ReservedNames::TAXONOMIES, ...ReservedNames::TERMS])),
            ],
            'taken'  => [
                'types'  => array_values(array_unique([...array_keys($this->types->all()), ...array_values(get_post_types())])),
                'taxes'  => array_values(array_unique([...array_keys($this->types->taxonomies()), ...array_values(get_taxonomies())])),
                'groups' => array_values(array_unique([...array_keys($this->groups->all()), ...array_keys(FieldRegistry::all())])),
            ],
            'home'   => untrailingslashit((string) preg_replace('#^https?://#', '', home_url())),
            'i18n'   => [
                'format'   => __('Use lowercase letters, numbers, "-" or "_", starting with a letter.', 'ctrlfield'),
                'reserved' => __('This key is reserved by WordPress.', 'ctrlfield'),
                'taken'    => __('This key is already in use.', 'ctrlfield'),
                'sample'   => __('sample', 'ctrlfield'),
            ],
        ];
        $steps = [__('Content', 'ctrlfield'), __('Taxonomies', 'ctrlfield'), __('Fields', 'ctrlfield'), __('Review', 'ctrlfield')];
        ?>
        <div class="wrap ctrlf-types-page ctrlf-wizard">
            <h1><?php esc_html_e('New Content Model', 'ctrlfield'); ?></h1>
            <p class="ctrlf-types__lead"><?php esc_html_e('A post type, its categories or tags, and a field group for it — set up together. Every part can be changed later in Post Types and Field Groups.', 'ctrlfield'); ?></p>
            <?php
            if ($errors !== []) {
                $list = '<ul>' . implode('', array_map(static fn ($e): string => '<li>' . esc_html((string) $e) . '</li>', $errors)) . '</ul>';
                echo MessageRenderer::box('error', '<p>' . esc_html__('Nothing was created yet:', 'ctrlfield') . '</p>' . $list); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped parts
            }
            ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" x-data="ctrlfModelWizard(<?php echo esc_attr(\CtrlField\Admin\Js::json($config)); ?>)" @submit="if (!canCreate()) $event.preventDefault()">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <?php wp_nonce_field(self::ACTION); ?>

                <ol class="ctrlf-wizard__steps">
                    <?php foreach ($steps as $i => $label): ?>
                        <li :class="{ 'is-current': step === <?php echo (int) $i + 1; ?>, 'is-done': step > <?php echo (int) $i + 1; ?> }">
                            <button type="button" @click="goTo(<?php echo (int) $i + 1; ?>)" :disabled="!canReach(<?php echo (int) $i + 1; ?>)">
                                <span class="ctrlf-wizard__num"><?php echo (int) $i + 1; ?></span> <?php echo esc_html($label); ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <?php // ── 1 Content ───────────────────────────────────────── ?>
                <section class="ctrlf-typeform__section" x-show="step === 1">
                    <h2><?php esc_html_e('What kind of content?', 'ctrlfield'); ?></h2>
                    <div class="ctrlf-typeform__row">
                        <label class="ctrlf-typeform__label" for="ctrlf-wz-plural"><?php esc_html_e('Plural name', 'ctrlfield'); ?> <span class="ctrlf-typeform__req">*</span></label>
                        <div class="ctrlf-typeform__control">
                            <input id="ctrlf-wz-plural" type="text" class="ctrlf-input" name="type[plural]" x-model="type.plural" placeholder="<?php esc_attr_e('Projects', 'ctrlfield'); ?>">
                        </div>
                    </div>
                    <div class="ctrlf-typeform__row">
                        <label class="ctrlf-typeform__label" for="ctrlf-wz-singular"><?php esc_html_e('Singular name', 'ctrlfield'); ?> <span class="ctrlf-typeform__req">*</span></label>
                        <div class="ctrlf-typeform__control">
                            <input id="ctrlf-wz-singular" type="text" class="ctrlf-input" name="type[singular]" x-model="type.singular" placeholder="<?php esc_attr_e('Project', 'ctrlfield'); ?>">
                        </div>
                    </div>
                    <div class="ctrlf-typeform__row" :class="{ 'has-error': typeKeyError }">
                        <label class="ctrlf-typeform__label" for="ctrlf-wz-slug"><?php esc_html_e('Key', 'ctrlfield'); ?> <span class="ctrlf-typeform__req">*</span></label>
                        <div class="ctrlf-typeform__control">
                            <input id="ctrlf-wz-slug" type="text" class="ctrlf-input code" name="type[slug]" x-model="type.slug" @input="typeKeyEdited = true" spellcheck="false" autocomplete="off">
                            <p class="ctrlf-typeform__error" x-show="typeKeyError" x-text="typeKeyError" x-cloak></p>
                            <p class="ctrlf-typeform__help"><?php esc_html_e('Made from the singular name. Lowercase, max. 20 characters.', 'ctrlfield'); ?></p>
                        </div>
                    </div>
                    <div class="ctrlf-typeform__row">
                        <span class="ctrlf-typeform__label"><?php esc_html_e('Icon', 'ctrlfield'); ?></span>
                        <div class="ctrlf-typeform__control">
                            <input type="hidden" name="type[icon]" :value="type.icon">
                            <div class="ctrlf-icon-field">
                                <button type="button" class="ctrlf-icon-chip" @click="$ctrlfIconPicker(type.icon, (icon) => { type.icon = icon || 'dashicons-admin-post'; }, { libraries: ['dashicons'] }, $el)">
                                    <span class="ctrlf-icon-chip__glyph" x-html="$ctrlfIcon(type.icon, 20)"></span>
                                    <span class="ctrlf-icon-chip__name" x-text="type.icon.replace(/^dashicons-/, '')"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <label class="ctrlf-typeform__toggle">
                        <input type="checkbox" name="type[public]" value="1" x-model="type.public">
                        <span><strong><?php esc_html_e('Public', 'ctrlfield'); ?></strong><span><?php esc_html_e('Entries have their own page on the website.', 'ctrlfield'); ?></span></span>
                    </label>
                    <label class="ctrlf-typeform__toggle" x-show="type.public">
                        <input type="checkbox" name="type[has_archive]" value="1" x-model="type.has_archive">
                        <span><strong><?php esc_html_e('Archive page', 'ctrlfield'); ?></strong><span x-text="'/' + (type.slug || '…') + '/'"></span></span>
                    </label>
                    <div class="ctrlf-typeform__checks">
                        <?php foreach (['title' => __('Title', 'ctrlfield'), 'editor' => __('Content editor', 'ctrlfield'), 'thumbnail' => __('Featured image', 'ctrlfield'), 'excerpt' => __('Excerpt', 'ctrlfield'), 'revisions' => __('Revisions', 'ctrlfield'), 'page-attributes' => __('Order & parent', 'ctrlfield')] as $feature => $label): ?>
                            <label class="ctrlf-typeform__check"><input type="checkbox" name="type[supports][]" value="<?php echo esc_attr($feature); ?>" x-model="type.supports"> <?php echo esc_html($label); ?></label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php // ── 2 Taxonomies ────────────────────────────────────── ?>
                <section class="ctrlf-typeform__section" x-show="step === 2" x-cloak>
                    <h2><?php esc_html_e('Categories and tags', 'ctrlfield'); ?></h2>
                    <p class="ctrlf-typeform__intro"><?php esc_html_e('Optional. Create new ones for this content, or use existing ones.', 'ctrlfield'); ?></p>
                    <template x-for="(tax, i) in newTax" :key="i">
                        <div class="ctrlf-wizard__tax">
                            <input type="text" class="ctrlf-input" :name="`new[${i}][plural]`" x-model="tax.plural" placeholder="<?php esc_attr_e('Plural, e.g. Services', 'ctrlfield'); ?>" aria-label="<?php esc_attr_e('Plural name', 'ctrlfield'); ?>">
                            <input type="text" class="ctrlf-input" :name="`new[${i}][singular]`" x-model="tax.singular" @input="taxSingular(tax)" placeholder="<?php esc_attr_e('Singular, e.g. Service', 'ctrlfield'); ?>" aria-label="<?php esc_attr_e('Singular name', 'ctrlfield'); ?>">
                            <input type="text" class="ctrlf-input code" :name="`new[${i}][slug]`" x-model="tax.slug" @input="tax.keyEdited = true" placeholder="<?php esc_attr_e('key', 'ctrlfield'); ?>" aria-label="<?php esc_attr_e('Key', 'ctrlfield'); ?>" spellcheck="false">
                            <div class="ctrlf-button-group" role="radiogroup">
                                <input type="hidden" :name="`new[${i}][hierarchical]`" :value="tax.hierarchical ? '1' : ''">
                                <button type="button" class="ctrlf-btn-group-item" :class="{ 'is-active': tax.hierarchical }" @click="tax.hierarchical = true"><?php esc_html_e('Categories', 'ctrlfield'); ?></button>
                                <button type="button" class="ctrlf-btn-group-item" :class="{ 'is-active': !tax.hierarchical }" @click="tax.hierarchical = false"><?php esc_html_e('Tags', 'ctrlfield'); ?></button>
                            </div>
                            <button type="button" class="ctrlf-wizard__remove" @click="newTax.splice(i, 1)" aria-label="<?php esc_attr_e('Remove', 'ctrlfield'); ?>">&times;</button>
                            <p class="ctrlf-typeform__error" x-show="taxKeyError(tax, i)" x-text="taxKeyError(tax, i)"></p>
                        </div>
                    </template>
                    <p class="ctrlf-typeform__note ctrlf-wizard__add"><button type="button" class="button" @click="newTax.push({ plural: '', singular: '', slug: '', hierarchical: true, keyEdited: false })"><?php esc_html_e('+ New taxonomy', 'ctrlfield'); ?></button></p>
                    <?php if ($existing !== []): ?>
                        <h3 class="ctrlf-wizard__subhead"><?php esc_html_e('Existing', 'ctrlfield'); ?></h3>
                        <div class="ctrlf-typeform__checks">
                            <?php foreach ($existing as $key => $label): ?>
                                <label class="ctrlf-typeform__check"><input type="checkbox" name="link[]" value="<?php echo esc_attr($key); ?>" x-model="link"> <?php echo esc_html($label); ?></label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <?php // ── 3 Fields ────────────────────────────────────────── ?>
                <section class="ctrlf-typeform__section" x-show="step === 3" x-cloak>
                    <h2><?php esc_html_e('Fields', 'ctrlfield'); ?></h2>
                    <label class="ctrlf-typeform__toggle">
                        <input type="checkbox" name="fields[create]" value="1" x-model="fields.create">
                        <span><strong><?php esc_html_e('Create a field group for it', 'ctrlfield'); ?></strong><span><?php esc_html_e('Shown on every entry of this post type. It opens in the field group editor right after, to add the fields.', 'ctrlfield'); ?></span></span>
                    </label>
                    <div class="ctrlf-typeform__row" x-show="fields.create">
                        <label class="ctrlf-typeform__label" for="ctrlf-wz-gtitle"><?php esc_html_e('Group title', 'ctrlfield'); ?></label>
                        <div class="ctrlf-typeform__control">
                            <input id="ctrlf-wz-gtitle" type="text" class="ctrlf-input" name="fields[title]" x-model="fields.title" :placeholder="groupTitle()">
                            <p class="ctrlf-typeform__help"><?php esc_html_e('Key:', 'ctrlfield'); ?> <code x-text="groupKey()"></code></p>
                            <p class="ctrlf-typeform__error" x-show="groupError()" x-text="groupError()"></p>
                        </div>
                    </div>
                </section>

                <?php // ── 4 Review ────────────────────────────────────────── ?>
                <section class="ctrlf-typeform__section" x-show="step === 4" x-cloak>
                    <h2><?php esc_html_e('Review', 'ctrlfield'); ?></h2>
                    <ul class="ctrlf-wizard__review">
                        <li>
                            <span class="ctrlf-types__icon" x-html="$ctrlfIcon(type.icon, 22)"></span>
                            <div>
                                <strong x-text="type.plural"></strong> <code x-text="type.slug"></code>
                                <div class="ctrlf-types__meta" x-text="type.public ? (entryUrl() + (type.has_archive ? ' · <?php echo esc_js(__('archive', 'ctrlfield')); ?>' : '')) : '<?php echo esc_js(__('admin only', 'ctrlfield')); ?>'"></div>
                            </div>
                        </li>
                        <template x-for="tax in newTax.filter((t) => t.plural)" :key="tax.slug">
                            <li>
                                <span class="ctrlf-types__icon"><span class="dashicons" :class="tax.hierarchical ? 'dashicons-category' : 'dashicons-tag'"></span></span>
                                <div><strong x-text="tax.plural"></strong> <code x-text="tax.slug"></code><div class="ctrlf-types__meta" x-text="tax.hierarchical ? '<?php echo esc_js(__('new taxonomy, like categories', 'ctrlfield')); ?>' : '<?php echo esc_js(__('new taxonomy, like tags', 'ctrlfield')); ?>'"></div></div>
                            </li>
                        </template>
                        <li x-show="link.length">
                            <span class="ctrlf-types__icon"><span class="dashicons dashicons-admin-links"></span></span>
                            <div><strong><?php esc_html_e('Uses', 'ctrlfield'); ?></strong><div class="ctrlf-types__meta" x-text="link.join(', ')"></div></div>
                        </li>
                        <li x-show="fields.create">
                            <span class="ctrlf-types__icon"><span class="dashicons dashicons-feedback"></span></span>
                            <div><strong x-text="fields.title || groupTitle()"></strong> <code x-text="groupKey()"></code><div class="ctrlf-types__meta"><?php esc_html_e('field group — you add the fields next', 'ctrlfield'); ?></div></div>
                        </li>
                    </ul>
                </section>

                <div class="ctrlf-wizard__nav">
                    <button type="button" class="button" x-show="step > 1" @click="step--"><?php esc_html_e('Back', 'ctrlfield'); ?></button>
                    <span class="ctrlf-wizard__hint" x-show="step === 1 && !stepOk(1)"><?php esc_html_e('Fill in the names and a valid key to continue.', 'ctrlfield'); ?></span>
                    <button type="button" class="button button-primary" x-show="step < 4" :disabled="!stepOk(step)" @click="step++"><?php esc_html_e('Next', 'ctrlfield'); ?></button>
                    <button type="submit" class="button button-primary" x-show="step === 4" :disabled="!canCreate()"><?php esc_html_e('Create content model', 'ctrlfield'); ?></button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * The step to reopen at: where the first error is.
     *
     * @param array<string, mixed> $errors
     */
    private static function firstErrorStep(array $errors): int
    {
        $keys = array_keys($errors);
        foreach ($keys as $key) {
            if (str_starts_with((string) $key, 'type.')) {
                return 1;
            }
        }
        foreach ($keys as $key) {
            if (str_starts_with((string) $key, 'tax.')) {
                return 2;
            }
        }

        return $errors !== [] ? 3 : 1;
    }

    public function handleCreate(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to do this.', 'ctrlfield'), 403);
        }
        check_admin_referer(self::ACTION);

        $input = (array) wp_unslash($_POST);
        unset($input['_wpnonce'], $input['_wp_http_referer'], $input['action']);

        [$model, $errors] = ContentModel::fromInput($input, [
            'post_types' => array_values(array_unique([...array_keys($this->types->all()), ...array_values(get_post_types())])),
            'taxonomies' => array_values(array_unique([...array_keys($this->types->taxonomies()), ...array_values(get_taxonomies())])),
            'groups'     => array_values(array_unique([...array_keys($this->groups->all()), ...array_keys(FieldRegistry::all())])),
        ]);

        if ($model === null) {
            set_transient(self::ERRORS_TX . get_current_user_id(), ['errors' => $errors, 'input' => $input], 300);
            wp_safe_redirect(add_query_arg(['page' => self::SLUG], admin_url('admin.php')));
            exit;
        }

        $this->types->save($model->postType);
        foreach ($model->taxonomies as $taxonomy) {
            $this->types->saveTaxonomy($taxonomy);
        }
        if ($model->group !== null) {
            $this->groups->save($model->group);
            wp_safe_redirect(add_query_arg(['page' => FieldGroupsPage::SLUG, 'action' => 'edit', 'group' => (string) $model->group['key'], 'model' => $model->postType->slug], admin_url('admin.php')));
            exit;
        }
        wp_safe_redirect(add_query_arg(['page' => PostTypesPage::SLUG, 'notice' => 'created', 'cpt' => $model->postType->slug], admin_url('admin.php')));
        exit;
    }
}
