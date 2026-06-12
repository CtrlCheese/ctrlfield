<?php

declare(strict_types=1);

namespace FieldForge\Fields;

use FieldForge\Enums\AdminTab;
use FieldForge\Enums\FieldType;
use FieldForge\Fields\Conditions\ConditionGroup;
use FieldForge\Fields\Conditions\ConditionOperator;
use FieldForge\Fields\Contracts\FieldInterface;
use FieldForge\Fields\Exceptions\BulkEditOnInvalidTypeException;
use FieldForge\Fields\Exceptions\DuplicateConditionException;
use FieldForge\Fields\Exceptions\InvalidOperatorForTypeException;
use FieldForge\Fields\Exceptions\InvalidWidthException;
use FieldForge\Fields\Notifications\FieldNotificationConfig;

abstract class FieldDefinition implements FieldInterface
{
    protected string $label        = '';
    protected bool   $required     = false;
    protected bool   $index        = false;
    protected bool   $restExposed  = false;
    protected bool   $readOnly     = false;
    protected AdminTab $tab        = AdminTab::CONTENT;
    protected string $placeholder  = '';
    protected string $instructions = '';
    protected mixed  $default      = null;
    protected bool   $hasDefault   = false;
    protected ?int   $width        = null;

    protected ?ConditionGroup $conditionGroup = null;

    protected bool   $adminColumn         = false;
    protected string $adminColumnLabel    = '';
    protected bool   $adminColumnSortable = false;

    protected bool $translatable = true;

    // Quick Edit / Bulk Edit (A-7)
    protected bool   $quickEdit      = false;
    protected string $quickEditLabel = '';
    protected bool   $bulkEdit       = false;
    protected string $bulkEditLabel  = '';

    // Return Format Decorator (A-11)
    protected string $returnFormat = '';

    // Email Notifications (A-12)
    /** @var list<FieldNotificationConfig> */
    protected array $notifications = [];

    // Webhook on change (D-1)
    /** @var list<array{url:string,toValue:string,secret:string,payload:list<string>}> */
    protected array $webhookConfigs = [];

    // Advanced permissions (D-4) — role → 'edit'|'read'|'hidden'
    /** @var array<string, string> */
    protected array $permissions = [];

    private const VALID_WIDTHS = [25, 50, 75, 100];

    public function __construct(protected string $key) {}

    abstract public function getType(): FieldType;

