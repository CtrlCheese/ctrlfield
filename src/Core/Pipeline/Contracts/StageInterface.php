<?php

declare(strict_types=1);

namespace CtrlField\Core\Pipeline\Contracts;

use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\PipelineException;

interface StageInterface
{
    /** @throws PipelineException */
    public function handle(PipelineContext $context): void;
}
