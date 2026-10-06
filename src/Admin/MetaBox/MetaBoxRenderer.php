<?php

declare(strict_types=1);

namespace CtrlField\Admin\MetaBox;

use CtrlField\Builder\FieldGroup;
use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Renderers\RendererRegistry;
use CtrlField\Fields\Types\GroupField;
use CtrlField\Fields\Types\TabField;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Storage\PostMetaAdapter;

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
        $containerClass  = 'ctrlfield-container ctrlf-label-' . $labelPlacement;

        ?>
        <div class="<?= esc_attr($containerClass) ?>"
             x-data="ctrlFieldAdmin({ values: <?= $valuesJson ?>, conditions: <?= $conditionsJson ?> })"
             x-cloak>

            <?php wp_nonce_field('ctrlfield_save', '_ctrlfield_nonce'); ?>

            <div class="ctrlf-group-section">
                <?php
                $tabInfo = $this->extractTabSections($group->getFields());
                if ($tabInfo['hasTabs']) {
                    $this->renderGroupWithTabs($groupKey, $tabInfo, $labelPlacement, $instrPlacement);
                } else {
                    foreach ($group->getFields() as $field) {
                        $this->renderField($field, $labelPlacement, $instrPlacement);
                    }
                }
                ?>
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
        $values     = $this->buildValues($groups, $stored);
        $conditions = $this->buildConditions($groups);

        $valuesJson     = esc_attr(wp_json_encode($values,     JSON_UNESCAPED_UNICODE) ?: '{}');
        $conditionsJson = esc_attr(wp_json_encode($conditions, JSON_UNESCAPED_UNICODE) ?: '{}');

        ?>
        <div class="ctrlfield-container ctrlf-label-top"
             x-data="ctrlFieldAdmin({ values: <?= $valuesJson ?>, conditions: <?= $conditionsJson ?> })"
             x-cloak>

            <?php wp_nonce_field('ctrlfield_save', '_ctrlfield_nonce'); ?>

            <?php foreach ($groups as $group): ?>
                <?php if (! empty($group->getFields())): ?>
                <div class="ctrlf-group-section">
                    <?php if ($group->getTitle()): ?>
                    <h4 class="ctrlf-group-title"><?= esc_html($group->getTitle()) ?></h4>
                    <?php endif; ?>

                    <?php foreach ($group->getFields() as $field): ?>
                        <?php $this->renderField($field, 'top', 'label'); ?>
                    <?php endforeach; ?>
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
        // Tab fields are handled entirely by renderGroupWithTabs() — skip here.
        if ($field->isUiOnly()) {
            if ($field->getType() === FieldType::TAB) {
                return;
            }
            $renderer = RendererRegistry::resolve($field->getType());
            echo $renderer->render($field, ''); // phpcs:ignore WordPress.Security.EscapeOutput
            return;
        }

        $key          = $field->getKey();
        $definition   = $field->getDefinition();
        $label        = $definition['label'] ?: $key;
        $required     = $field->isRequired();
        $instructions = $field->getInstructions();
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
                    <span class="ctrlf-instructions"><?= esc_html($instructions) ?></span>
                <?php endif; ?>
            </label>
            <?= $renderer->render($field, $statePath) ?>
            <?php if ($instructions !== '' && $instrPlacement === 'field'): ?>
                <p class="ctrlf-instructions"><?= esc_html($instructions) ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Tab grouping
    // -------------------------------------------------------------------------

    /**
     * Analyses a flat list of fields and separates them into tab sections.
     *
     * @param  FieldDefinition[] $fields
     * @return array{hasTabs: bool, beforeTabs: list<FieldDefinition>, sections: list<array{key: string, label: string, fields: list<FieldDefinition>}>}
     */
    private function extractTabSections(array $fields): array
    {
        $hasTabs   = false;
        $beforeTabs = [];
        $sections  = [];

        $currentSection = null;

        foreach ($fields as $field) {
            if ($field instanceof TabField) {
                $hasTabs = true;
                $def     = $field->getDefinition();
                $currentSection = [
                    'key'    => $field->getKey(),
                    'label'  => $def['label'] ?: $field->getKey(),
                    'fields' => [],
                ];
                $sections[] = &$currentSection;
                unset($currentSection); // break the reference; $sections keeps it
                $currentSection = &$sections[count($sections) - 1];
            } elseif ($currentSection !== null) {
                $currentSection['fields'][] = $field;
            } else {
                $beforeTabs[] = $field;
            }
        }

        return [
            'hasTabs'    => $hasTabs,
            'beforeTabs' => $beforeTabs,
            'sections'   => $sections,
        ];
    }

    /**
     * Renders fields that contain at least one tab divider.
     *
     * @param array{hasTabs: bool, beforeTabs: list<FieldDefinition>, sections: list<array{key: string, label: string, fields: list<FieldDefinition>}>} $tabInfo
     */
    private function renderGroupWithTabs(
        string $groupKey,
        array  $tabInfo,
        string $labelPlacement,
        string $instrPlacement,
    ): void {
        $sections    = $tabInfo['sections'];
        $beforeTabs  = $tabInfo['beforeTabs'];
        $firstTabKey = ! empty($sections) ? esc_js($sections[0]['key']) : '';
        $escapedGroup = esc_js($groupKey);

        ?>
        <div class="ctrlf-tabs">
            <div class="ctrlf-tabs-nav">
                <?php foreach ($sections as $section): ?>
                    <button type="button" class="ctrlf-tab-btn"
                        :class="{'is-active': (activeTabs['<?= $escapedGroup ?>'] ?? '<?= esc_js($sections[0]['key']) ?>') === '<?= esc_js($section['key']) ?>'}"
                        @click="activeTabs['<?= $escapedGroup ?>'] = '<?= esc_js($section['key']) ?>'">
                        <?= esc_html($section['label']) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($beforeTabs as $field): ?>
                <?php $this->renderField($field, $labelPlacement, $instrPlacement); ?>
            <?php endforeach; ?>

            <?php foreach ($sections as $section): ?>
                <div class="ctrlf-tab-panel"
                     x-show="(activeTabs['<?= $escapedGroup ?>'] ?? '<?= esc_js($sections[0]['key']) ?>') === '<?= esc_js($section['key']) ?>'">
                    <?php foreach ($section['fields'] as $field): ?>
                        <?php $this->renderField($field, $labelPlacement, $instrPlacement); ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
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
                // UI-only fields are never stored — skip them.
                if ($field->isUiOnly()) {
                    continue;
                }
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
