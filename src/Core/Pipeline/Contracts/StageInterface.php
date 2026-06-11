<?php

declare(strict_types=1);

namespace FieldForge\Core\Pipeline\Contracts;

use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\PipelineException;

interface StageInterface
{
    /** @throws PipelineException */
    public function handle(PipelineContext $context): void;
}
