<?php

declare(strict_types=1);

namespace Tests\Support;

use Codeception\Test\Unit;

/**
 * Base test case for the parts of the SDK that read request superglobals.
 *
 * The SDK reads `$_REQUEST`/`$_GET` directly (HttpReceiver, BoxModel,
 * DropBoxModel), so every test has to leave them exactly as it found them.
 */
abstract class SuperglobalTestCase extends Unit
{
    /**
     * @var array<string, mixed>
     */
    private $requestBackup = [];

    /**
     * @var array<string, mixed>
     */
    private $getBackup = [];

    protected function _before()
    {
        $this->requestBackup = $_REQUEST;
        $this->getBackup = $_GET;
        $_REQUEST = [];
        $_GET = [];
    }

    protected function _after()
    {
        $_REQUEST = $this->requestBackup;
        $_GET = $this->getBackup;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function givenRequest(array $params): void
    {
        $_REQUEST = $params;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function givenQuery(array $params): void
    {
        $_GET = $params;
        $_REQUEST = array_merge($_REQUEST, $params);
    }

    /**
     * Calls a private/protected static method of the SDK.
     *
     * @param array<int, mixed> $args
     *
     * @return mixed
     */
    protected function callPrivateStatic(string $class, string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod($class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }
}
