<?php

declare(strict_types=1);

namespace FieldForge\Bootstrap\Exceptions;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

class NotFoundException extends RuntimeException implements NotFoundExceptionInterface {}
