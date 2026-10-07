<?php

declare(strict_types=1);

namespace CtrlField\Admin\FieldGroups;

use CtrlField\Builder\OptionsPage;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use CtrlField\Schema\JsonGroup;

/**
 * CtrlField → Field Groups: create and edit field groups without code.
 *
 * Groups are saved as JSON (see FieldGroupRepository). Groups defined in PHP
 * are listed read-only. Every save is validated by JsonGroup::normalize().
 */
final class FieldGroupsPage
{
    public const SLUG       = 'ctrlfield-field-groups';
    private const CAP       = 'manage_options';
    private const ERRORS_TX = 'ctrlfield_field_group_errors_';
    private const ARG       = 'group';

    private string $hook = '';

    public function __construct(private readonly FieldGroupRepository $repo) {}

    public function registerMenu(): void
    {
        $hook = add_submenu_page(
            'ctrlfield',
            __('Field Groups', 'ctrlfield'),
            __('Field Groups', 'ctrlfield'),
            self::CAP,
            self::SLUG,
            [$this, 'render'],
            1,
        );
        $this->hook = is_string($hook) ? $hook : '';
    }

    public function enqueueAssets(string $hook): void
    {
        if ($this->hook === '' || $hook !== $this->hook) {
            return;
        }

        wp_enqueue_style('ctrlfield-admin', CTRLFIELD_URL . 'assets/admin/ctrlfield.css', [], CTRLFIELD_VERSION);
        wp_enqueue_script('ctrlfield-admin', CTRLFIELD_URL . 'assets/admin/ctrlfield.js', [], CTRLFIELD_VERSION, true);
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
        $key    = isset($_GET[self::ARG]) ? sanitize_key((string) $_GET[self::ARG]) : '';

        echo '<div class="wrap">';

        if ($action === 'new') {
            $this->renderForm(null);
        } elseif ($action === 'edit' && ($group = $this->repo->get($key)) !== null) {
            $this->renderForm($group);
        } elseif ($action === 'code' && ($group = $this->repo->get($key)) !== null) {
            $this->renderCode($group);
        } else {
            $this->renderList();
        }

        echo '</div>';
    }

