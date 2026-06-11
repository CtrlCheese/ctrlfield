<?php

declare(strict_types=1);

namespace FieldForge\Builder;

use FieldForge\Builder\BlockConfig;
use FieldForge\Builder\DashboardWidgetConfig;
use FieldForge\Builder\Exceptions\InvalidConditionException;
use FieldForge\Builder\Exceptions\MissingBlockRendererException;
use FieldForge\Fields\Conditions\ConditionValidator;
use FieldForge\Fields\Contracts\ExpandableFieldInterface;
use FieldForge\Fields\Exceptions\AdminColumnWithoutIndexException;
use FieldForge\Fields\Exceptions\DuplicateFieldKeyException;
use FieldForge\Fields\FieldDefinition;
use FieldForge\Registry\ContextRegistry;
use FieldForge\Registry\FieldRegistry;
use FieldForge\Registry\PendingCloneRegistry;

final class FieldGroup
{
    private const VALID_OPERATORS = ['==', '!='];

    private string $title = '';

    private ?BlockConfig $blockConfig = null;

    // Role-Based Registration (A-13)
    private string $requiredCapability = '';

    // Dashboard Widget Context (A-15)
    private ?DashboardWidgetConfig $dashboardWidgetConfig = null;

    // Meta box display settings
    private string $position             = 'normal';  // 'normal' | 'side' | 'after_title'
    private string $style                = 'default'; // 'default' (boxed) | 'seamless'
    private string $labelPlacement       = 'top';     // 'top' | 'left'
    private string $instructionPlacement = 'label';   // 'label' | 'field'

    /**
     * AND conditions: all must evaluate to true for the group to match.
     *
     * @var array<int, array{key: string, operator: string, value: mixed}>
     */
    private array $andConditions = [];

    /**
     * OR groups: each group must have at least one condition that evaluates to true.
     *
     * @var array<int, array<int, array{key: string, operator: string, value: mixed}>>
     */
    private array $orGroups = [];

    /** @var array<int, FieldDefinition> */
    private array $fields = [];

    private function __construct(private readonly string $key) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function where(string $key, string $operator, mixed $value): self
    {
        self::assertContextKey($key);
        self::assertOperator($operator);

        $this->andConditions[] = [
            'key'      => $key,
            'operator' => $operator,
            'value'    => $value,
        ];

        return $this;
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: mixed}> $conditions
     */
    public function whereAny(array $conditions): self
    {
        if (empty($conditions)) {
            throw new InvalidConditionException(
                'whereAny() requires at least one condition. An empty array would make the group unmatchable.'
            );
        }

        $normalized = [];

        foreach ($conditions as $condition) {
            self::assertContextKey($condition[0]);
            self::assertOperator($condition[1]);

            $normalized[] = [
                'key'      => $condition[0],
                'operator' => $condition[1],
                'value'    => $condition[2],
            ];
        }

        $this->orGroups[] = $normalized;

        return $this;
    }

    /**
     * Register this field group as a Gutenberg block.
     *
     * Either $renderTemplate or $renderCallback must be provided.
     * Actual block registration is handled by BlockServiceProvider (Pro).
     *
     * @param string[]     $keywords
     * @param callable|null $renderCallback
     */
    public function asBlock(
        string   $icon           = 'admin-post',
        string   $category       = 'design',
        array    $keywords       = [],
        string   $renderTemplate = '',
        mixed    $renderCallback = null,
    ): self {
        if ($renderTemplate === '' && $renderCallback === null) {
            throw new MissingBlockRendererException($this->key);
        }

        $this->blockConfig = new BlockConfig(
            icon:           $icon,
            category:       $category,
            keywords:       $keywords,
            renderTemplate: $renderTemplate,
            renderCallback: $renderCallback,
        );

        return $this;
    }

    public function isBlock(): bool
    {
        return $this->blockConfig !== null;
    }

    public function getBlockConfig(): ?BlockConfig
    {
        return $this->blockConfig;
    }

