<?php

declare(strict_types=1);

namespace CtrlField\Core\Pipeline\Traits;

use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\FieldDefinition;

trait BuildsFieldMap
{
    /**
     * Builds a flat key → FieldDefinition map from all provided field groups.
     * Only top-level fields are included; nested repeater/group sub-fields are not flattened.
     *
     * @param FieldGroup[]                    $groups
     * @return array<string, FieldDefinition>
     */
    private static function buildFieldMap(array $groups): array
    {
        $map = [];

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $map[$field->getKey()] = $field;
            }
        }

        return $map;
    }
}
