<?php

declare(strict_types=1);

namespace danog\MadelineProto\Db;

use Amp\Iterator;
use ArrayAccess;
use Countable;

/**
 * DB array interface.
 *
 * @extends ArrayAccess<array-key, mixed>
 */
interface DbArray extends DbType, ArrayAccess, Countable
{
    /**
     * Get Array copy.
     *
     * @return array<string|int, mixed>
     */
    public function getArrayCopy(): array;
    /**
     * Check if element is set.
     */
    public function isset(string|int $key): bool;
    /**
     * Unset element.
     */
    public function unset(string|int $key): void;
    /**
     * Set element.
     */
    public function set(string|int $key, mixed $value): void;
    /**
     * Get element.
     *
     * @param string|int $index
     */
    public function offsetGet(mixed $index): mixed;
    /**
     * Set element.
     *
     * @param string|int $index
     */
    public function offsetSet(mixed $index, mixed $value): void;
    /**
     * Unset element.
     * @param string|int $index Offset
     */
    public function offsetUnset(mixed $index): void;
    /**
     * @see DbArray::isset();
     */
    public function offsetExists(mixed $index): bool;
    /**
     * Clear all elements.
     */
    public function clear(): void;
    /**
     * Get iterator.
     *
     * @return \Traversable<array-key, mixed>
     */
    public function getIterator(): \Traversable;
}
