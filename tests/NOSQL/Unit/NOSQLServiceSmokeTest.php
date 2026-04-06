<?php

namespace NOSQL\Test\Unit;

use NOSQL\Services\NOSQLService;
use PHPUnit\Framework\TestCase;
use PSFS\base\Cache;
use PSFS\base\types\SimpleService;

final class NOSQLServiceSmokeTest extends TestCase
{
    public function testGetDomainsFiltersRootAndNormalizesLabels(): void
    {
        $service = (new \ReflectionClass(NOSQLService::class))->newInstanceWithoutConstructor();

        $cache = $this->createMock(Cache::class);
        $cache->method('getDataFromFile')->willReturn([
            '@Billing/' => [],
            '@ROOT/' => [],
            '@Orders/' => [],
        ]);

        $cacheRef = new \ReflectionProperty(SimpleService::class, 'cache');
        $cacheRef->setAccessible(true);
        $cacheRef->setValue($service, $cache);

        self::assertSame(['Billing', 'Orders'], $service->getDomains());
    }

    public function testGetValidationsReturnsAvailableValidationConstants(): void
    {
        $service = (new \ReflectionClass(NOSQLService::class))->newInstanceWithoutConstructor();
        $validations = $service->getValidations();

        self::assertIsArray($validations);
        self::assertNotEmpty($validations);
    }

    public function testParseCollectionBuildsJsonSchemaRequiredAndProperties(): void
    {
        $service = (new \ReflectionClass(NOSQLService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(NOSQLService::class, 'parseCollection');
        $method->setAccessible(true);

        $schema = $method->invoke($service, [
            'properties' => [
                ['name' => 'code', 'type' => 'string', 'required' => true],
                ['name' => 'amount', 'type' => 'double', 'required' => false],
            ],
        ])->toArray();

        self::assertSame(['code'], $schema['required']);
        self::assertArrayHasKey('code', $schema['properties']);
        self::assertArrayHasKey('amount', $schema['properties']);
    }
}
