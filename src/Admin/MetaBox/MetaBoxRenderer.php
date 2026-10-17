<?php

declare(strict_types=1);

namespace CtrlField\Admin\MetaBox;

use CtrlField\Builder\FieldGroup;
use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\FlexibleContentInterface;
use CtrlField\Fields\Contracts\NestedFieldInterface;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Renderers\FieldLayout;
use CtrlField\Fields\Renderers\RendererRegistry;
use CtrlField\Fields\Types\GroupField;
use CtrlField\Fields\Types\ImageField;
use CtrlField\Fields\Types\RelationshipField;
use CtrlField\Fields\Types\TabField;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Storage\PostMetaAdapter;
use CtrlField\Storage\RelationshipAdapter;

/**
 * Outputs the Alpine.js root component for a single FieldGroup meta box.
 *
 * Each group is rendered independently so groups can have different
 * labelPlacement, instructionPlacement, and field widths.
 *
 * Payload key: ctrlfield_payload[{group_key}] — allows multiple groups
 * on the same post edit screen without $_POST key collisions.
 *
 * Excluded from PHPStan — calls WP functions.
 */
class MetaBoxRenderer
{
    /** @var array<string, mixed> values of the group being rendered (for ctrlfield/prepare_field) */
    private array $currentValues = [];

    /**
     * Renders a single FieldGroup as a standalone Alpine component.
     */
    public function renderGroup(int $postId, FieldGroup $group): void
    {
        $adapter = new PostMetaAdapter(new WpPostMetaDriver());
        $this->renderStoredGroup($group, $adapter->load($postId) ?? [], $postId);
    }

    /**
     * Same UI for values that do not live in post meta (options pages).
     *
     * @param array<string, mixed> $stored
     */
    public function renderStoredGroup(FieldGroup $group, array $stored, int $postId = 0): void
    {
        $values     = $this->buildValues([$group], $stored, $postId);
        $this->currentValues = $values;
        $conditions = $this->buildConditions([$group]);
        $initJs     = $this->alpineInit($values, $conditions, [$group]);

        $groupKey        = $group->getKey();
        $labelPlacement  = $group->getLabelPlacement();   // 'top' | 'left'
        $instrPlacement  = $group->getInstructionPlacement(); // 'label' | 'field'
        $containerClass  = 'ctrlfield-container ctrlf-label-' . $labelPlacement;

        ?>
        <div class="<?= esc_attr($containerClass) ?>"
             x-data="<?= $initJs ?>"
             x-cloak>

            <?php wp_nonce_field('ctrlfield_save', '_ctrlfield_nonce'); ?>

            <div class="ctrlf-group-section">
                <?= $this->renderFields($group->getFields(), $labelPlacement, $instrPlacement) // phpcs:ignore WordPress.Security.EscapeOutput -- escaped per field ?>
            </div>

            <input type="hidden"
                   name="ctrlfield_payload[<?= esc_attr($groupKey) ?>]"
                   :value="JSON.stringify(adminState)">
        </div>
        <?php
    }

