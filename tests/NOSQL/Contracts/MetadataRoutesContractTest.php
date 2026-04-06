<?php

namespace NOSQL\Test\Contracts;

use NOSQL\Api\NOSQL;
use NOSQL\Api\base\NOSQLBase;
use NOSQL\Controller\NOSQLController;
use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\RouterHelper;
use PSFS\base\types\helpers\attributes\Api as ApiAttribute;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Route;

final class MetadataRoutesContractTest extends TestCase
{
    public function testNosqlApiClassHasApiAttribute(): void
    {
        $reflection = new \ReflectionClass(NOSQL::class);
        $attributes = $reflection->getAttributes(ApiAttribute::class);

        self::assertCount(1, $attributes);
        self::assertSame('__admin', $attributes[0]->newInstance()->value);
    }

    public function testNosqlAdminApiRoutesAndMethodsAreResolvedFromAttributes(): void
    {
        $this->assertRouteContract(
            NOSQL::class,
            'getNOSQLTypes',
            '__admin',
            'NOSQL',
            'GET',
            '/NOSQL/Api/__admin/types',
            '/{__DOMAIN__}/Api/{__API__}/types'
        );
        $this->assertRouteContract(
            NOSQL::class,
            'getFormValidations',
            '__admin',
            'NOSQL',
            'GET',
            '/NOSQL/Api/__admin/validations',
            '/{__DOMAIN__}/Api/{__API__}/validations'
        );
        $this->assertRouteContract(
            NOSQL::class,
            'readModules',
            '__admin',
            'NOSQL',
            'GET',
            '/NOSQL/Api/__admin/domains',
            '/{__DOMAIN__}/Api/{__API__}/domains'
        );
        $this->assertRouteContract(
            NOSQL::class,
            'readCollections',
            '__admin',
            'NOSQL',
            'GET',
            null,
            '/{__DOMAIN__}/Api/{__API__}/{module}/collections',
            '/NOSQL/Api/__admin/'
        );
        $this->assertRouteContract(
            NOSQL::class,
            'storeCollections',
            '__admin',
            'NOSQL',
            'PUT',
            null,
            '/{__DOMAIN__}/Api/{__API__}/{module}/collections',
            '/NOSQL/Api/__admin/'
        );
        $this->assertRouteContract(
            NOSQL::class,
            'syncCollections',
            '__admin',
            'NOSQL',
            'POST',
            null,
            '/{__DOMAIN__}/Api/{__API__}/{module}/sync',
            '/NOSQL/Api/__admin/'
        );
    }

    public function testControllerRoutesAndMethodsAreResolvedFromAttributes(): void
    {
        $this->assertRouteContract(
            NOSQLController::class,
            'index',
            'NOSQL',
            'NOSQL',
            'GET',
            '/admin/nosql',
            '/admin/nosql'
        );
        $this->assertRouteContract(
            NOSQLController::class,
            'configParams',
            'NOSQL',
            'NOSQL',
            'GET',
            '/admin/config/params',
            '/admin/config/params'
        );
    }

    public function testManagerTraitRoutesAreResolvedFromAttributes(): void
    {
        $this->assertRouteContract(
            NOSQLBase::class,
            'getForm',
            'users',
            'BLOG',
            'POST',
            '/admin/api/form/BLOG/users/nosql',
            '/admin/api/form/{__DOMAIN__}/{__API__}/nosql'
        );
        $this->assertRouteContract(
            NOSQLBase::class,
            'admin',
            'users',
            'BLOG',
            'GET',
            '/admin/BLOG/users/manager',
            '/admin/{__DOMAIN__}/{__API__}/manager'
        );
    }

    private function assertRouteContract(
        string $className,
        string $methodName,
        string $api,
        string $domain,
        string $http,
        ?string $expectedDefaultRoute,
        string $expectedPattern,
        ?string $expectedRouteContains = null
    ): void {
        $reflectionMethod = new \ReflectionMethod($className, $methodName);

        self::assertNotEmpty($reflectionMethod->getAttributes(HttpMethod::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(Route::class));

        [$route, $info] = RouterHelper::extractRouteInfo($reflectionMethod, $api, $domain);

        self::assertIsString($route);
        self::assertIsArray($info);
        self::assertSame($http, $info['http']);
        self::assertSame($expectedDefaultRoute ?? '', $info['default']);
        self::assertSame($expectedPattern, $info['pattern']);
        self::assertSame($methodName, $info['method']);
        self::assertStringContainsString($http . '#|#', $route);
        self::assertStringContainsString($expectedRouteContains ?? ($expectedDefaultRoute ?? ''), $route);
    }
}
