<?php

namespace NOSQL\Test\Unit;

use NOSQL\Models\NOSQLQuery;
use NOSQL\Models\Query\NOSQLQueryPlanner;
use PHPUnit\Framework\TestCase;
use PSFS\base\exception\ApiException;

final class NOSQLQueryPlannerTest extends TestCase
{
    public function testBuildFindPipelineIncludesExpectedStages(): void
    {
        $planner = new NOSQLQueryPlanner();
        $pipeline = $planner->buildFindPipeline([
            'filters' => ['name' => ['$eq' => 'Alice']],
            'sort' => ['createdAt' => -1],
            'projection' => ['_id' => 1, 'name' => 1],
            'customPipelines' => [['$lookup' => ['from' => 'other']]],
            'skip' => 20,
            'limit' => 10,
        ]);

        self::assertSame('$match', array_key_first($pipeline[0]));
        self::assertSame('$sort', array_key_first($pipeline[1]));
        self::assertSame('$project', array_key_first($pipeline[2]));
        self::assertSame('$lookup', array_key_first($pipeline[3]));
        self::assertSame(['$skip' => 20], $pipeline[4]);
        self::assertSame(['$limit' => 10], $pipeline[5]);
    }

    public function testBuildDriverOptionsIncludesHintMaxTimeAndDiskUse(): void
    {
        $planner = new NOSQLQueryPlanner();
        $options = $planner->buildDriverOptions([
            NOSQLQuery::NOSQL_HINT_FIELD => ['name' => 1],
            NOSQLQuery::NOSQL_MAX_TIME_MS_FIELD => 2500,
            NOSQLQuery::NOSQL_ALLOW_DISK_USE_FIELD => true,
        ]);

        self::assertArrayHasKey('hint', $options);
        self::assertArrayHasKey('maxTimeMS', $options);
        self::assertArrayHasKey('allowDiskUse', $options);
        self::assertSame(['name' => 1], $options['hint']);
        self::assertSame(2500, $options['maxTimeMS']);
        self::assertTrue($options['allowDiskUse']);
    }

    public function testToObjectIdThrowsApiExceptionForInvalidValue(): void
    {
        $planner = new NOSQLQueryPlanner();

        $this->expectException(ApiException::class);
        $planner->toObjectId('invalid-id');
    }
}
