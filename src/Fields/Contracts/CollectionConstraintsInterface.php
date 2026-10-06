<?php

declare(strict_types=1);

namespace CtrlField\Fields\Contracts;

/**
 * Implemented by collection-type fields with min/max item constraints
 * (PostObjectField, TaxonomyField, RelationshipField).
 *
 * RulesVerificationStage checks this interface to enforce count bounds.
 */
interface CollectionConstraintsInterface
{
    public function getMinItems(): ?int;

    public function getMaxItems(): ?int;
}
