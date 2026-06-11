<?php

declare(strict_types=1);

namespace FieldForge\Bootstrap\Exceptions;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

class ContainerException extends RuntimeException implements ContainerExceptionInterface {}
