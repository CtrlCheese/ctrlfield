<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\UserField;

final class UserRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $multiple   = false;
        $roles      = [];

        if ($field instanceof UserField) {
            $multiple = $field->isMultiple();
            $roles    = $field->getRoles();
        }

        $fieldKey    = $this->esc($field->getKey());
        $rolesJson   = $this->esc(wp_json_encode($roles) ?: '[]');
        $multipleJs  = $multiple ? 'true' : 'false';
        $escapedPath = $this->esc($statePath);

        $selectedSingle = sprintf(
            '<template x-if="!%1$s && %2$s">'
            . '<span class="ff-user-tag">'
            . '<span x-text="selectedUserLabels[\'%3$s\'] ?? %2$s"></span>'
            . '<button type="button" @click="%2$s = null; selectedUserLabels[\'%3$s\'] = \'\'">&#215;</button>'
            . '</span>'
            . '</template>',
            $multipleJs,
            $escapedPath,
            $fieldKey,
        );

        $selectedMultiple = sprintf(
            '<template x-if="%1$s && Array.isArray(%2$s)">'
            . '<template x-for="(uid, idx) in %2$s" :key="uid">'
            . '<span class="ff-user-tag">'
            . '<span x-text="selectedUserLabels[\'%3$s\' + \'_\' + uid] ?? uid"></span>'
            . '<button type="button" @click="%2$s.splice(idx, 1)">&#215;</button>'
            . '</span>'
            . '</template>'
            . '</template>',
            $multipleJs,
            $escapedPath,
            $fieldKey,
        );

        return sprintf(
            '<div class="ff-user-field" data-multiple="%s" data-roles="%s">'
            . '<input type="text" class="ff-user-search ff-input" placeholder="Search users..."'
            . ' @input.debounce.300ms="searchUsers($event.target.value, \'%s\', %s)"'
            . ' autocomplete="off">'
            . '<div class="ff-user-results" x-show="(userResults[\'%s\'] ?? []).length > 0">'
            . '<template x-for="u in (userResults[\'%s\'] ?? [])" :key="u.id">'
            . '<button type="button" class="ff-user-result-item"'
            . ' @click="selectUser(\'%s\', u, %s)">'
            . '<img :src="u.avatar_url" class="ff-user-avatar" width="24" height="24">'
            . '<span x-text="u.display_name"></span>'
            . '</button>'
            . '</template>'
            . '</div>'
            . '<div class="ff-user-selected">'
            . '%s'
            . '%s'
            . '</div>'
            . '</div>',
            $this->esc($multipleJs),
            $rolesJson,
            $fieldKey,
            $rolesJson,
            $fieldKey,
            $fieldKey,
            $fieldKey,
            $this->esc($multipleJs),
            $selectedSingle,
            $selectedMultiple,
        );
    }
}
