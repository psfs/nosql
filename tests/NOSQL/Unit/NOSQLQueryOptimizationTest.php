<?php

namespace NOSQL\Test\Unit;

use ArrayIterator;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\CursorInterface;
use NOSQL\Dto\CollectionDto;
use NOSQL\Dto\PropertyDto;
use NOSQL\Models\NOSQLActiveRecord;
use NOSQL\Models\NOSQLQuery;
use NOSQL\Services\Base\NOSQLBase;
use PHPUnit\Framework\TestCase;
use PSFS\base\types\Api;

final class NOSQLQueryOptimizationTest extends TestCase
{
    public function testFindAppliesSortSkipLimitAndProjection(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::atMost(1))
            ->method('listIndexes')
            ->willReturn(new ArrayIterator([]));
        $collection->expects(self::once())
            ->method('countDocuments')
            ->with(
                self::arrayHasKey('name'),
                self::isType('array')
            )
            ->willReturn(17);
        $collection->expects(self::once())
            ->method('aggregate')
            ->with(
                self::callback(function (array $pipeline): bool {
                    self::assertSame('$match', array_key_first($pipeline[0]));
                    self::assertSame('$sort', array_key_first($pipeline[1]));
                    self::assertSame('$project', array_key_first($pipeline[2]));
                    self::assertSame(['age' => -1], $pipeline[1]['$sort']);
                    self::assertSame(['_id' => 1, 'name' => 1], $pipeline[2]['$project']);
                    self::assertSame(['$skip' => 20], $pipeline[3]);
                    self::assertSame(['$limit' => 10], $pipeline[4]);
                    return true;
                }),
                self::isType('array')
            )
            ->willReturn($this->createCursor([['name' => 'Alice']]));

        $db = $this->createMock(Database::class);
        $db->expects(self::once())->method('selectCollection')->willReturn($collection);

        $result = NOSQLQuery::find(
            StubQueryModel::class,
            [
                'name' => 'Alice',
                Api::API_ORDER_FIELD => ['age' => -1],
                Api::API_PAGE_FIELD => 3,
                Api::API_LIMIT_FIELD => 10,
                Api::API_FIELDS_RESULT_FIELD => ['name'],
            ],
            $db,
            true
        );

