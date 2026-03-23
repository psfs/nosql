<?php

namespace NOSQL\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Model\BSONDocument;
use NOSQL\Dto\Model\ResultsetDto;
use NOSQL\Models\Query\NOSQLQueryPlanner;
use PSFS\base\exception\ApiException;

final class NOSQLQuery
{
    public const NOSQL_COLLATION_FIELD = '__collation';
    public const NOSQL_HINT_FIELD = '__hint';
    public const NOSQL_ALLOW_DISK_USE_FIELD = '__allowDiskUse';
    public const NOSQL_MAX_TIME_MS_FIELD = '__maxTimeMS';
    public const NOSQL_STRING_MODE_FIELD = '__string_mode';
    public const NOSQL_CURSOR_MODE_FIELD = '__cursor_mode';
    public const NOSQL_CURSOR_AFTER_FIELD = '__after';
    public const NOSQL_IN_OPERATOR = '$in';
    public const NOSQL_NOT_IN_OPERATOR = '$nin';
    public const NOSQL_NOT_NULL_OPERATOR = '$ne';
    public const NOSQL_EQUAL_OPERATOR = '$eq';
    public const NOSQL_NOT_EQUAL_OPERATOR = '$ne';
    public const NOSQL_LESS_OPERATOR = '$lt';
    public const NOSQL_LESS_EQUAL_OPERATOR = '$lte';
    public const NOSQL_GREATER_OPERATOR = '$gt';
    public const NOSQL_GREATER_EQUAL_OPERATOR = '$gte';

    private static ?NOSQLQueryPlanner $planner = null;

    /**
     * @param string $modelName
     * @param string $pk
     * @param Database|null $con
     * @return mixed
     * @throws ApiException
     */
    public static function findPk($modelName, $pk, Database $con = null)
    {
        /** @var NOSQLActiveRecord $model */
        $model = new $modelName();
        $con = $model::initConnection($model, $con);
        $collection = $con->selectCollection($model->getSchema()->name, self::buildDriverOptions());

        $result = $collection->findOne(['_id' => self::toObjectId($pk)], self::buildDriverOptions());
        if (null !== $result) {
            $model->feed($result->getArrayCopy());
            return $model;
        }

        throw new ApiException(t('Document not found'), 404);
    }

    /**
     * @param string $modelName
     * @param array $criteria
     * @param Database|null $con
     * @return ResultsetDto
     */
    public static function count($modelName, array $criteria, Database $con = null)
    {
        /** @var NOSQLActiveRecord $model */
        $model = new $modelName();
        $con = $model::initConnection($model, $con);
        $collection = $con->selectCollection($model->getSchema()->name, self::buildDriverOptions());

        $plan = self::buildQueryPlan($criteria, $model, $collection);
        $resultSet = new ResultsetDto(false);
        $resultSet->limit = $plan['limit'] ?? $resultSet->limit;
        $resultSet->page = $plan['page'];

        $total = self::resolveTotalCount($collection, $plan);
        $resultSet->count = $total;
        $resultSet->items[] = $total;

        return $resultSet;
    }

    /**
     * @param string $modelName
     * @param array $criteria
     * @param Database|null $con
     * @return int
     */
    public static function deleteMany($modelName, array $criteria, Database $con = null)
    {
        /** @var NOSQLActiveRecord $model */
        $model = new $modelName();
        $con = $model::initConnection($model, $con);
        $collection = $con->selectCollection($model->getSchema()->name, self::buildDriverOptions());

        $plan = self::buildQueryPlan($criteria, $model, $collection);
        $result = $collection->deleteMany($plan['filters'], $plan['driverOptions']);

        return $result->getDeletedCount();
    }

    /**
     * @param string $modelName
     * @param array $criteria
     * @param Database|null $con
     * @param bool $asArray
     * @return ResultsetDto
     * @throws \NOSQL\Exceptions\NOSQLValidationException
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function find($modelName, array $criteria, Database $con = null, $asArray = false)
    {
        /** @var NOSQLActiveRecord $model */
        $model = new $modelName();
        $con = $model::initConnection($model, $con);
        $collection = $con->selectCollection($model->getSchema()->name, self::buildDriverOptions());

        $plan = self::buildQueryPlan($criteria, $model, $collection);

        $resultSet = new ResultsetDto(false);
        $resultSet->page = $plan['page'];
        $resultSet->limit = $plan['limit'] ?? -1;
        $resultSet->count = self::resolveTotalCount($collection, $plan);

        $items = $collection->aggregate(self::buildFindPipeline($plan), $plan['driverOptions']);
        $resultSet->items = self::generateItems($model, self::getItemIterator($items), $asArray);

        return $resultSet;
    }

    /**
     * @param array $criteria
     * @param NOSQLActiveRecord $model
     * @param Collection $collection
     * @return array
     */
    private static function buildQueryPlan(array $criteria, NOSQLActiveRecord $model, Collection $collection): array
    {
        return self::planner()->buildQueryPlan($criteria, $model, $collection);
    }

    /**
     * @param array $plan
     * @return array
     */
    private static function buildFindPipeline(array $plan): array
    {
        return self::planner()->buildFindPipeline($plan);
    }

    /**
     * @param Collection $collection
     * @param array $plan
     * @return int
     */
    private static function resolveTotalCount(Collection $collection, array $plan): int
    {
        return self::planner()->resolveTotalCount($collection, $plan);
    }

    /**
     * @param mixed $value
     * @return ObjectId
     */
    private static function toObjectId(mixed $value): ObjectId
    {
        return self::planner()->toObjectId($value);
    }

    /**
     * @return array
     */
    private static function buildDriverOptions(array $criteria = []): array
    {
        return self::planner()->buildDriverOptions($criteria);
    }

    /**
     * @param NOSQLActiveRecord $model
     * @param iterable $items
     * @param bool $asArray
     * @return \Generator
     */
    private static function generateItems($model, iterable $items, $asArray = false)
    {
        foreach ($items as $item) {
            $row = $item instanceof BSONDocument ? $item->getArrayCopy() : (array)$item;
            if ($asArray) {
                yield $row;
                continue;
            }
            $model->feed($row, true);
            yield $model->getDtoCopy(true);
        }
    }

    /**
     * @param iterable $items
     * @return \Generator
     */
    private static function getItemIterator(iterable $items)
    {
        foreach ($items as $item) {
            yield $item;
        }
    }

    private static function planner(): NOSQLQueryPlanner
    {
        if (null === self::$planner) {
            self::$planner = new NOSQLQueryPlanner();
        }

        return self::$planner;
    }
}
