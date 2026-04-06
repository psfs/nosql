<?php

namespace NOSQL\Test\Unit;

use NOSQL\Services\Sync\NOSQLIndexPlanner;
use PHPUnit\Framework\TestCase;

final class NOSQLIndexPlannerTest extends TestCase
{
    public function testBuildIndexesForCollectionParsesDirectionsAndRemovesDuplicateNames(): void
    {
        $planner = new NOSQLIndexPlanner();
        $indexes = $planner->buildIndexesForCollection([
            'name' => 'orders',
            'indexes' => [
                [
                    'name' => 'idx_orders_status',
                    'properties' => ['status.ASC'],
                    'unique' => false,
                ],
                [
                    'name' => 'idx_orders_status',
                    'properties' => ['status.DESC'],
                    'unique' => true,
                ],
                [
                    'name' => 'idx_orders_createdAt',
                    'properties' => ['createdAt.DESC'],
                    'unique' => false,
                ],
            ],
            'properties' => [
                ['name' => 'status', 'type' => 'string', 'required' => true],
            ],
        ], false);

        self::assertCount(2, $indexes);
        self::assertSame('idx_orders_status', $indexes[0]['name']);
        self::assertSame(['status' => 1], $indexes[0]['key']);
        self::assertFalse($indexes[0]['unique']);
        self::assertSame('idx_orders_createdAt', $indexes[1]['name']);
        self::assertSame(['createdAt' => -1], $indexes[1]['key']);
    }

    public function testBuildIndexesForCollectionCanAddAutoIndexes(): void
    {
        $planner = new NOSQLIndexPlanner();
        $indexes = $planner->buildIndexesForCollection([
            'name' => 'orders',
            'indexes' => [],
            'properties' => [
                ['name' => 'code', 'type' => 'string', 'required' => true],
                ['name' => 'payload', 'type' => 'object', 'required' => true],
                ['name' => 'optionalLabel', 'type' => 'string', 'required' => false],
            ],
        ], true);

        self::assertCount(1, $indexes);
        self::assertSame('idx_auto_orders_code', $indexes[0]['name']);
        self::assertSame(['code' => 1], $indexes[0]['key']);
    }
}