    private function renderList(): void
    {
        $groups   = $this->repo->all();
        $shadowed = FieldGroupsServiceProvider::shadowed();
        $fromJson = FieldGroupsServiceProvider::registered();
        $inCode   = array_filter(
            FieldRegistry::all(),
            static fn ($g, $k) => ! in_array($k, $fromJson, true) && ! $g->isBlock(),
            ARRAY_FILTER_USE_BOTH
        );
        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e('Field Groups', 'ctrlfield'); ?></h1>
        <a href="<?php echo esc_url($this->url(['action' => 'new'])); ?>" class="page-title-action"><?php esc_html_e('Add New Field Group', 'ctrlfield'); ?></a>
        <?php if (has_action('admin_post_ctrlfield_acf_import')): // Pro importer is running ?>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'ctrlfield-acf-import'], admin_url('admin.php'))); ?>" class="page-title-action"><?php esc_html_e('Import from ACF', 'ctrlfield'); ?></a>
        <?php elseif ($this->hasAcfGroups()): ?>
            <?php // Pro code present: its license page; Free download: the product page.
            $proUrl = defined('CTRLFIELD_PRO_VERSION')
                ? add_query_arg(['page' => 'ctrlfield-pro-license'], admin_url('admin.php'))
                : 'https://ctrlcheese.de/ctrlfield'; ?>
            <a href="<?php echo esc_url($proUrl); ?>" class="page-title-action" title="<?php esc_attr_e('Importing ACF field groups and their values is a CtrlField Pro feature.', 'ctrlfield'); ?>"><?php esc_html_e('Import from ACF (Pro)', 'ctrlfield'); ?></a>
        <?php endif; ?>
        <hr class="wp-header-end">
        <p class="description">
            <?php if ($this->repo->canWriteFiles()): ?>
                <?php printf(
                    /* translators: %s: folder path */
                    esc_html__('Groups are saved as JSON files in %s, so they can be committed to git and deployed with the theme.', 'ctrlfield'),
                    '<code>' . esc_html($this->relativePath($this->repo->directory())) . '</code>'
                ); ?>
            <?php else: ?>
                <?php esc_html_e('The JSON folder cannot be written on this server, so groups are saved in the database.', 'ctrlfield'); ?>
            <?php endif; ?>
        </p>
        <?php $this->renderNotice(); ?>

        <table class="wp-list-table widefat fixed striped" style="margin-top:16px">
            <thead>
                <tr>
                    <th style="width:35%"><?php esc_html_e('Title', 'ctrlfield'); ?></th>
                    <th><?php esc_html_e('Key', 'ctrlfield'); ?></th>
                    <th><?php esc_html_e('Location', 'ctrlfield'); ?></th>
                    <th style="width:80px"><?php esc_html_e('Fields', 'ctrlfield'); ?></th>
                    <th><?php esc_html_e('Saved in', 'ctrlfield'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($groups === []): ?>
                <tr><td colspan="5"><?php esc_html_e('No field groups yet. Click "Add New Field Group" to create your first one.', 'ctrlfield'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($groups as $key => $group):
                $editUrl = $this->url(['action' => 'edit', self::ARG => $key]);
                ?>
                <tr>
                    <td>
                        <strong><a class="row-title" href="<?php echo esc_url($editUrl); ?>"><?php echo esc_html($group['title'] !== '' ? $group['title'] : $key); ?></a></strong>
                        <?php if (! $group['active']): ?>
                            — <span class="post-state"><?php esc_html_e('Inactive', 'ctrlfield'); ?></span>
                        <?php endif; ?>
                        <?php if ($group['_errors'] !== []): ?>
                            <p class="description" style="color:#b32d2e"><?php echo esc_html(__('Not loaded:', 'ctrlfield') . ' ' . implode(' ', $group['_errors'])); ?></p>
                        <?php elseif (in_array($key, $shadowed, true)): ?>
                            <p class="description" style="color:#b32d2e"><?php esc_html_e('Not active: a field group with this key is already defined in code, which takes precedence.', 'ctrlfield'); ?></p>
                        <?php endif; ?>
                        <div class="row-actions">
                            <span><a href="<?php echo esc_url($editUrl); ?>"><?php esc_html_e('Edit', 'ctrlfield'); ?></a> | </span>
                            <span><a href="<?php echo esc_url($this->url(['action' => 'code', self::ARG => $key])); ?>"><?php esc_html_e('Export PHP', 'ctrlfield'); ?></a> | </span>
                            <span class="trash"><a href="<?php echo esc_url($this->deleteUrl($key)); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this field group? The values already saved in posts stay in the database and come back if you recreate the fields with the same keys.', 'ctrlfield')); ?>');"><?php esc_html_e('Delete', 'ctrlfield'); ?></a></span>
                        </div>
                    </td>
                    <td><code><?php echo esc_html($key); ?></code></td>
                    <td><?php echo esc_html($this->locationSummary($group['location'])); ?></td>
                    <td><?php echo esc_html((string) count($group['fields'])); ?></td>
                    <td><?php echo $group['_source'] === FieldGroupRepository::SOURCE_FILE
                        ? '<code>' . esc_html($key) . '.json</code>'
                        : esc_html__('Database', 'ctrlfield'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($inCode !== []): ?>
            <h2 style="margin-top:32px"><?php esc_html_e('Defined in code', 'ctrlfield'); ?></h2>
            <p class="description"><?php esc_html_e('These groups come from PHP schema files or the theme. Edit them in code.', 'ctrlfield'); ?></p>
            <table class="wp-list-table widefat fixed striped" style="margin-top:8px">
                <thead>
                    <tr>
                        <th style="width:35%"><?php esc_html_e('Title', 'ctrlfield'); ?></th>
                        <th><?php esc_html_e('Key', 'ctrlfield'); ?></th>
                        <th><?php esc_html_e('Location', 'ctrlfield'); ?></th>
                        <th style="width:80px"><?php esc_html_e('Fields', 'ctrlfield'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($inCode as $key => $g):
                    $rules = array_values(array_map(static fn ($c) => ['key' => $c['key'], 'operator' => $c['operator'], 'value' => is_scalar($c['value']) ? (string) $c['value'] : '…'], $g->getAndConditions()));
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($g->getTitle() !== '' ? $g->getTitle() : $key); ?></strong></td>
                        <td><code><?php echo esc_html($key); ?></code></td>
                        <td><?php echo esc_html($this->locationSummary($rules)); ?></td>
                        <td><?php echo esc_html((string) count($g->getFields())); ?></td>
                        <td><span class="post-state"><?php esc_html_e('Defined in code', 'ctrlfield'); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    /** @param array<string, mixed>|null $group */
    private function renderForm(?array $group): void
    {
        $isNew  = $group === null;
        $flash  = $this->takeErrors();
        $errors = $flash['errors'] ?? [];
        $values = $flash['input'] ?? $group ?? [
            'key' => '', 'title' => '', 'active' => true, 'position' => 'normal', 'style' => 'default',
            'labelPlacement' => 'top', 'fields' => [],
            'location' => [['key' => 'post_type', 'operator' => '==', 'value' => 'post']],
        ];
        unset($values['_source'], $values['_errors']);
        if ($group !== null && $group['_errors'] !== [] && $errors === []) {
            $errors = $group['_errors'];
        }

        $config = [
            'group'           => $values,
            'types'           => $this->typesConfig(),
            'locationChoices' => $this->locationChoices(),
            'returnFormats'   => $this->returnFormats(),
            'i18n'            => ['confirmRemove' => __('Remove this field?', 'ctrlfield')],
        ];
        ?>
        <h1><?php echo $isNew
            ? esc_html__('Add New Field Group', 'ctrlfield')
            : esc_html(sprintf(__('Edit Field Group: %s', 'ctrlfield'), $group['title'])); ?></h1>
        <p><a href="<?php echo esc_url($this->url()); ?>">&larr; <?php esc_html_e('All field groups', 'ctrlfield'); ?></a></p>

        <?php if ($errors !== []): ?>
            <div class="notice notice-error"><p><strong><?php esc_html_e('The field group was not saved:', 'ctrlfield'); ?></strong></p>
                <ul style="list-style:disc;margin-left:20px"><?php foreach ($errors as $e): ?><li><?php echo esc_html($e); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <?php $this->renderNotice(); ?>

        <div id="ctrlfield-group-editor" x-data="ctrlFieldGroupEditor" data-config="<?php echo esc_attr((string) wp_json_encode($config)); ?>">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" @submit="serialize()">
            <input type="hidden" name="action" value="ctrlfield_save_field_group">
            <input type="hidden" name="previous_key" value="<?php echo esc_attr($isNew ? '' : $group['key']); ?>">
            <input type="hidden" name="group_json" :value="payload">
            <?php wp_nonce_field('ctrlfield_save_field_group'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="cf-title"><?php esc_html_e('Title', 'ctrlfield'); ?></label></th>
                    <td><input id="cf-title" type="text" class="regular-text" x-model="group.title" @input="titleChanged()" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="cf-key"><?php esc_html_e('Key', 'ctrlfield'); ?></label></th>
                    <td>
                        <input id="cf-key" type="text" class="regular-text code" x-model="group.key" @input="keyTouched = true" pattern="[a-z][a-z0-9_]*" required>
                        <p class="description"><?php esc_html_e('Lowercase letters, numbers and underscores. Also the JSON file name.', 'ctrlfield'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Active', 'ctrlfield'); ?></th>
                    <td><label><input type="checkbox" x-model="group.active"> <?php esc_html_e('Show this group in the editor', 'ctrlfield'); ?></label></td>
                </tr>
            </table>

            <h2><?php esc_html_e('Fields', 'ctrlfield'); ?></h2>
            <p x-show="path.length > 0" style="font-size:14px">
                <a href="#" @click.prevent="goTo(0)"><?php esc_html_e('Fields', 'ctrlfield'); ?></a>
                <template x-for="crumb in breadcrumb" :key="crumb.depth">
                    <span> &rsaquo; <a href="#" @click.prevent="goTo(crumb.depth)" x-text="crumb.label"></a></span>
                </template>
                <span class="description"> — <?php esc_html_e('sub-fields', 'ctrlfield'); ?></span>
            </p>

            <div class="ctrlfield-ge-list" style="max-width:960px">
                <p x-show="currentFields.length === 0" class="description"><?php esc_html_e('No fields yet. Click "Add Field".', 'ctrlfield'); ?></p>
                <template x-for="(field, index) in currentFields" :key="field._uid">
                    <div class="postbox" style="margin-bottom:8px">
                        <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;cursor:pointer" @click="field._open = !field._open">
                            <span class="description" x-text="index + 1" style="width:20px"></span>
                            <strong style="flex:1" x-text="field.label || field.key || '<?php echo esc_js(__('(no label)', 'ctrlfield')); ?>'"></strong>
                            <code x-text="field.key"></code>
                            <span style="width:140px" x-text="typeLabel(field.type)"></span>
                            <span @click.stop>
                                <button type="button" class="button-link" @click="move(index, -1)" :disabled="index === 0" aria-label="<?php esc_attr_e('Move up', 'ctrlfield'); ?>">&uarr;</button>
                                <button type="button" class="button-link" @click="move(index, 1)" :disabled="index === currentFields.length - 1" aria-label="<?php esc_attr_e('Move down', 'ctrlfield'); ?>">&darr;</button>
                                <button type="button" class="button-link button-link-delete" @click="removeField(index)"><?php esc_html_e('Remove', 'ctrlfield'); ?></button>
                            </span>
                        </div>
                        <div x-show="field._open" style="border-top:1px solid #dcdcde;padding:0 12px 12px">
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Field type', 'ctrlfield'); ?></th>
                                    <td>
                                        <select x-model="field.type">
                                            <?php foreach ($this->typeCategories() as $category => $types): ?>
                                                <optgroup label="<?php echo esc_attr($category); ?>">
                                                    <?php foreach ($types as $type):
                                                        $t = $config['types'][$type]; ?>
                                                        <option value="<?php echo esc_attr($type); ?>" <?php disabled(! $t['available']); ?>><?php echo esc_html($t['label'] . ($t['pro'] && ! $t['available'] ? ' (Pro)' : '')); ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Label', 'ctrlfield'); ?></th>
                                    <td><input type="text" class="regular-text" x-model="field.label" @input="labelChanged(field)"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Key', 'ctrlfield'); ?></th>
                                    <td>
                                        <input type="text" class="regular-text code" x-model="field.key" @input="field._keyTouched = true" pattern="[a-z][a-z0-9_]*">
                                        <p class="description"><?php esc_html_e('Used in templates: ctrlfield_get(\'key\'). Changing it later hides the values already saved under the old key.', 'ctrlfield'); ?></p>
                                    </td>
                                </tr>
                                <?php $this->settingRows(); ?>
                            </table>
                        </div>
                    </div>
                </template>
                <p><button type="button" class="button" @click="addField()">+ <?php esc_html_e('Add Field', 'ctrlfield'); ?></button></p>
            </div>

            <h2><?php esc_html_e('Location', 'ctrlfield'); ?></h2>
            <p class="description"><?php esc_html_e('Show this group when all of these rules match.', 'ctrlfield'); ?></p>
            <table class="widefat" style="max-width:960px;margin-top:8px">
                <tbody>
                <template x-for="(rule, index) in group.location" :key="index">
                    <tr>
                        <td>
                            <select x-model="rule.key" @change="rule.value = ''">
                                <?php foreach ($this->locationParams() as $param => $label): ?>
                                    <option value="<?php echo esc_attr($param); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select x-model="rule.operator">
                                <option value="=="><?php esc_html_e('is equal to', 'ctrlfield'); ?></option>
                                <option value="!="><?php esc_html_e('is not equal to', 'ctrlfield'); ?></option>
                            </select>
                        </td>
                        <td>
                            <template x-if="choicesFor(rule.key)">
                                <select x-model="rule.value">
                                    <option value=""><?php esc_html_e('— Select —', 'ctrlfield'); ?></option>
                                    <template x-for="(label, value) in choicesFor(rule.key)" :key="value">
                                        <option :value="value" x-text="label" :selected="value === rule.value"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="!choicesFor(rule.key)">
                                <input type="text" x-model="rule.value" placeholder="<?php esc_attr_e('Value', 'ctrlfield'); ?>">
                            </template>
                        </td>
                        <td style="width:80px"><button type="button" class="button-link button-link-delete" @click="removeRule(index)"><?php esc_html_e('Remove', 'ctrlfield'); ?></button></td>
                    </tr>
                </template>
                </tbody>
            </table>
            <p><button type="button" class="button" @click="addRule()">+ <?php esc_html_e('Add rule', 'ctrlfield'); ?></button></p>
            <div x-show="group.locationAny.length > 0" class="notice notice-info inline" style="max-width:930px">
                <p>
                    <?php esc_html_e('Also shown when any of these rules matches (imported from ACF; kept as is, edit them in PHP):', 'ctrlfield'); ?>
                    <template x-for="(rule, i) in group.locationAny" :key="i">
                        <code style="margin-left:6px" x-text="rule.key + ' ' + rule.operator + ' ' + rule.value"></code>
                    </template>
                </p>
            </div>

            <h2><?php esc_html_e('Settings', 'ctrlfield'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Position', 'ctrlfield'); ?></th>
                    <td><select x-model="group.position">
                        <option value="normal"><?php esc_html_e('Normal (below the content)', 'ctrlfield'); ?></option>
                        <option value="side"><?php esc_html_e('Side', 'ctrlfield'); ?></option>
                        <option value="after_title"><?php esc_html_e('High (after the title)', 'ctrlfield'); ?></option>
                    </select></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Style', 'ctrlfield'); ?></th>
                    <td><select x-model="group.style">
                        <option value="default"><?php esc_html_e('Standard (box)', 'ctrlfield'); ?></option>
                        <option value="seamless"><?php esc_html_e('Seamless (no box)', 'ctrlfield'); ?></option>
                    </select></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Label placement', 'ctrlfield'); ?></th>
                    <td><select x-model="group.labelPlacement">
                        <option value="top"><?php esc_html_e('Above fields', 'ctrlfield'); ?></option>
                        <option value="left"><?php esc_html_e('Beside fields', 'ctrlfield'); ?></option>
                    </select></td>
                </tr>
            </table>

            <?php submit_button($isNew ? __('Create Field Group', 'ctrlfield') : __('Save Changes', 'ctrlfield')); ?>
        </form>
        </div>
        <?php
    }

    /** The per-type setting rows of a field (shown only when the type supports them). */
    private function settingRows(): void
    {
        $postTypes  = $this->postTypeChoices();
        $taxonomies = $this->taxonomyChoices();
        $roles      = wp_roles()->get_names();

        $row = static function (string $setting, string $label, string $input, string $help = ''): void {
            printf(
                '<tr x-show="has(field, \'%1$s\')"><th scope="row">%2$s</th><td>%3$s%4$s</td></tr>',
                esc_attr($setting),
                esc_html($label),
                $input, // built below from escaped parts
                $help !== '' ? '<p class="description">' . esc_html($help) . '</p>' : ''
            );
        };
        $check = static fn (string $model, string $text): string =>
            '<label><input type="checkbox" x-model="field.' . esc_attr($model) . '"> ' . esc_html($text) . '</label>';
        $text = static fn (string $model, string $type = 'text'): string =>
            '<input type="' . esc_attr($type) . '" class="regular-text" x-model="field.' . esc_attr($model) . '">';
        $select = static function (string $model, array $choices, bool $multiple = false): string {
            $html = '<select x-model="field.' . esc_attr($model) . '"' . ($multiple ? ' multiple size="5"' : '') . '>';
            if (! $multiple) {
                $html .= '<option value="">' . esc_html__('— Select —', 'ctrlfield') . '</option>';
            }
            foreach ($choices as $value => $label) {
                $html .= '<option value="' . esc_attr((string) $value) . '">' . esc_html((string) $label) . '</option>';
            }
            return $html . '</select>';
        };

        $row('instructions', __('Instructions', 'ctrlfield'), '<textarea class="large-text" rows="2" x-model="field.instructions"></textarea>', __('Shown to editors below the label.', 'ctrlfield'));
        $row('required', __('Required', 'ctrlfield'), $check('required', __('The post cannot be saved without a value', 'ctrlfield')));
        $row('options', __('Choices', 'ctrlfield'), '<textarea class="large-text code" rows="5" x-model="field._optionsText"></textarea>', __('One per line. Use "value : Label" to show a different text than the stored value.', 'ctrlfield'));
        $row('allowNull', __('Allow empty', 'ctrlfield'), $check('allowNull', __('No choice selected is allowed', 'ctrlfield')));
        $row('default', __('Default value', 'ctrlfield'), $text('default'));
        $row('placeholder', __('Placeholder', 'ctrlfield'), $text('placeholder'));
        $row('min', __('Minimum', 'ctrlfield'), $text('min', 'number'));
        $row('max', __('Maximum', 'ctrlfield'), $text('max', 'number'));
        $row('step', __('Step', 'ctrlfield'), $text('step', 'number'));
        $row('message', __('Message', 'ctrlfield'), $text('message'), __('Text shown next to the checkbox.', 'ctrlfield'));
        $row('content', __('Message', 'ctrlfield'), '<textarea class="large-text" rows="3" x-model="field.content"></textarea>');
        $row('format', __('Display format', 'ctrlfield'), $text('format'), __('PHP date format, e.g. d/m/Y. Empty: the site\'s format.', 'ctrlfield'));
        $row('postType', __('Post types', 'ctrlfield'), $select('postType', $postTypes, true), __('Empty: all post types.', 'ctrlfield'));
        $row('relatedPostType', __('Post type', 'ctrlfield'), $select('relatedPostType', $postTypes));
        $row('taxonomy', __('Taxonomy', 'ctrlfield'), $select('taxonomy', $taxonomies));
        $row('appearance', __('Appearance', 'ctrlfield'), $select('appearance', [
            'select' => __('Dropdown', 'ctrlfield'), 'checkbox' => __('Checkboxes', 'ctrlfield'), 'radio' => __('Radio buttons', 'ctrlfield'),
        ]));
        $row('roles', __('Roles', 'ctrlfield'), $select('roles', $roles, true), __('Empty: all users.', 'ctrlfield'));
        $row('multiple', __('Multiple', 'ctrlfield'), $check('multiple', __('Allow more than one', 'ctrlfield')));
        $row('returnFormat', __('Return format', 'ctrlfield'),
            '<template x-if="returnFormats(field)"><select x-model="field.returnFormat"><option value="">' . esc_html__('Default', 'ctrlfield') . '</option>'
            . '<template x-for="(label, value) in returnFormats(field)" :key="value"><option :value="value" x-text="label" :selected="value === field.returnFormat"></option></template></select></template>'
            . '<template x-if="!returnFormats(field)"><input type="text" class="regular-text" x-model="field.returnFormat" placeholder="d/m/Y"></template>',
            __('What templates receive. For dates: a PHP date format.', 'ctrlfield'));
        $row('bidirectional', __('Bidirectional', 'ctrlfield'), $check('bidirectional', __('Also link back from the related posts', 'ctrlfield')));
        $row('minItems', __('Minimum items', 'ctrlfield'), $text('minItems', 'number'));
        $row('maxItems', __('Maximum items', 'ctrlfield'), $text('maxItems', 'number'));
        $row('language', __('Language', 'ctrlfield'), $select('language', [
            'html' => 'HTML', 'css' => 'CSS', 'javascript' => 'JavaScript', 'php' => 'PHP', 'json' => 'JSON',
        ]));
        $row('fields', __('Sub-fields', 'ctrlfield'), '<button type="button" class="button" @click="enter(index)">'
            . esc_html__('Edit sub-fields', 'ctrlfield') . ' (<span x-text="field.fields.length"></span>)</button>');
        $row('width', __('Width', 'ctrlfield'), $select('width', ['25' => '25%', '50' => '50%', '75' => '75%']), __('Empty: full width.', 'ctrlfield'));
        $row('adminColumn', __('Admin column', 'ctrlfield'), $check('adminColumn', __('Show as a column in the post list', 'ctrlfield')));
        $row('showInRest', __('REST API', 'ctrlfield'), $check('showInRest', __('Include in the REST API', 'ctrlfield')));
        $row('visibleWhen', __('Show only when', 'ctrlfield'),
            '<select x-model="field._cond.field"><option value="">' . esc_html__('— Always show —', 'ctrlfield') . '</option>'
            . '<template x-for="s in siblings(field)" :key="s._uid"><option :value="s.key" x-text="s.label || s.key" :selected="s.key === field._cond.field"></option></template></select> '
            . '<select x-model="field._cond.operator" x-show="field._cond.field">'
            . '<option value="==">' . esc_html__('is equal to', 'ctrlfield') . '</option>'
            . '<option value="!=">' . esc_html__('is not equal to', 'ctrlfield') . '</option>'
            . '<option value="contains">' . esc_html__('contains', 'ctrlfield') . '</option>'
            . '<option value="empty">' . esc_html__('is empty', 'ctrlfield') . '</option>'
            . '<option value="not_empty">' . esc_html__('is not empty', 'ctrlfield') . '</option>'
            . '<option value=">">' . esc_html__('is greater than', 'ctrlfield') . '</option>'
            . '<option value="<">' . esc_html__('is less than', 'ctrlfield') . '</option>'
            . '</select> '
            . '<input type="text" x-model="field._cond.value" x-show="field._cond.field && ![\'empty\', \'not_empty\'].includes(field._cond.operator)" placeholder="' . esc_attr__('Value', 'ctrlfield') . '">',
            __('Another field of this group decides whether this one is shown.', 'ctrlfield'));
    }

    /** @param array<string, mixed> $group */
    private function renderCode(array $group): void
    {
        ?>
        <h1><?php echo esc_html(sprintf(__('Export PHP: %s', 'ctrlfield'), $group['title'])); ?></h1>
        <p><a href="<?php echo esc_url($this->url()); ?>">&larr; <?php esc_html_e('All field groups', 'ctrlfield'); ?></a></p>
        <p class="description"><?php esc_html_e('Save this code as a PHP file in your schema folder, then delete the group here: groups defined in code take precedence.', 'ctrlfield'); ?></p>
        <textarea class="large-text code" rows="24" readonly onclick="this.select()"><?php echo esc_textarea(JsonGroup::toPhp($group)); ?></textarea>
        <?php
    }

    // -------------------------------------------------------------------------
    // Handlers
    // -------------------------------------------------------------------------

    public function handleSave(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to do this.', 'ctrlfield'), 403);
        }
        check_admin_referer('ctrlfield_save_field_group');

        $raw         = json_decode(wp_unslash((string) ($_POST['group_json'] ?? '')), true);
        $previousKey = sanitize_key(wp_unslash((string) ($_POST['previous_key'] ?? '')));
        $previousKey = $previousKey !== '' ? $previousKey : null;

        [$group, $errors] = JsonGroup::normalize(is_array($raw) ? $raw : []);
        $key = (string) $group['key'];

        if ($errors === [] && $key !== $previousKey) {
            if ($this->repo->get($key) !== null) {
                $errors[] = sprintf(__('Another field group already uses the key "%s".', 'ctrlfield'), $key);
            } elseif (FieldRegistry::has($key) && ! in_array($key, FieldGroupsServiceProvider::registered(), true)) {
                $errors[] = sprintf(__('A field group defined in code already uses the key "%s".', 'ctrlfield'), $key);
            }
        }

        if ($errors !== []) {
            set_transient(self::ERRORS_TX . get_current_user_id(), [
                'errors' => $errors,
                'input'  => is_array($raw) ? $raw : $group,
            ], 300);
            $args = $previousKey !== null ? ['action' => 'edit', self::ARG => $previousKey] : ['action' => 'new'];
            wp_safe_redirect($this->url($args));
            exit;
        }

        $where = $this->repo->save($group, $previousKey);
        wp_safe_redirect($this->url([
            'action'  => 'edit',
            self::ARG => $key,
            'saved'   => $where,
        ]));
        exit;
    }

    public function handleDelete(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to do this.', 'ctrlfield'), 403);
        }

        $key = sanitize_key(wp_unslash((string) ($_GET[self::ARG] ?? '')));
        check_admin_referer('ctrlfield_delete_field_group_' . $key);

        $this->repo->delete($key);
        wp_safe_redirect($this->url(['deleted' => 1]));
        exit;
    }

    // -------------------------------------------------------------------------
    // Choices
    // -------------------------------------------------------------------------

    /** @return array<string, array{label: string, settings: list<string>, pro: bool, available: bool}> */
    private function typesConfig(): array
    {
        $labels = $this->typeLabels();
        $out    = [];
        foreach (JsonGroup::TYPES as $type => [, , $pro]) {
            $out[$type] = [
                'label'     => $labels[$type] ?? $type,
                'settings'  => JsonGroup::settingsFor($type),
                'pro'       => $pro,
                'available' => ! $pro || Field::hasProFactory($type),
            ];
        }

        return $out;
    }

    /** @return array<string, array<string, string>> type => [format => label]; dates take a free format */
    private function returnFormats(): array
    {
        $id     = __('ID', 'ctrlfield');
        $url    = __('URL', 'ctrlfield');
        $arr    = __('Array', 'ctrlfield');
        $obj    = __('Object', 'ctrlfield');
        $choice = ['value' => __('Value', 'ctrlfield'), 'label' => __('Label', 'ctrlfield'), 'array' => __('Value and label', 'ctrlfield')];

        return [
            'image'         => ['array' => $arr, 'url' => $url, 'id' => $id],
            'file'          => ['array' => $arr, 'url' => $url, 'id' => $id],
            'gallery'       => ['array' => $arr, 'url' => $url, 'id' => $id],
            'select'        => $choice,
            'radio'         => $choice,
            'checkbox'      => $choice,
            'button_group'  => $choice,
            'link'          => ['array' => $arr, 'url' => $url],
            'post_object'   => ['object' => $obj, 'id' => $id],
            'relationship'  => ['object' => $obj, 'id' => $id],
            'taxonomy_term' => ['id' => $id, 'object' => $obj],
            'user'          => ['array' => $arr, 'object' => $obj, 'id' => $id],
        ];
    }

    /** @return array<string, string> */
    private function typeLabels(): array
    {
        return [
            'text' => __('Text', 'ctrlfield'), 'textarea' => __('Text Area', 'ctrlfield'), 'number' => __('Number', 'ctrlfield'),
            'email' => __('Email', 'ctrlfield'), 'url' => __('URL', 'ctrlfield'), 'password' => __('Password', 'ctrlfield'),
            'wysiwyg' => __('WYSIWYG Editor', 'ctrlfield'), 'range' => __('Range', 'ctrlfield'),
            'select' => __('Select', 'ctrlfield'), 'radio' => __('Radio Buttons', 'ctrlfield'), 'checkbox' => __('Checkboxes', 'ctrlfield'),
            'button_group' => __('Button Group', 'ctrlfield'), 'true_false' => __('True / False', 'ctrlfield'),
            'date' => __('Date', 'ctrlfield'), 'datetime' => __('Date and Time', 'ctrlfield'), 'time' => __('Time', 'ctrlfield'),
            'color' => __('Color', 'ctrlfield'), 'image' => __('Image', 'ctrlfield'), 'file' => __('File', 'ctrlfield'),
            'link' => __('Link', 'ctrlfield'), 'oembed' => __('oEmbed', 'ctrlfield'), 'page_link' => __('Page Link', 'ctrlfield'),
            'post_object' => __('Post Object', 'ctrlfield'), 'relationship' => __('Relationship', 'ctrlfield'),
            'taxonomy_term' => __('Taxonomy', 'ctrlfield'), 'user' => __('User', 'ctrlfield'), 'map' => __('Map', 'ctrlfield'),
            'icon' => __('Icon', 'ctrlfield'), 'code' => __('Code', 'ctrlfield'), 'group' => __('Group', 'ctrlfield'),
            'tab' => __('Tab', 'ctrlfield'), 'message' => __('Message', 'ctrlfield'), 'separator' => __('Separator', 'ctrlfield'),
            'repeater' => __('Repeater', 'ctrlfield'), 'gallery' => __('Gallery', 'ctrlfield'),
            'flexible_content' => __('Flexible Content (layouts edited in PHP)', 'ctrlfield'),
        ];
    }

    /** @return array<string, list<string>> */
    private function typeCategories(): array
    {
        return [
            __('Basic', 'ctrlfield')      => ['text', 'textarea', 'number', 'range', 'email', 'url', 'password'],
            __('Content', 'ctrlfield')    => ['image', 'file', 'wysiwyg', 'oembed', 'gallery'],
            __('Choice', 'ctrlfield')     => ['select', 'checkbox', 'radio', 'button_group', 'true_false'],
            __('Relational', 'ctrlfield') => ['link', 'post_object', 'page_link', 'relationship', 'taxonomy_term', 'user'],
            __('Advanced', 'ctrlfield')   => ['date', 'datetime', 'time', 'color', 'map', 'icon', 'code'],
            __('Layout', 'ctrlfield')     => ['group', 'repeater', 'flexible_content', 'tab', 'message', 'separator'],
        ];
    }

    /** @return array<string, string> */
    private function locationParams(): array
    {
        return [
            'post_type'         => __('Post type', 'ctrlfield'),
            'page_template'     => __('Page template', 'ctrlfield'),
            'page_type'         => __('Page type', 'ctrlfield'),
            'post_parent'       => __('Parent (post ID)', 'ctrlfield'),
            'post_status'       => __('Post status', 'ctrlfield'),
            'post_format'       => __('Post format', 'ctrlfield'),
            'post_term'         => __('Post term (taxonomy:slug)', 'ctrlfield'),
            'post'              => __('Post (ID)', 'ctrlfield'),
            'taxonomy'          => __('Taxonomy term screen', 'ctrlfield'),
            'options_page'      => __('Options page', 'ctrlfield'),
            'context'           => __('Other screen', 'ctrlfield'),
            'user_role'         => __('Edited user role', 'ctrlfield'),
            'current_user_role' => __('Current user role', 'ctrlfield'),
            'current_user_can'  => __('Current user capability', 'ctrlfield'),
        ];
    }

    /** @return array<string, array<string, string>> param => [value => label] */
    private function locationChoices(): array
    {
        $templates = ['default' => __('Default template', 'ctrlfield')];
        foreach (wp_get_theme()->get_page_templates(null, 'page') as $file => $name) {
            $templates[$file] = $name;
        }
        if (function_exists('get_block_templates')) {
            foreach (get_block_templates(['post_type' => 'page']) as $tpl) {
                $templates[$tpl->slug] = $tpl->title !== '' ? $tpl->title : $tpl->slug;
            }
        }

        $formats = ['standard' => __('Standard', 'ctrlfield')] + get_post_format_strings();

        $options = [];
        foreach (OptionsPage::all() as $key => $page) {
            $options[$key] = $page->getTitle();
        }

        $roles = wp_roles()->get_names();

        $statuses = [];
        foreach (get_post_stati(['internal' => false], 'objects') as $name => $obj) {
            $statuses[$name] = (string) $obj->label;
        }

        return array_filter([
            'post_type'         => $this->postTypeChoices(),
            'page_template'     => $templates,
            'page_type'         => [
                'front_page' => __('Front page', 'ctrlfield'), 'posts_page' => __('Posts page', 'ctrlfield'),
                'top_level'  => __('Top level (no parent)', 'ctrlfield'), 'parent' => __('Parent (has children)', 'ctrlfield'),
                'child'      => __('Child (has parent)', 'ctrlfield'),
            ],
            'post_status'       => $statuses,
            'post_format'       => $formats,
            'taxonomy'          => $this->taxonomyChoices(),
            'options_page'      => $options,
            'context'           => [
                'user_profile' => __('User profile', 'ctrlfield'), 'comment' => __('Comment', 'ctrlfield'),
                'nav_menu_item' => __('Menu item', 'ctrlfield'), 'dashboard_widget' => __('Dashboard widget', 'ctrlfield'),
            ],
            'user_role'         => $roles,
            'current_user_role' => $roles,
        ]);
    }

    /** @return array<string, string> */
    private function postTypeChoices(): array
    {
        $out = [];
        foreach (get_post_types(['show_ui' => true], 'objects') as $name => $pt) {
            if (! in_array($name, ['wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_font_family', 'wp_global_styles'], true)) {
                $out[$name] = (string) $pt->labels->singular_name;
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    private function taxonomyChoices(): array
    {
        $out = [];
        foreach (get_taxonomies(['show_ui' => true], 'objects') as $tax) {
            $out[(string) $tax->name] = (string) $tax->labels->singular_name;
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param list<array{key: string, operator: string, value: string}> $rules */
    private function locationSummary(array $rules): string
    {
        $params = $this->locationParams();
        $parts  = [];
        foreach ($rules as $r) {
            $parts[] = ($params[$r['key']] ?? $r['key']) . ' ' . ($r['operator'] === '!=' ? '≠' : '=') . ' ' . $r['value'];
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }

    /** ACF field groups in the database (ACF active or not) or in acf-json folders. */
    private function hasAcfGroups(): bool
    {
        global $wpdb;
        $inDb = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'acf-field-group' AND post_status <> 'trash'");

        return $inDb > 0
            || (glob(get_stylesheet_directory() . '/acf-json/group_*.json') ?: []) !== []
            || (glob(get_template_directory() . '/acf-json/group_*.json') ?: []) !== [];
    }

    private function relativePath(string $path): string
    {
        return str_starts_with($path, ABSPATH) ? substr($path, strlen(ABSPATH)) : $path;
    }

    private function renderNotice(): void
    {
        if (isset($_GET['saved'])) {
            $msg = $_GET['saved'] === FieldGroupRepository::SOURCE_OPTION
                ? __('Field group saved in the database (the JSON folder is not writable).', 'ctrlfield')
                : __('Field group saved.', 'ctrlfield');
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        } elseif (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Field group deleted.', 'ctrlfield') . '</p></div>';
        }
    }

    /** @return array{errors?: list<string>, input?: array<string, mixed>} */
    private function takeErrors(): array
    {
        $tx   = self::ERRORS_TX . get_current_user_id();
        $data = get_transient($tx);
        delete_transient($tx);

        return is_array($data) ? $data : [];
    }

    /** @param array<string, string|int> $args */
    private function url(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => self::SLUG], $args), admin_url('admin.php'));
    }

    private function deleteUrl(string $key): string
    {
        return wp_nonce_url(
            add_query_arg(['action' => 'ctrlfield_delete_field_group', self::ARG => $key], admin_url('admin-post.php')),
            'ctrlfield_delete_field_group_' . $key
        );
    }
}
