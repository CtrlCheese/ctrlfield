<?php

declare(strict_types=1);

namespace FieldForge\Fields\Exceptions;

/**
 * Thrown when FieldPresets::get() or FieldPresets::{name}() is called
 * for a preset that has not been registered.
 */
class PresetNotFoundException extends \RuntimeException {}
