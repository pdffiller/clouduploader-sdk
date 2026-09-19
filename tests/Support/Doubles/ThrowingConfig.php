<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use RuntimeException;

/**
 * Config object that blows up as soon as the model reads a key from it.
 *
 * ServiceFabric wraps every model call in `catch (\Exception)`; this double is
 * the cheapest way to reach those catch branches without any I/O.
 */
class ThrowingConfig implements \ArrayAccess
{
    public const MESSAGE = 'config is unavailable';

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        throw new RuntimeException(self::MESSAGE);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        throw new RuntimeException(self::MESSAGE);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        throw new RuntimeException(self::MESSAGE);
    }
}
