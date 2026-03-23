<?php

namespace NOSQL\Test\Unit;

use NOSQL\Services\NOSQLService;
use PHPUnit\Framework\TestCase;

final class NOSQLServiceIndexRecommendationTest extends TestCase
{
    public function testBuildRecommendedIndexesOnlyIncludesRequiredScalarLikeFields(): void
    {
        $service = (new \ReflectionClass(NOSQLService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(NOSQLService::class, 'buildRecommendedIndexes');
        $method->setAccessible(true);

        $indexes = $method->invoke($service, [
            'name' => 'orders',
            'properties' => [
                ['name' => 'code', 'type' => 'string', 'required' => true],
                ['name' => 'total', 'type' => 'double', 'required' => true],
                ['name' => 'payload', 'type' => 'object', 'required' => true],
                ['name' => 'optionalLabel', 'type' => 'string', 'required' => false],
            ],
        ]);

        self::assertCount(2, $indexes);
        self::assertSame('idx_auto_orders_code', $indexes[0]['name']);
        self::assertSame(['code' => 1], $indexes[0]['key']);
        self::assertFalse($indexes[0]['unique']);
        self::assertSame('idx_auto_orders_total', $indexes[1]['name']);
        self::assertSame(['total' => 1], $indexes[1]['key']);
    }
}