    /**
     * Legacy render for callers that still pass an array of groups
     * (e.g. UserMeta, Comment, Taxonomy renderers).
     *
     * @param FieldGroup[]              $groups
     * @param array<string, mixed>|null $stored  Values of a user / term / comment, loaded with
     *                                           its own adapter. Null = post meta of $postId.
     */
    public function render(int $postId, array $groups, ?array $stored = null): void
    {
        if ($stored === null) {
            $adapter = new PostMetaAdapter(new WpPostMetaDriver());
            $stored  = $adapter->load($postId) ?? [];
        } else {
            // Not a post: relationship pivots are keyed by post ID, do not load them.
            $postId = 0;
        }
        $values     = $this->buildValues($groups, $stored, $postId);
        $conditions = $this->buildConditions($groups);
        $initJs     = $this->alpineInit($values, $conditions, $groups);

        ?>
        <div class="ctrlfield-container ctrlf-label-top"
             x-data="<?= $initJs ?>"
             x-cloak>

            <?php wp_nonce_field('ctrlfield_save', '_ctrlfield_nonce'); ?>

            <?php foreach ($groups as $group): ?>
                <?php if (! empty($group->getFields())): ?>
                <div class="ctrlf-group-section">
                    <?php if ($group->getTitle()): ?>
                    <h4 class="ctrlf-group-title"><?= esc_html($group->getTitle()) ?></h4>
                    <?php endif; ?>

                    <?= $this->renderFields($group->getFields(), 'top', 'label') // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <input type="hidden"
                   name="ctrlfield_payload"
                   :value="JSON.stringify(adminState)">
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Field rendering
    // -------------------------------------------------------------------------

    private function renderField(
        FieldDefinition $field,
        string $labelPlacement,
        string $instrPlacement,
    ): void {
        // UI-only fields bypass the standard label/input wrapper.
        // Tabs and accordions are laid out by FieldLayout — skip here.
        if ($field->isUiOnly()) {
            if (in_array($field->getType(), [FieldType::TAB, FieldType::ACCORDION, FieldType::ACCORDION_END], true)) {
                return;
            }
            $renderer = RendererRegistry::resolve($field->getType());
            echo $renderer->render($field, ''); // phpcs:ignore WordPress.Security.EscapeOutput
            return;
        }

        // Field permissions: not shown at all, or shown locked. The save
        // pipeline enforces the same (FieldPermissionStage).
        $permission = $field->currentUserPermission();
        if ($permission === 'hidden') {
            return;
        }
        if ($permission === 'read' && ! $field->isReadOnly()) {
            $field = (clone $field)->readOnly();
        }

        $key          = $field->getKey();
        $ui           = \CtrlField\Fields\Renderers\AbstractRenderer::prepare($field, $this->currentValues[$key] ?? null);
        if ($ui['hidden']) {
            return;
        }
        $label        = $ui['label'];
        $required     = $field->isRequired();
        $instructions = $ui['instructions'];
        $statePath    = "adminState['{$key}']";
        $renderer     = RendererRegistry::resolve($field->getType());

        ?>
        <div class="ctrlf-field<?= \CtrlField\Fields\Renderers\AbstractRenderer::widthAttrs($field) // phpcs:ignore WordPress.Security.EscapeOutput -- a number ?> x-show="isVisible('<?= esc_js($key) ?>')" x-cloak>
            <label class="ctrlf-label" for="ctrlf-<?= esc_attr($key) ?>">
                <?= esc_html($label) ?>
                <?php if ($required): ?>
                    <span class="ctrlf-required" aria-hidden="true">*</span>
                <?php endif; ?>
                <?php if ($instructions !== '' && $instrPlacement === 'label'): ?>
                    <span class="ctrlf-instructions"><?= \CtrlField\Fields\Renderers\AbstractRenderer::instructionsHtml($instructions) // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses_post ?></span>
                <?php endif; ?>
            </label>
            <?= $renderer->render($field, $statePath) ?>
            <?php if ($instructions !== '' && $instrPlacement === 'field'): ?>
                <p class="ctrlf-instructions"><?= \CtrlField\Fields\Renderers\AbstractRenderer::instructionsHtml($instructions) // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses_post ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Fields with their accordions and tabs (FieldLayout), as one HTML string.
     *
     * @param array<int, FieldDefinition> $fields
     */
    private function renderFields(array $fields, string $labelPlacement, string $instrPlacement): string
    {
        return FieldLayout::render(array_values($fields), function (FieldDefinition $field) use ($labelPlacement, $instrPlacement): string {
            ob_start();
            $this->renderField($field, $labelPlacement, $instrPlacement);
            return (string) ob_get_clean();
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param  FieldGroup[]         $groups
     * @param  array<string, mixed> $stored
     * @return array<string, mixed>
     */
    private function buildValues(array $groups, array $stored, int $postId = 0): array
    {
        $values = [];

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                // UI-only fields are never stored — skip them. A field hidden from
                // this user (field permissions) never reaches the browser.
                if ($field->isUiOnly() || $field->currentUserPermission() === 'hidden') {
                    continue;
                }
                // Relationship values live in their pivot table, not in the JSON blob.
                // Without loading them here, saving the post would send an empty
                // list and wipe every relationship.
                if ($field instanceof RelationshipField && $postId > 0) {
                    $values[$field->getKey()] = RelationshipAdapter::load($postId, $field->getKey(), $field->getTableName());
                    continue;
                }
                $values[$field->getKey()] = $this->valueFor($field, $stored);
            }
        }

        return $values;
    }

    /**
     * Group values go to the browser as objects, also when empty: PHP stores an
     * empty group as [] and the JS state would then hold an array, where values
     * typed in are lost when the form is serialized (JSON drops non-index keys).
     * Applied inside repeater rows and flexible sections too.
     */
    public static function shapeForAdmin(mixed $value, FieldDefinition $field): mixed
    {
        $type = $field->getType();

        if ($type === FieldType::GROUP) {
            $object = is_array($value) ? $value : [];
            if ($field instanceof NestedFieldInterface) {
                foreach ($field->getFields() as $sub) {
                    if (array_key_exists($sub->getKey(), $object)) {
                        $object[$sub->getKey()] = self::shapeForAdmin($object[$sub->getKey()], $sub);
                    }
                }
            } else {
                // A group without declared fields (e.g. global block overrides): its entries are objects too.
                $object = array_map(static fn ($v) => is_array($v) ? (object) $v : $v, $object);
            }
            return (object) $object;
        }

        if ($type === FieldType::REPEATER && is_array($value) && $field instanceof NestedFieldInterface) {
            return array_map(static function ($row) use ($field) {
                if (! is_array($row)) {
                    return $row;
                }
                foreach ($field->getFields() as $sub) {
                    if (array_key_exists($sub->getKey(), $row)) {
                        $row[$sub->getKey()] = self::shapeForAdmin($row[$sub->getKey()], $sub);
                    }
                }
                return (object) $row;
            }, array_values($value));
        }

        if ($type === FieldType::FLEXIBLE_CONTENT && is_array($value) && $field instanceof FlexibleContentInterface) {
            return array_map(static function ($row) use ($field) {
                if (! is_array($row)) {
                    return $row;
                }
                $layout = is_string($row['_layout'] ?? null) ? $row['_layout'] : '';
                foreach ($field->getLayoutFields($layout) as $sub) {
                    if (array_key_exists($sub->getKey(), $row)) {
                        $row[$sub->getKey()] = self::shapeForAdmin($row[$sub->getKey()], $sub);
                    }
                }
                return (object) $row;
            }, array_values($value));
        }

        return $value;
    }

    /**
     * x-data expression for the ctrlFieldAdmin component: values, conditions and
     * the names / thumbnails of items already picked (so pickers show titles).
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $conditions
     * @param FieldGroup[]         $groups
     */
    private function alpineInit(array $values, array $conditions, array $groups): string
    {
        [$labels, $attachments] = $this->collectPickerLabels($groups, $values);

        // Only the browser copy is reshaped (PHP code above walks plain arrays).
        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                if (array_key_exists($field->getKey(), $values)) {
                    $values[$field->getKey()] = self::shapeForAdmin($values[$field->getKey()], $field);
                }
            }
        }

        // Stored values are the editors' text: Js::json keeps an "&quot;" in one
        // from turning into a quote when the browser reads the attribute.
        $json = static fn (mixed $v): string => \CtrlField\Admin\Js::json($v);

        return htmlspecialchars(sprintf(
            'ctrlFieldAdmin({ values: %s, conditions: %s, labels: %s, attachments: %s })',
            $json($values ?: new \stdClass()),
            $json($conditions ?: new \stdClass()),
            $json($labels ?: new \stdClass()),
            $json($attachments ?: new \stdClass()),
        ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param FieldGroup[]         $groups
     * @param array<string, mixed> $values
     * @return array{0: array<string, string>, 1: array<int|string, string>}
     */
    private function collectPickerLabels(array $groups, array $values): array
    {
        $postIds = $termIds = $attachmentIds = $previewSizes = [];

        $ids = static function (mixed $v): array {
            $list = is_array($v) ? $v : [$v];
            return array_values(array_filter(array_map('intval', array_filter($list, 'is_scalar')), static fn (int $i) => $i > 0));
        };

        // Walk nested values too: images and posts inside groups, repeater rows
        // and flexible content sections need their thumbnails / titles as well.
        $visit = static function (FieldDefinition $field, mixed $value) use (&$visit, $ids, &$postIds, &$termIds, &$attachmentIds, &$previewSizes): void {
            if (($field instanceof ImageField || $field->getType() === FieldType::GALLERY)
                && method_exists($field, 'getPreviewSize') && $field->getPreviewSize() !== 'thumbnail') {
                foreach ($ids($value) as $id) {
                    $previewSizes[$id . ':' . $field->getPreviewSize()] = [$id, $field->getPreviewSize()];
                }
            }
            match ($field->getType()) {
                FieldType::POST_OBJECT, FieldType::RELATIONSHIP => $postIds = array_merge($postIds, $ids($value)),
                FieldType::TAXONOMY_TERM                        => $termIds = array_merge($termIds, $ids($value)),
                FieldType::IMAGE, FieldType::GALLERY            => $attachmentIds = array_merge($attachmentIds, $ids($value)),
                default                                         => null,
            };
            if (! is_array($value)) {
                return;
            }
            if ($field->getType() === FieldType::GROUP && $field instanceof NestedFieldInterface) {
                foreach ($field->getFields() as $sub) {
                    $visit($sub, $value[$sub->getKey()] ?? null);
                }
            } elseif ($field->getType() === FieldType::REPEATER && $field instanceof NestedFieldInterface) {
                foreach ($value as $row) {
                    foreach ($field->getFields() as $sub) {
                        $visit($sub, is_array($row) ? ($row[$sub->getKey()] ?? null) : null);
                    }
                }
            } elseif ($field instanceof FlexibleContentInterface) {
                foreach ($value as $row) {
                    $layout = is_array($row) ? (string) ($row['_layout'] ?? '') : '';
                    if (! in_array($layout, $field->getLayoutKeys(), true)) {
                        continue;
                    }
                    foreach ($field->getLayoutFields($layout) as $sub) {
                        $visit($sub, $row[$sub->getKey()] ?? null);
                    }
                }
            }
        };

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $visit($field, $values[$field->getKey()] ?? null);
            }
        }

        $labels = [];
        if ($postIds !== []) {
            foreach (get_posts(['post__in' => array_unique($postIds), 'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => -1]) as $post) {
                $labels['post:' . $post->ID] = $post->post_title !== '' ? $post->post_title : '#' . $post->ID;
            }
        }
        if ($termIds !== []) {
            $terms = get_terms(['include' => array_unique($termIds), 'hide_empty' => false]);
            foreach (is_array($terms) ? $terms : [] as $term) {
                $labels['term:' . $term->term_id] = $term->name;
            }
        }

        $attachments = [];
        foreach (array_unique($attachmentIds) as $id) {
            $url = wp_get_attachment_image_url($id, 'thumbnail');
            if ($url) {
                $attachments[$id] = $url;
            }
        }
        foreach ($previewSizes as $cacheKey => [$id, $size]) {
            $url = wp_get_attachment_image_url($id, $size);
            if ($url) {
                $attachments[$cacheKey] = $url;
            }
        }

        return [$labels, $attachments];
    }

    private function valueFor(FieldDefinition $field, array $stored): mixed
    {
        $key = $field->getKey();

        if ($field instanceof GroupField) {
            $storedGroup = (isset($stored[$key]) && is_array($stored[$key])) ? $stored[$key] : [];
            $sub = [];
            foreach ($field->getFields() as $subField) {
                $sub[$subField->getKey()] = $this->valueFor($subField, $storedGroup);
            }
            return $sub;
        }

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        if ($field->hasDefault()) {
            return $field->getDefault();
        }

        return $this->defaultFor($field->getType()->value);
    }

    /** @param FieldGroup[] $groups */
    private function buildConditions(array $groups): array
    {
        $conditions = [];

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $cg = $field->getConditionGroup();
                if ($cg !== null) {
                    $conditions[$field->getKey()] = $cg->toArray();
                }
            }
        }

        return $conditions;
    }

    private function defaultFor(string $type): mixed
    {
        return match ($type) {
            'checkbox', 'repeater'      => [],
            'number', 'image', 'file'   => 0,
            'range'                     => 0.0,
            'link'                      => ['url' => '', 'title' => '', 'target' => '_self'],
            default                     => '',
        };
    }
}
