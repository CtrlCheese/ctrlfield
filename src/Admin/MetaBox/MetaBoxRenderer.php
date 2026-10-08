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
     * @param FieldGroup[] $groups
     */
    public function render(int $postId, array $groups): void
    {
        $adapter    = new PostMetaAdapter(new WpPostMetaDriver());
        $stored     = $adapter->load($postId) ?? [];
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
        $width        = $field->getWidth();

        // CSS class for field width: ctrlf-col-25, ctrlf-col-50, ctrlf-col-75, ctrlf-col-100
        $widthClass  = $width !== null ? " ctrlf-col-{$width}" : '';
        $fieldClass  = 'ctrlf-field' . $widthClass;

        ?>
        <div class="<?= esc_attr($fieldClass) ?>" x-show="isVisible('<?= esc_js($key) ?>')" x-cloak>
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
                // UI-only fields are never stored — skip them.
                if ($field->isUiOnly()) {
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

        $json = static fn (mixed $v): string => (string) (wp_json_encode($v, JSON_UNESCAPED_UNICODE) ?: '{}');

        return esc_attr(sprintf(
            'ctrlFieldAdmin({ values: %s, conditions: %s, labels: %s, attachments: %s })',
            $json($values ?: new \stdClass()),
            $json($conditions ?: new \stdClass()),
            $json($labels ?: new \stdClass()),
            $json($attachments ?: new \stdClass()),
        ));
    }

    /**
     * @param FieldGroup[]         $groups
     * @param array<string, mixed> $values
     * @return array{0: array<string, string>, 1: array<int, string>}
     */
    private function collectPickerLabels(array $groups, array $values): array
    {
        $postIds = $termIds = $attachmentIds = [];

        $ids = static function (mixed $v): array {
            $list = is_array($v) ? $v : [$v];
            return array_values(array_filter(array_map('intval', array_filter($list, 'is_scalar')), static fn (int $i) => $i > 0));
        };

        // Walk nested values too: images and posts inside groups, repeater rows
        // and flexible content sections need their thumbnails / titles as well.
        $visit = static function (FieldDefinition $field, mixed $value) use (&$visit, $ids, &$postIds, &$termIds, &$attachmentIds): void {
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
