<?php

namespace NOSQL\Test;

use NOSQL\Dto\Model\NOSQLModelDto;
use NOSQL\Dto\PropertyDto;
use NOSQL\Models\NOSQLActiveRecord;
use NOSQL\Models\Query\NOSQLQueryFilterBuilder;
use NOSQL\Models\Query\NOSQLQueryOptionsBuilder;
use NOSQL\Models\Query\NOSQLQueryPipelineBuilder;
use NOSQL\Services\Sync\NOSQLIndexPlanner;
use NOSQL\Services\Sync\NOSQLSchemaBuilder;
use PHPUnit\Framework\TestCase;
use PSFS\base\types\Api;

final class NOSQLTest extends TestCase
{
    public function testGetDtoCopyResetsPrimaryKeyOnlyInClone(): void
    {
        $model = (new \ReflectionClass(StubActiveRecordForCopy::class))->newInstanceWithoutConstructor();

        $dto = new StubModelDto(false);
        $dto->setPk('507f1f77bcf86cd799439011');
        $property = new \ReflectionProperty(NOSQLActiveRecord::class, 'dto');
        $property->setAccessible(true);
        $property->setValue($model, $dto);

        $copy = $model->getDtoCopy(true);

        self::assertNull($copy->getPk());
        self::assertSame('507f1f77bcf86cd799439011', $dto->getPk());
    }

    public function testPipelineBuilderParsesLimitMinusOneAsUnlimited(): void
    {
        $builder = new NOSQLQueryPipelineBuilder();
        self::assertNull($builder->parseLimit([Api::API_LIMIT_FIELD => -1]));
    }

    public function testPipelineBuilderBuildsMatchAndLimitStages(): void
    {
        $builder = new NOSQLQueryPipelineBuilder();
        $pipeline = $builder->buildFindPipeline([
            'filters' => ['name' => ['$eq' => 'Alice']],
            'sort' => [],
            'projection' => [],
            'customPipelines' => [],
            'skip' => 0,
            'limit' => 5,
        ]);

        self::assertSame('$match', array_key_first($pipeline[0]));
        self::assertSame(['$limit' => 5], $pipeline[1]);
    }

    public function testOptionsBuilderBuildsMongoDriverOptions(): void
    {
        $builder = new NOSQLQueryOptionsBuilder();
        $options = $builder->buildDriverOptions([
            '__maxTimeMS' => 1200,
            '__hint' => ['name' => 1],
            '__allowDiskUse' => true,
        ]);

        self::assertSame(1200, $options['maxTimeMS']);
        self::assertSame(['name' => 1], $options['hint']);
        self::assertTrue($options['allowDiskUse']);
    }

    public function testSchemaBuilderCreatesRequiredFields(): void
    {
        $builder = new NOSQLSchemaBuilder();
        $schema = $builder->buildCollectionSchema([
            'properties' => [
                ['name' => 'code', 'type' => 'string', 'required' => true],
                ['name' => 'state', 'type' => 'enum', 'enum' => 'OPEN|CLOSED', 'required' => false],
            ],
        ])->toArray();

        self::assertSame(['code'], $schema['required']);
        self::assertSame(['OPEN', 'CLOSED'], $schema['properties']['state']['enum']);
    }

    public function testIndexPlannerBuildsAutoIndexesForRequiredFields(): void
    {
        $planner = new NOSQLIndexPlanner();
        $indexes = $planner->buildRecommendedIndexes([
            'name' => 'orders',
            'properties' => [
                ['name' => 'code', 'type' => 'string', 'required' => true],
                ['name' => 'meta', 'type' => 'object', 'required' => true],
                ['name' => 'optional', 'type' => 'string', 'required' => false],
            ],
        ]);

        self::assertCount(1, $indexes);
        self::assertSame('idx_auto_orders_code', $indexes[0]['name']);
    }

    public function testFilterBuilderCanParseTextSearch(): void
    {
        $builder = new NOSQLQueryFilterBuilder();
        $model = (new \ReflectionClass(StubFilterActiveRecord::class))->newInstanceWithoutConstructor();

        $schemaProperty = new PropertyDto(false);
        $schemaProperty->name = 'name';
        $schemaProperty->type = 'string';
        $schema = new \stdClass();
        $schema->properties = [$schemaProperty];
        $schemaRef = new \ReflectionProperty(StubFilterActiveRecord::class, 'schema');
        $schemaRef->setAccessible(true);
        $schemaRef->setValue($model, $schema);

        $filters = $builder->parseCriteria(
            [Api::API_COMBO_FIELD => 'hello', 'name' => 'Alice'],
            $model,
            fn (mixed $value): string => (string)$value
        );

        self::assertArrayHasKey('$text', $filters);
        self::assertArrayHasKey('name', $filters);
    }
}

final class StubModelDto extends NOSQLModelDto
{
    public string $name = '';
}

final class StubActiveRecordForCopy extends NOSQLActiveRecord
{
    protected $domain = 'stub';
}

final class StubFilterActiveRecord extends NOSQLActiveRecord
{
    protected $domain = 'stub';
}