    public function getKey(): string
    {
        return $this->key;
    }

    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }

    public function setIndex(bool $index = true): static
    {
        $this->index = $index;
        return $this;
    }

    public function showInRest(bool $show = true): static
    {
        $this->restExposed = $show;
        return $this;
    }

    public function tab(AdminTab $tab): static
    {
        $this->tab = $tab;
        return $this;
    }

    /**
     * v1 API — backwards compatible. Internally stored as a single-item AND group.
     * Throws DuplicateConditionException if a condition is already set.
     */
    public function visibleWhen(string $field, string $operator, mixed $value): static
    {
        return $this->visibleWhenAll([[$field, $operator, $value]]);
    }

    /**
     * Show field only when ALL conditions match.
     *
     * @param array<int, array{0: string, 1: string, 2: mixed}> $conditions
     */
    public function visibleWhenAll(array $conditions): static
    {
        $this->assertNoDuplicateCondition();
        $this->conditionGroup = ConditionGroup::all($this->normalizeConditions($conditions));
        return $this;
    }

    /**
     * Show field when ANY condition matches.
     *
     * @param array<int, array{0: string, 1: string, 2: mixed}> $conditions
     */
    public function visibleWhenAny(array $conditions): static
    {
        $this->assertNoDuplicateCondition();
        $this->conditionGroup = ConditionGroup::any($this->normalizeConditions($conditions));
        return $this;
    }

    private function assertNoDuplicateCondition(): void
    {
        if ($this->conditionGroup !== null) {
            throw new DuplicateConditionException(
                "Field '{$this->key}' already has a visibility condition. Call visibleWhen/All/Any only once per field."
            );
        }
    }

    /**
     * Validates operator strings and normalises condition tuples.
     *
     * @param  array<int, array{0: string, 1: string, 2: mixed}> $conditions
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function normalizeConditions(array $conditions): array
    {
        return array_map(function (array $c): array {
            [$field, $operator, $value] = $c;

            if (ConditionOperator::tryFrom($operator) === null) {
                throw new InvalidOperatorForTypeException(
                    "Unknown operator '{$operator}'. Valid operators: " .
                    implode(', ', array_column(ConditionOperator::cases(), 'value')) . '.'
                );
            }

            return [$field, $operator, $value];
        }, $conditions);
    }

    public function placeholder(string $text): static
    {
        $this->placeholder = $text;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default    = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function instructions(string $html): static
    {
        $this->instructions = $html;
        return $this;
    }

    public function readOnly(bool $lock = true): static
    {
        $this->readOnly = $lock;
        return $this;
    }

    public function width(int $percent): static
    {
        if (! in_array($percent, self::VALID_WIDTHS, true)) {
            throw new InvalidWidthException(
                "Invalid field width '{$percent}'. Accepted values: " . implode(', ', self::VALID_WIDTHS) . '.'
            );
        }
        $this->width = $percent;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isIndex(): bool
    {
        return $this->index;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function getInstructions(): string
    {
        return $this->instructions;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getConditionGroup(): ?ConditionGroup
    {
        return $this->conditionGroup;
    }

    // -------------------------------------------------------------------------
    // Admin Column fluents (A-4)
    // -------------------------------------------------------------------------

    /** Show this field as a column in the WP post list table. Requires setIndex(true). */
    public function adminColumn(bool $show = true): static
    {
        $this->adminColumn = $show;
        return $this;
    }

    /** Override the column header label. Defaults to the field label. */
    public function adminColumnLabel(string $label): static
    {
        $this->adminColumnLabel = $label;
        return $this;
    }

    /** Make the column sortable via the index row. Requires setIndex(true). */
    public function adminColumnSortable(bool $sortable = true): static
    {
        $this->adminColumnSortable = $sortable;
        return $this;
    }

    public function isAdminColumn(): bool
    {
        return $this->adminColumn;
    }

    public function getAdminColumnLabel(): string
    {
        return $this->adminColumnLabel !== '' ? $this->adminColumnLabel : $this->label;
    }

    public function isAdminColumnSortable(): bool
    {
        return $this->adminColumnSortable;
    }

    // -------------------------------------------------------------------------
    // Quick Edit / Bulk Edit fluents (A-7)
    // -------------------------------------------------------------------------

    public function quickEdit(bool $enabled = true): static
    {
        $this->quickEdit = $enabled;
        return $this;
    }

    public function quickEditLabel(string $label): static
    {
        $this->quickEditLabel = $label;
        return $this;
    }

    public function bulkEdit(bool $enabled = true): static
    {
        $allowed = [FieldType::SELECT, FieldType::RADIO, FieldType::CHECKBOX];
        if ($enabled && ! in_array($this->getType(), $allowed, true)) {
            throw new BulkEditOnInvalidTypeException($this->key, $this->getType()->value);
        }

        $this->bulkEdit = $enabled;
        return $this;
    }

    public function bulkEditLabel(string $label): static
    {
        $this->bulkEditLabel = $label;
        return $this;
    }

    public function isQuickEdit(): bool
    {
        return $this->quickEdit;
    }

    public function isBulkEdit(): bool
    {
        return $this->bulkEdit;
    }

    public function getQuickEditLabel(): string
    {
        return $this->quickEditLabel !== '' ? $this->quickEditLabel : $this->label;
    }

    public function getBulkEditLabel(): string
    {
        return $this->bulkEditLabel !== '' ? $this->bulkEditLabel : $this->label;
    }

    public function translate(bool $translatable = true): static
    {
        $this->translatable = $translatable;
        return $this;
    }

    public function isTranslatable(): bool
    {
        return $this->translatable;
    }

    // -------------------------------------------------------------------------
    // Return Format (A-11)
    // -------------------------------------------------------------------------

    /**
     * Configures how fieldforge_get() transforms the raw stored value on read.
     *
     * Supported formats:
     *  image/file  → 'id' (default), 'url', 'array'
     *  date/time   → 'string' (default), 'DateTime', 'timestamp'
     *  post_object → 'id' (default), 'object' (WP_Post), 'url'
     */
    public function returnFormat(string $format): static
    {
        $this->returnFormat = $format;
        return $this;
    }

    public function getReturnFormat(): string
    {
        return $this->returnFormat;
    }

    // -------------------------------------------------------------------------
    // Email Notifications (A-12)
    // -------------------------------------------------------------------------

    /**
     * Sends an email when this field changes to a specific value.
     *
     * Can be called multiple times to configure multiple notifications.
     *
     * @param string                   $toValue  Trigger value. Empty = any change.
     * @param string|string[]|\Closure $to       Recipient(s) or callable(int $postId).
     */
    public function notifyOnChange(
        string                   $toValue,
        string|array|\Closure    $to,
        string                   $subject,
        string                   $message,
    ): static {
        $this->notifications[] = new FieldNotificationConfig($toValue, $to, $subject, $message);
        return $this;
    }

    /**
     * @return list<FieldNotificationConfig>
     */
    public function getNotifications(): array
    {
        return $this->notifications;
    }

    // -------------------------------------------------------------------------
    // Webhook on change (D-1)
    // -------------------------------------------------------------------------

    /**
     * POST to an external URL when this field changes value.
     *
     * @param list<string> $payload Keys to include: 'post_id','field_key','old_value','new_value','post_title','date'
     */
    public function webhookOnChange(
        string $url,
        string $toValue = '',
        string $secret  = '',
        array  $payload = ['post_id', 'field_key', 'old_value', 'new_value', 'post_title', 'date'],
    ): static {
        $this->webhookConfigs[] = compact('url', 'toValue', 'secret', 'payload');
        return $this;
    }

    /** @return list<array{url:string,toValue:string,secret:string,payload:list<string>}> */
    public function getWebhookConfigs(): array
    {
        return $this->webhookConfigs;
    }

    // -------------------------------------------------------------------------
    // Advanced permissions (D-4)
    // -------------------------------------------------------------------------

    /**
     * Per-role field access. Role not listed = defaults to 'edit'.
     *
     * @param array<string, 'edit'|'read'|'hidden'> $permissions
     */
    public function permissions(array $permissions): static
    {
        $this->permissions = $permissions;
        return $this;
    }

    /** @return array<string, string> */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Returns the effective permission for the current user.
     * Falls back to 'edit' when the user's role is not listed.
     */
    public function currentUserPermission(): string
    {
        if (empty($this->permissions)) {
            return 'edit';
        }

        if (! function_exists('wp_get_current_user')) {
            return 'edit';
        }

        $user = wp_get_current_user();

        foreach ($this->permissions as $role => $perm) {
            if (in_array($role, (array) $user->roles, true)) {
                return $perm;
            }
        }

        return 'edit';
    }

    // -------------------------------------------------------------------------
    // UI-only marker (C-1, C-2)
    // -------------------------------------------------------------------------

    /**
     * Returns true for fields that are purely UI organisers (Tab, Accordion, Message, Separator).
     * UI-only fields are never included in the fieldforge_payload and are not stored.
     */
    public function isUiOnly(): bool
    {
        return false;
    }

    /**
     * Returns true when the index value should be sorted numerically.
     * NumberField and RangeField use meta_value_num; others use meta_value.
     * ISO 8601 dates sort correctly as strings, so DateField returns false.
     */
    public function isNumericSort(): bool
    {
        return in_array($this->getType(), [\FieldForge\Enums\FieldType::NUMBER, \FieldForge\Enums\FieldType::RANGE], true);
    }

    /**
     * Creates a copy of this field definition with a different key.
     * Used exclusively by CloneField expansion — not for general use.
     */
    public function withPrefixedKey(string $newKey): static
    {
        $clone      = clone $this;
        $clone->key = $newKey;
        return $clone;
    }

    /**
     * Validates that FlexibleContent is not nested in illegal positions.
     * No-op for all field types except FlexibleContentField (Pro), which overrides this.
     * RepeaterField and GroupField propagate the flags to their sub-fields.
     */
    public function validateFlexNesting(bool $insideFlexible = false, bool $insideRepeater = false): void {}

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return [
            'key'                   => $this->key,
            'type'                  => $this->getType()->value,
            'label'                 => $this->label,
            'required'              => $this->required,
            'index'                 => $this->index,
            'rest_exposed'          => $this->restExposed,
            'read_only'             => $this->readOnly,
            'tab'                   => $this->tab->value,
            'placeholder'           => $this->placeholder,
            'instructions'          => $this->instructions,
            'default'               => $this->hasDefault ? $this->default : null,
            'width'                 => $this->width,
            'condition'             => $this->conditionGroup?->toArray(),
            'admin_column'          => $this->adminColumn,
            'admin_column_label'    => $this->adminColumnLabel,
            'admin_column_sortable' => $this->adminColumnSortable,
            'translatable'          => $this->translatable,
            'quick_edit'            => $this->quickEdit,
            'quick_edit_label'      => $this->quickEditLabel,
            'bulk_edit'             => $this->bulkEdit,
            'bulk_edit_label'       => $this->bulkEditLabel,
        ];
    }
}
