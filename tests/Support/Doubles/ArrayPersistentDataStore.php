<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Kunnu\Dropbox\Store\PersistentDataStoreInterface;

/**
 * Session-free persistent data store, so CSRF handling can be exercised in
 * unit tests without an active PHP session.
 */
class ArrayPersistentDataStore implements PersistentDataStoreInterface
{
    /**
     * @var array<string, string>
     */
    private $data = [];

    public function get($key)
    {
        return $this->data[$key] ?? null;
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function clear($key)
    {
        unset($this->data[$key]);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
}
