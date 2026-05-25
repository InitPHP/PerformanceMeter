<?php

declare(strict_types=1);

namespace InitPHP\PerformanceMeter\Exception;

use InvalidArgumentException;

/**
 * Thrown when a pointer is referenced by name but no checkpoint
 * with that name has been recorded.
 */
final class PointerNotFoundException extends InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self(\sprintf(
            'No checkpoint named "%s" has been recorded. Call PerformanceMeter::setPointer() before referencing it.',
            $name,
        ));
    }
}
