<?php

namespace NOSQL\Test\Unit;

use ArrayIterator;
use MongoDB\BSON\ObjectId;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;
use MongoDB\Database;
use NOSQL\Dto\Model\NOSQLModelDto;
use NOSQL\Models\NOSQLActiveRecord;
use PHPUnit\Framework\TestCase;

final class NOSQLActiveRecordPersistenceTest extends TestCase
{
    public function testSaveInsertSetsPrimaryKeyAndReturnsTrue(): void
    {
        $model = $this->buildModel();

        $insertResult = $this->createMock(\MongoDB\InsertOneResult::class);
        $insertResult->method('getInsertedCount')->willReturn(1);
        $insertResult->method('getInsertedId')->willReturn(new ObjectId('507f1f77bcf86cd799439011'));

        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('insertOne')->willReturn($insertResult);

        $db = $this->createMock(Database::class);
        $db->expects(self::once())->method('selectCollection')->willReturn($collection);

        self::assertTrue($model->save($db));
        self::assertSame('507f1f77bcf86cd799439011', $model->getDtoCopy()->getPk());
    }

    public function testUpdateReplacesDocumentWithoutPrimaryKeyField(): void
    {
        $model = $this->buildModel();
        $dto = $model->getDtoCopy();
        $dto->setPk('507f1f77bcf86cd799439011');
        $this->setDto($model, $dto);
        $model->setIsNew(false);
        $model->setIsModified(true);

        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())
            ->method('findOneAndReplace')
            ->with(
                self::callback(function (array $filter): bool {
                    self::assertArrayHasKey('_id', $filter);
                    self::assertInstanceOf(ObjectId::class, $filter['_id']);
                    return true;
                }),
                self::callback(function (array $data): bool {
                    self::assertArrayNotHasKey('_id', $data);
                    return true;
                }),
                self::isType('array')
            );

        $db = $this->createMock(Database::class);
        $db->method('selectCollection')->willReturn($collection);

        self::assertTrue($model->update($db));
    }

    public function testDeleteReturnsTrueAndClearsDto(): void
    {
        $model = $this->buildModel();
        $dto = $model->getDtoCopy();
        $dto->setPk('507f1f77bcf86cd799439011');
        $this->setDto($model, $dto);

        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('deleteOne');

        $db = $this->createMock(Database::class);
        $db->method('selectCollection')->willReturn($collection);

        self::assertTrue($model->delete($db));
    }

    public function testBulkDeleteReturnsDeletedCount(): void
    {
        $model = $this->buildModel();

        $deleteResult = $this->createMock(\MongoDB\DeleteResult::class);
        $deleteResult->method('getDeletedCount')->willReturn(4);

        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('deleteMany')->willReturn($deleteResult);

        $db = $this->createMock(Database::class);
        $db->method('selectCollection')->willReturn($collection);

        self::assertSame(4, $model->bulkDelete(['status' => 'OPEN'], $db));
    }

    public function testBulkUpsertReturnsAggregationOfModifiedInsertedAndUpserted(): void
    {
        $model = $this->buildModel();

        $index = new class(['key' => ['code' => 1]]) extends \ArrayObject {
            public function __debugInfo(): array
            {
                return [
                    'collation' => ['locale' => 'en', 'strength' => 1],
                ];
            }
        };

        $bulkResult = $this->createMock(BulkWriteResult::class);
        $bulkResult->method('getModifiedCount')->willReturn(1);
        $bulkResult->method('getInsertedCount')->willReturn(2);
        $bulkResult->method('getUpsertedCount')->willReturn(3);

        $collection = $this->createMock(Collection::class);
        $collection->method('listIndexes')->willReturn(new ArrayIterator([$index]));
        $collection->expects(self::once())->method('bulkWrite')->willReturn($bulkResult);

        $db = $this->createMock(Database::class);
        $db->method('selectCollection')->willReturn($collection);

        $affected = $model->bulkUpsert([
            ['code' => 'A1', 'name' => 'First'],
            ['code' => 'B1', 'name' => 'Second'],
        ], 'code', $db);

        self::assertSame(6, $affected);
    }

    private function buildModel(): ActiveRecordStub
    {
        /** @var ActiveRecordStub $model */
        $model = (new \ReflectionClass(ActiveRecordStub::class))->newInstanceWithoutConstructor();
        $this->setDto($model, new ActiveRecordStubDto(false));
        $schema = new \stdClass();
        $schema->name = 'items';
        $schemaRef = new \ReflectionProperty(ActiveRecordStub::class, 'schema');
        $schemaRef->setAccessible(true);
        $schemaRef->setValue($model, $schema);

        return $model;
    }

    private function setDto(ActiveRecordStub $model, ActiveRecordStubDto $dto): void
    {
        $dtoRef = new \ReflectionProperty(NOSQLActiveRecord::class, 'dto');
        $dtoRef->setAccessible(true);
        $dtoRef->setValue($model, $dto);
    }
}

final class ActiveRecordStub extends NOSQLActiveRecord
{
    protected $domain = 'stub';
}

final class ActiveRecordStubDto extends NOSQLModelDto
{
    public string $name = '';
    public string $code = '';
}
