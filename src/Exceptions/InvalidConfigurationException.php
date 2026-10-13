<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Exceptions;

/**
 * Thrown when a `cart.*` configuration value has a type or value the package cannot use.
 */
final class InvalidConfigurationException extends CartException
{
    public function __construct(
        public readonly string $key,
        string $expected,
        mixed $actual,
    ) {
        parent::__construct(sprintf(
            'The [%s] configuration value must be %s, %s given.',
            $key,
            $expected,
            is_scalar($actual) ? get_debug_type($actual).' '.var_export($actual, true) : get_debug_type($actual),
        ));
    }
}
