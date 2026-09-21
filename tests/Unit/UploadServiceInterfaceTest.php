<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Interfaces\UploadServiceInterface;
use UploadModels\BoxModel;
use UploadModels\DropBoxModel;
use UploadModels\GoogleDriveModel;

/**
 * Contract test: ServiceFabric calls every model statically through the same
 * four entry points, so a model that drifts from the interface breaks routing
 * at runtime rather than at compile time.
 *
 * @covers \Interfaces\UploadServiceInterface
 */
class UploadServiceInterfaceTest extends Unit
{
    private const CONTRACT = [
        'auth' => ['state', 'config'],
        'uploadFile' => ['access_token', 'uploadFile', 'fileName', 'config'],
        'getToken' => ['config'],
        'updateFile' => ['access_token', 'fileUrl', 'fileNameWithoutExtension', 'fileId', 'config'],
    ];

    public function testInterfaceDeclaresExactlyTheRoutedMethods(): void
    {
        $reflection = new \ReflectionClass(UploadServiceInterface::class);
        $declared = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods()
        );

        sort($declared);
        $expected = array_keys(self::CONTRACT);
        sort($expected);

        $this->assertSame($expected, $declared);
    }

    /**
     * @dataProvider contractProvider
     */
    public function testInterfaceMethodIsStaticWithExpectedParameters(string $method, array $parameters): void
    {
        $reflection = new \ReflectionMethod(UploadServiceInterface::class, $method);

        $this->assertTrue($reflection->isStatic(), $method . '() must stay static');
        $this->assertSame(
            $parameters,
            array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                $reflection->getParameters()
            )
        );
    }

    /**
     * @dataProvider modelProvider
     */
    public function testModelImplementsTheInterface(string $model): void
    {
        $this->assertContains(UploadServiceInterface::class, class_implements($model));
    }

    /**
     * @dataProvider modelMethodProvider
     */
    public function testModelExposesInterfaceMethodStatically(string $model, string $method): void
    {
        $reflection = new \ReflectionMethod($model, $method);

        $this->assertTrue($reflection->isPublic(), $model . '::' . $method . '() must be public');
        $this->assertTrue($reflection->isStatic(), $model . '::' . $method . '() must be static');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function contractProvider(): array
    {
        $cases = [];
        foreach (self::CONTRACT as $method => $parameters) {
            $cases[$method] = [$method, $parameters];
        }

        return $cases;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function modelProvider(): array
    {
        return [
            'dropbox' => [DropBoxModel::class],
            'google drive' => [GoogleDriveModel::class],
            'box' => [BoxModel::class],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function modelMethodProvider(): array
    {
        $cases = [];
        foreach (self::modelProvider() as $label => [$model]) {
            foreach (array_keys(self::CONTRACT) as $method) {
                $cases[$label . ' ' . $method] = [$model, $method];
            }
        }

        return $cases;
    }
}