    /** @param array<int, FieldDefinition> $fields */
    public function fields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    public function register(): void
    {
        // Validate FlexibleContent nesting rules (Pro enforcement via hook point).
        foreach ($this->fields as $field) {
            $field->validateFlexNesting();
        }

        // Expand CloneFields. If a source group is not yet registered, defer.
        $expanded = [];
        foreach ($this->fields as $field) {
            if ($field instanceof ExpandableFieldInterface) {
                $sourceKey = $field->getSourceGroupKey();

                if (! FieldRegistry::has($sourceKey)) {
                    PendingCloneRegistry::defer($sourceKey, $this);
                    return; // deferred — will re-register when source becomes available
                }

                foreach ($field->expand(FieldRegistry::get($sourceKey)) as $expandedField) {
                    $expanded[] = $expandedField;
                }
            } else {
                $expanded[] = $field;
            }
        }

        $this->fields = $expanded;

        // Check for duplicate keys after expansion
        $seen = [];
        foreach ($this->fields as $field) {
            $k = $field->getKey();
            if (isset($seen[$k])) {
                throw new DuplicateFieldKeyException(
                    "Duplicate field key '{$k}' in group '{$this->key}' after clone expansion."
                );
            }
            $seen[$k] = true;
        }

        // Build field map for condition validation (same-group references only).
        $fieldMap = [];
        foreach ($this->fields as $field) {
            $fieldMap[$field->getKey()] = $field;
        }

        // Validate operator applicability for each field that has a condition.
        foreach ($this->fields as $field) {
            ConditionValidator::validate($field, $fieldMap);
        }

        // Validate adminColumn requires setIndex — fail early, not at render time.
        foreach ($this->fields as $field) {
            if ($field->isAdminColumn() && ! $field->isIndex()) {
                throw new AdminColumnWithoutIndexException(
                    sprintf(
                        "Field '%s' in group '%s' has adminColumn(true) but setIndex is false. "
                        . "Admin columns require an index row for sortability. Add ->setIndex(true).",
                        $field->getKey(),
                        $this->key,
                    )
                );
            }
        }

        FieldRegistry::add($this);

        // Resolve any groups that were waiting for this group as a clone source.
        PendingCloneRegistry::resolve($this->key);
    }

    /**
     * Returns true when this group applies to the given admin context.
     * Delegates to ContextRegistry so built-in and custom resolvers are used.
     */
    public function matches(AdminContext $context): bool
    {
        return ContextRegistry::groupMatches($this, $context);
    }

    // -------------------------------------------------------------------------
    // Meta box display settings
    // -------------------------------------------------------------------------

    /**
     * Where the meta box appears on the post edit screen.
     *
     * - 'normal'      Default — below the editor in the main column.
     * - 'side'        Right sidebar (same as Featured Image, Categories, etc.).
     * - 'after_title' Immediately below the post title, above the editor.
     */
    public function position(string $position): self
    {
        $this->position = $position;
        return $this;
    }

    /**
     * Visual style of the meta box container.
     *
     * - 'default'   Standard WP meta box with border and header.
     * - 'seamless'  No box, no header — fields blend into the edit screen.
     */
    public function style(string $style): self
    {
        $this->style = $style;
        return $this;
    }

    /**
     * Where labels appear relative to their input.
     *
     * - 'top'   Label above the field (default).
     * - 'left'  Label to the left of the field in a two-column layout.
     */
    public function labelPlacement(string $placement): self
    {
        $this->labelPlacement = $placement;
        return $this;
    }

    /**
     * Where the field's ->instructions() text appears.
     *
     * - 'label'  Inline below the label.
     * - 'field'  Below the input control.
     */
    public function instructionPlacement(string $placement): self
    {
        $this->instructionPlacement = $placement;
        return $this;
    }

    public function getPosition(): string             { return $this->position; }
    public function getStyle(): string                { return $this->style; }
    public function getLabelPlacement(): string       { return $this->labelPlacement; }
    public function getInstructionPlacement(): string { return $this->instructionPlacement; }

    // -------------------------------------------------------------------------
    // Role-Based Registration (A-13)
    // -------------------------------------------------------------------------

    public function requiredCapability(string $capability): self
    {
        $this->requiredCapability = $capability;
        return $this;
    }

    public function getRequiredCapability(): string
    {
        return $this->requiredCapability;
    }

    // -------------------------------------------------------------------------
    // Dashboard Widget (A-15)
    // -------------------------------------------------------------------------

    public function dashboardWidget(
        string $title,
        string $context  = 'normal',
        string $priority = 'default',
    ): self {
        $this->dashboardWidgetConfig = new DashboardWidgetConfig($title, $context, $priority);
        return $this;
    }

    public function isDashboardWidget(): bool
    {
        return $this->dashboardWidgetConfig !== null;
    }

    public function getDashboardWidgetConfig(): ?DashboardWidgetConfig
    {
        return $this->dashboardWidgetConfig;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @return array<int, FieldDefinition> */
    public function getFields(): array
    {
        return $this->fields;
    }

    /** @return array<int, array{key: string, operator: string, value: mixed}> */
    public function getAndConditions(): array
    {
        return $this->andConditions;
    }

    /** @return array<int, array<int, array{key: string, operator: string, value: mixed}>> */
    public function getOrGroups(): array
    {
        return $this->orGroups;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private static function assertOperator(string $operator): void
    {
        if (! in_array($operator, self::VALID_OPERATORS, true)) {
            throw new InvalidConditionException(
                sprintf(
                    "Invalid where() operator '%s'. Context conditions only support: %s.",
                    $operator,
                    implode(', ', self::VALID_OPERATORS)
                )
            );
        }
    }

    private static function assertContextKey(string $key): void
    {
        if (! ContextRegistry::isValidKey($key)) {
            throw new InvalidConditionException(
                sprintf(
                    "Unknown context key '%s'. Built-in keys: post_type, options_page, taxonomy, context. "
                    . "Register custom keys via ContextRegistry::register().",
                    $key,
                )
            );
        }
    }

}
