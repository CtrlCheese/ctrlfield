<?php

declare(strict_types=1);

namespace FieldForge\Admin\MetaBox;

use FieldForge\Builder\FieldGroup;
use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Renderers\RendererRegistry;
use FieldForge\Fields\Types\GroupField;
use FieldForge\Storage\Drivers\WpPostMetaDriver;
use FieldForge\Storage\PostMetaAdapter;

/**
 * Outputs the Alpine.js root component for a single FieldGroup meta box.
 *
 * Each group is rendered independently so groups can have different
 * labelPlacement, instructionPlacement, and field widths.
 *
 * Payload key: fieldforge_payload[{group_key}] — allows multiple groups
 * on the same post edit screen without $_POST key collisions.
 *
 * Excluded from PHPStan — calls WP functions.
 */
class MetaBoxRenderer
{
    /**
     * Renders a single FieldGroup as a standalone Alpine component.
     */
    public function renderGroup(int $postId, FieldGroup $group): void
    {
        $adapter    = new PostMetaAdapter(new WpPostMetaDriver());
        $stored     = $adapter->load($postId) ?? [];
        $values     = $this->buildValues([$group], $stored);
        $conditions = $this->buildConditions([$group]);

        $valuesJson     = esc_attr(wp_json_encode($values,     JSON_UNESCAPED_UNICODE) ?: '{}');
        $conditionsJson = esc_attr(wp_json_encode($conditions, JSON_UNESCAPED_UNICODE) ?: '{}');

        $groupKey        = $group->getKey();
        $labelPlacement  = $group->getLabelPlacement();   // 'top' | 'left'
        $instrPlacement  = $group->getInstructionPlacement(); // 'label' | 'field'
        $containerClass  = 'fieldforge-container ff-label-' . $labelPlacement;

        ?>
        <div class="<?= esc_attr($containerClass) ?>"
             x-data="fieldForgeAdmin({ values: <?= $valuesJson ?>, conditions: <?= $conditionsJson ?> })"
             x-cloak>

            <?php wp_nonce_field('fieldforge_save', '_fieldforge_nonce'); ?>

            <div class="ff-group-section">
                <?php foreach ($group->getFields() as $field): ?>
                    <?php $this->renderField($field, $labelPlacement, $instrPlacement); ?>
                <?php endforeach; ?>
            </div>

            <input type="hidden"
                   name="fieldforge_payload[<?= esc_attr($groupKey) ?>]"
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
        $values     = $this->buildValues($groups, $stored);
        $conditions = $this->buildConditions($groups);

        $valuesJson     = esc_attr(wp_json_encode($values,     JSON_UNESCAPED_UNICODE) ?: '{}');
        $conditionsJson = esc_attr(wp_json_encode($conditions, JSON_UNESCAPED_UNICODE) ?: '{}');

        ?>
        <div class="fieldforge-container ff-label-top"
             x-data="fieldForgeAdmin({ values: <?= $valuesJson ?>, conditions: <?= $conditionsJson ?> })"
             x-cloak>

            <?php wp_nonce_field('fieldforge_save', '_fieldforge_nonce'); ?>

            <?php foreach ($groups as $group): ?>
                <?php if (! empty($group->getFields())): ?>
                <div class="ff-group-section">
                    <?php if ($group->getTitle()): ?>
                    <h4 class="ff-group-title"><?= esc_html($group->getTitle()) ?></h4>
                    <?php endif; ?>

                    <?php foreach ($group->getFields() as $field): ?>
                        <?php $this->renderField($field, 'top', 'label'); ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <input type="hidden"
                   name="fieldforge_payload"
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
        $key          = $field->getKey();
        $definition   = $field->getDefinition();
        $label        = $definition['label'] ?: $key;
        $required     = $field->isRequired();
        $instructions = $field->getInstructions();
        $statePath    = "adminState['{$key}']";
        $renderer     = RendererRegistry::resolve($field->getType());
        $width        = $field->getWidth();

        // CSS class for field width: ff-col-25, ff-col-50, ff-col-75, ff-col-100
        $widthClass  = $width !== null ? " ff-col-{$width}" : '';
        $fieldClass  = 'ff-field' . $widthClass;

        ?>
        <div class="<?= esc_attr($fieldClass) ?>" x-show="isVisible('<?= esc_js($key) ?>')" x-cloak>
            <label class="ff-label" for="ff-<?= esc_attr($key) ?>">
                <?= esc_html($label) ?>
                <?php if ($required): ?>
                    <span class="ff-required" aria-hidden="true">*</span>
                <?php endif; ?>
                <?php if ($instructions !== '' && $instrPlacement === 'label'): ?>
                    <span class="ff-instructions"><?= esc_html($instructions) ?></span>
                <?php endif; ?>
            </label>
            <?= $renderer->render($field, $statePath) ?>
            <?php if ($instructions !== '' && $instrPlacement === 'field'): ?>
                <p class="ff-instructions"><?= esc_html($instructions) ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param  FieldGroup[]         $groups
     * @param  array<string, mixed> $stored
     * @return array<string, mixed>
     */
    private function buildValues(array $groups, array $stored): array
    {
        $values = [];

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $values[$field->getKey()] = $this->valueFor($field, $stored);
            }
        }

        return $values;
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