        self::assertSame(17, $result->count);
        self::assertSame(3, $result->page);
        self::assertSame(10, $result->limit);
        self::assertCount(1, iterator_to_array($result->items));
    }

    public function testFindSupportsCursorPaginationWithoutOffsetSkip(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::atMost(1))
            ->method('listIndexes')
            ->willReturn(new ArrayIterator([]));
        $collection->expects(self::once())
            ->method('countDocuments')
            ->willReturn(9);
        $collection->expects(self::once())
            ->method('aggregate')
            ->with(
                self::callback(function (array $pipeline): bool {
                    $match = $pipeline[0]['$match'] ?? [];
                    self::assertArrayHasKey('$and', $match);
                    self::assertCount(2, $match['$and']);
                    self::assertArrayHasKey('$or', $match['$and'][1]);
                    self::assertSame('$sort', array_key_first($pipeline[1]));
                    foreach ($pipeline as $stage) {
                        self::assertFalse(array_key_exists('$skip', $stage));
                    }
                    return true;
                }),
                self::isType('array')
            )
            ->willReturn($this->createCursor([]));

        $db = $this->createMock(Database::class);
        $db->expects(self::once())->method('selectCollection')->willReturn($collection);

        $after = json_encode(['createdAt' => '2024-01-01T00:00:00Z', '_id' => '507f1f77bcf86cd799439011']);
        NOSQLQuery::find(
            StubQueryModel::class,
            [
                'name' => 'foo',
                Api::API_ORDER_FIELD => ['createdAt' => 1, '_id' => 1],
                Api::API_PAGE_FIELD => 5,
                Api::API_LIMIT_FIELD => 25,
                NOSQLQuery::NOSQL_CURSOR_MODE_FIELD => 'cursor',
                NOSQLQuery::NOSQL_CURSOR_AFTER_FIELD => $after,
            ],
            $db,
            true
        );
    }

    public function testFindRejectsUnsafeCustomPipelineStages(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::atMost(1))->method('listIndexes')->willReturn(new ArrayIterator([]));
        $collection->expects(self::never())->method('countDocuments');
        $aggregateCalls = 0;
        $collection->expects(self::exactly(2))
            ->method('aggregate')
            ->willReturnCallback(function (array $pipeline, array $options) use (&$aggregateCalls) {
                $aggregateCalls++;
                if ($aggregateCalls === 1) {
                    self::assertSame('$count', array_key_first($pipeline[count($pipeline) - 1]));
                    return $this->createCursor([['total_count' => 0]]);
                }
                self::assertIsArray($options);
                self::assertTrue((function (array $pipeline): bool {
                    $serialized = json_encode($pipeline);
                    self::assertStringContainsString('$lookup', (string)$serialized);
                    self::assertStringContainsString('$group', (string)$serialized);
                    self::assertStringNotContainsString('$where', (string)$serialized);
                    self::assertStringNotContainsString('$function', (string)$serialized);
                    return true;
                })($pipeline));
                return $this->createCursor([]);
            });

        $db = $this->createMock(Database::class);
        $db->expects(self::once())->method('selectCollection')->willReturn($collection);

        NOSQLQuery::find(
            StubQueryModel::class,
            [
                '$lookup' => ['from' => 'other', 'localField' => 'name', 'foreignField' => 'name', 'as' => 'ref'],
                '$where' => 'this.age > 10',
                'custom_pipelines' => [
                    '$group_total' => ['_id' => '$name', 'total' => ['$sum' => 1]],
                    '$function_bad' => ['body' => 'return true;'],
                ],
            ],
            $db,
            true
        );
    }

    public function testDeleteManyUsesCollationHintAndMaxTimeOptions(): void
    {
        $deleteResult = $this->createMock(\MongoDB\DeleteResult::class);
        $deleteResult->expects(self::once())->method('getDeletedCount')->willReturn(4);

        $collection = $this->createMock(Collection::class);
        $collection->expects(self::never())->method('listIndexes');
        $collection->expects(self::once())
            ->method('deleteMany')
            ->with(
                self::isType('array'),
                self::callback(function (array $options): bool {
                    self::assertArrayHasKey('collation', $options);
                    self::assertSame(['locale' => 'es', 'strength' => 1], $options['collation']);
                    self::assertArrayHasKey('hint', $options);
                    self::assertSame(['name' => 1], $options['hint']);
                    self::assertSame(2500, $options['maxTimeMS']);
                    return true;
                })
            )
            ->willReturn($deleteResult);

        $db = $this->createMock(Database::class);
        $db->expects(self::once())->method('selectCollection')->willReturn($collection);

        $deleted = NOSQLQuery::deleteMany(
            StubQueryModel::class,
            [
                'name' => 'Joe',
                NOSQLQuery::NOSQL_COLLATION_FIELD => ['locale' => 'es', 'strength' => 1],
                NOSQLQuery::NOSQL_HINT_FIELD => ['name' => 1],
                NOSQLQuery::NOSQL_MAX_TIME_MS_FIELD => 2500,
            ],
            $db
        );

        self::assertSame(4, $deleted);
    }

    public function testStringFilterModesExactPrefixAndContains(): void
    {
        $containsFilters = $this->extractCountFilter([
            'name' => 'a.b',
            NOSQLQuery::NOSQL_STRING_MODE_FIELD => 'contains',
        ]);
        self::assertSame(['$regex' => 'a\\.b', '$options' => 'i'], $containsFilters['name']);

        $exactFilters = $this->extractCountFilter([
            'name' => 'a.b',
            NOSQLQuery::NOSQL_STRING_MODE_FIELD => 'exact',
        ]);
        self::assertSame(['$eq' => 'a.b'], $exactFilters['name']);

        $prefixFilters = $this->extractCountFilter([
            'name' => 'a.b',
            NOSQLQuery::NOSQL_STRING_MODE_FIELD => 'prefix',
        ]);
        self::assertSame(['$regex' => '^a\\.b', '$options' => 'i'], $prefixFilters['name']);
    }

    public function testObjectIdCriteriaValidationThrowsApiExceptionOnInvalidId(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::never())->method('listIndexes');
        $collection->expects(self::never())->method('countDocuments');
        $collection->expects(self::never())->method('aggregate');

        $db = $this->createMock(Database::class);
        $db->expects(self::once())->method('selectCollection')->willReturn($collection);

        $this->expectException(\PSFS\base\exception\ApiException::class);
        NOSQLQuery::find(
            StubQueryModel::class,
            ['_id' => 'invalid-object-id'],
            $db,
            true
        );
    }

    /**
     * @param array $criteria
     * @return array
     */
    private function extractCountFilter(array $criteria): array
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::never())->method('listIndexes');
        $collection->expects(self::once())
            ->method('countDocuments')
            ->with(self::isType('array'), self::isType('array'))
            ->willReturnCallback(function (array $filters): int {
                $this->capturedFilters = $filters;
                return 0;
            });

        $db = $this->createMock(Database::class);
        $db->expects(self::once())->method('selectCollection')->willReturn($collection);

        NOSQLQuery::count(StubQueryModel::class, $criteria, $db);

        /** @var array $filters */
        $filters = $this->capturedFilters;
        return $filters;
    }

    /**
     * @var array
     */
    private array $capturedFilters = [];

    /**
     * @param array<int,mixed> $rows
     * @return CursorInterface
     */
    private function createCursor(array $rows): CursorInterface
    {
        $position = 0;
        $cursor = $this->createMock(CursorInterface::class);
        $cursor->method('rewind')->willReturnCallback(function () use (&$position): void {
            $position = 0;
        });
        $cursor->method('valid')->willReturnCallback(function () use (&$position, $rows): bool {
            return $position < count($rows);
        });
        $cursor->method('current')->willReturnCallback(function () use (&$position, $rows): mixed {
            return $rows[$position] ?? null;
        });
        $cursor->method('key')->willReturnCallback(function () use (&$position): int {
            return $position;
        });
        $cursor->method('next')->willReturnCallback(function () use (&$position): void {
            $position++;
        });
        return $cursor;
    }
}

final class StubQueryModel extends NOSQLActiveRecord
{
    public function __construct()
    {
        $schema = new CollectionDto(false);
        $schema->name = 'stub_collection';
        $schema->id = 'stub';

        $name = new PropertyDto(false);
        $name->name = 'name';
        $name->type = NOSQLBase::NOSQL_TYPE_STRING;
        $name->required = false;

        $age = new PropertyDto(false);
        $age->name = 'age';
        $age->type = NOSQLBase::NOSQL_TYPE_INTEGER;
        $age->required = false;

        $createdAt = new PropertyDto(false);
        $createdAt->name = 'createdAt';
        $createdAt->type = NOSQLBase::NOSQL_TYPE_TIMESTAMP;
        $createdAt->required = false;

        $id = new PropertyDto(false);
        $id->name = '_id';
        $id->type = NOSQLBase::NOSQL_TYPE_STRING;
        $id->required = false;

        $schema->properties = [$name, $age, $createdAt, $id];
        $this->setSchema($schema);
        $this->setDomain('NOSQL');
    }

    protected function hydrate()
    {
    }
}
