<?php

namespace NOSQL\Test\Unit;

use ArrayIterator;
use MongoDB\Collection;
use NOSQL\Dto\PropertyDto;
use NOSQL\Models\Query\NOSQLQueryFilterBuilder;
use NOSQL\Models\Query\NOSQLQueryOptionsBuilder;
use NOSQL\Models\Query\NOSQLQueryPipelineBuilder;
use PHPUnit\Framework\TestCase;
use PSFS\base\types\Api;

final class NOSQLQueryBuilderBranchTest extends TestCase
{
    public function testFilterBuilderCoversArrayOperatorsAndScalarCasting(): void
    {
        $builder = new NOSQLQueryFilterBuilder();
        $model = $this->buildModel([
            $this->property('age', 'int'),
            $this->property('score', 'double'),
            $this->property('enabled', 'bool'),
            $this->property('tags', 'string'),
        ]);

        $filters = $builder->parseCriteria([
            'age' => ['$gt', '5'],
            'score' => '4.5',
            'enabled' => 'true',
            'tags' => ['red', 'blue'],
        ], $model, fn (mixed $value): string => (string)$value);

        self::assertSame(['$gt' => '5'], $filters['age']);
        self::assertSame(['$eq' => 4.5], $filters['score']);
        self::assertSame(['$eq' => true], $filters['enabled']);
        self::assertSame(['$in' => ['red', 'blue']], $filters['tags']);
    }

    public function testPipelineBuilderCoversCursorFilterAndCustomWhitelist(): void
    {
        $builder = new NOSQLQueryPipelineBuilder();
        $criteria = [
            Api::API_ORDER_FIELD => ['createdAt' => 1, '_id' => -1],
            '__cursor_mode' => 'cursor',
            '__after' => ['createdAt' => '2025-01-01', '_id' => 'abc'],
            '$lookup' => ['from' => 'other'],
            '$where' => 'return true;',
            'custom_pipelines' => [
                '$group_total' => ['_id' => '$status', 'total' => ['$sum' => 1]],
                '$function_bad' => ['body' => 'return true;'],
            ],
        ];

        $sort = $builder->parseSort($criteria);
        $cursor = $builder->parseCursorFilter($criteria, $sort);
        $custom = $builder->parseCustomPipelines($criteria);

        self::assertArrayHasKey('$or', $cursor);
        self::assertCount(2, $custom);
        self::assertSame('$lookup', array_key_first($custom[0]));
        self::assertSame('$group', array_key_first($custom[1]));
    }

    public function testOptionsBuilderCoversFallbackAndInferredCollation(): void
    {
        $builder = new NOSQLQueryOptionsBuilder();
        $options = $builder->buildDriverOptions();
        self::assertArrayNotHasKey('hint', $options);
        self::assertArrayNotHasKey('maxTimeMS', $options);

        $index = new class(['key' => ['name' => 1]]) extends \ArrayObject {
            public function __debugInfo(): array
            {
                return ['collation' => ['locale' => 'es', 'strength' => 1]];
            }
        };

        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('listIndexes')->willReturn(new ArrayIterator([$index]));

        $resolved = $builder->appendCollationOptions([], [], ['name' => ['$eq' => 'A']], $collection);
        self::assertSame(['locale' => 'es', 'strength' => 1], $resolved['collation']);
    }

    /**
     * @param PropertyDto[] $properties
     * @return object
     */
    private function buildModel(array $properties): \NOSQL\Models\NOSQLActiveRecord
    {
        /** @var \NOSQL\Models\NOSQLActiveRecord $model */
        $model = (new \ReflectionClass(QueryBuilderModelStub::class))->newInstanceWithoutConstructor();
        $schemaRef = new \ReflectionProperty(QueryBuilderModelStub::class, 'schema');
        $schemaRef->setAccessible(true);
        $schemaRef->setValue($model, (object)['properties' => $properties]);
        return $model;
    }

    private function property(string $name, string $type): PropertyDto
    {
        $property = new PropertyDto(false);
        $property->name = $name;
        $property->type = $type;
        return $property;
    }
}

final class QueryBuilderModelStub extends \NOSQL\Models\NOSQLActiveRecord
{
    protected $domain = 'NOSQL';

    public function __construct()
    {
    }
}
