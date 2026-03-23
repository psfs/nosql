<?php
namespace NOSQL\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Model\BSONDocument;
use NOSQL\Dto\Model\ResultsetDto;
use NOSQL\Models\base\NOSQLParserTrait;
use NOSQL\Services\Base\NOSQLBase;
use NOSQL\Services\Helpers\NOSQLApiHelper;
use PSFS\base\config\Config;
use PSFS\base\exception\ApiException;
use PSFS\base\types\Api;

final class NOSQLQuery
{
    const NOSQL_COLLATION_FIELD = '__collation';
    const NOSQL_HINT_FIELD = '__hint';
    const NOSQL_ALLOW_DISK_USE_FIELD = '__allowDiskUse';
    const NOSQL_MAX_TIME_MS_FIELD = '__maxTimeMS';
    const NOSQL_STRING_MODE_FIELD = '__string_mode';
    const NOSQL_CURSOR_MODE_FIELD = '__cursor_mode';
    const NOSQL_CURSOR_AFTER_FIELD = '__after';
    const NOSQL_IN_OPERATOR = '$in';
    const NOSQL_NOT_IN_OPERATOR = '$nin';
    const NOSQL_NOT_NULL_OPERATOR = '$ne';
    const NOSQL_EQUAL_OPERATOR = '$eq';
    const NOSQL_NOT_EQUAL_OPERATOR = '$ne';
    const NOSQL_LESS_OPERATOR = '$lt';
    const NOSQL_LESS_EQUAL_OPERATOR = '$lte';
    const NOSQL_GREATER_OPERATOR = '$gt';
    const NOSQL_GREATER_EQUAL_OPERATOR = '$gte';

    private const INDEX_CACHE_TTL_SECONDS = 60;

    private const CUSTOM_PIPELINE_WHITELIST = [
        '$match',
        '$project',
        '$addFields',
        '$lookup',
        '$unwind',
        '$group',
        '$sort',
        '$skip',
        '$limit',
        '$count',
        '$unset',
        '$replaceRoot',
    ];

    /**
     * @var array<string,array{ts:int,indexes:array}>
     */
    private static array $collectionIndexesCache = [];

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
        $con = NOSQLParserTrait::initConnection($model, $con);
        $collection = $con->selectCollection($model->getSchema()->name, self::buildDriverOptions());

        $objectId = self::toObjectId($pk);
        $result = $collection->findOne(['_id' => $objectId], self::buildDriverOptions());
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
        $con = NOSQLParserTrait::initConnection($model, $con);
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
        $con = NOSQLParserTrait::initConnection($model, $con);
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
        $con = NOSQLParserTrait::initConnection($model, $con);
        $collection = $con->selectCollection($model->getSchema()->name, self::buildDriverOptions());

        $plan = self::buildQueryPlan($criteria, $model, $collection);

        $resultSet = new ResultsetDto(false);
        $resultSet->page = $plan['page'];
        $resultSet->limit = $plan['limit'] ?? -1;
        $resultSet->count = self::resolveTotalCount($collection, $plan);

        $pipeline = self::buildFindPipeline($plan);
        $items = $collection->aggregate($pipeline, $plan['driverOptions']);
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
        $filters = self::parseCriteria($criteria, $model);
        $sort = self::parseSort($criteria);
        $projection = self::parseProjection($criteria);
        $customPipelines = self::parsePipelines($criteria);
        $page = self::parsePage($criteria);
        $limit = self::parseLimit($criteria);
        $skip = self::parseSkip($criteria, $page, $limit);

        $cursorFilter = self::parseCursorFilter($criteria, $sort);
        if (!empty($cursorFilter)) {
            if (empty($filters)) {
                $filters = $cursorFilter;
            } else {
                $filters = ['$and' => [$filters, $cursorFilter]];
            }
        }

        $driverOptions = self::buildDriverOptions($criteria);
        $driverOptions = self::appendCollationOptions($driverOptions, $criteria, $filters, $collection);

        return [
            'filters' => $filters,
            'sort' => $sort,
            'projection' => $projection,
            'customPipelines' => $customPipelines,
            'page' => $page,
            'limit' => $limit,
            'skip' => $skip,
            'driverOptions' => $driverOptions,
        ];
    }

    /**
     * @param array $plan
     * @return array
     */
    private static function buildFindPipeline(array $plan): array
    {
        $pipelines = [];

        if (!empty($plan['filters'])) {
            $pipelines[] = ['$match' => $plan['filters']];
        }

        if (!empty($plan['sort'])) {
            $pipelines[] = ['$sort' => $plan['sort']];
        }

        if (!empty($plan['projection'])) {
            $pipelines[] = ['$project' => $plan['projection']];
        }

        foreach ($plan['customPipelines'] as $customPipeline) {
            $pipelines[] = $customPipeline;
        }

        if (($plan['skip'] ?? 0) > 0) {
            $pipelines[] = ['$skip' => (int)$plan['skip']];
        }

        if (null !== $plan['limit']) {
            $pipelines[] = ['$limit' => (int)$plan['limit']];
        }

        return $pipelines;
    }

    /**
     * @param Collection $collection
     * @param array $plan
     * @return int
     */
    private static function resolveTotalCount(Collection $collection, array $plan): int
    {
        if (empty($plan['customPipelines'])) {
            return (int)$collection->countDocuments($plan['filters'], $plan['driverOptions']);
        }

        $countPipeline = [];
        if (!empty($plan['filters'])) {
            $countPipeline[] = ['$match' => $plan['filters']];
        }
        foreach ($plan['customPipelines'] as $customPipeline) {
            $countPipeline[] = $customPipeline;
        }
        $countPipeline[] = ['$count' => 'total_count'];

        $cursor = $collection->aggregate($countPipeline, $plan['driverOptions']);
        foreach ($cursor as $item) {
            if ($item instanceof BSONDocument) {
                $raw = $item->getArrayCopy();
                return (int)($raw['total_count'] ?? 0);
            }
            if (is_array($item)) {
                return (int)($item['total_count'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * @param array $criteria
     * @return int
     */
    private static function parsePage(array $criteria): int
    {
        $page = (int)($criteria[Api::API_PAGE_FIELD] ?? 1);
        return $page > 0 ? $page : 1;
    }

    /**
     * @param array $criteria
     * @return int|null
     */
    private static function parseLimit(array $criteria): ?int
    {
        $defaultLimit = (int)Config::getParam('pagination.limit', 50);
        $limit = (int)($criteria[Api::API_LIMIT_FIELD] ?? $defaultLimit);

        if ($limit < 0) {
            return null;
        }

        return $limit;
    }

    /**
     * @param array $criteria
     * @param int $page
     * @param int|null $limit
     * @return int
     */
    private static function parseSkip(array $criteria, int $page, ?int $limit): int
    {
        if (null === $limit) {
            return 0;
        }
        if (self::isCursorMode($criteria)) {
            return 0;
        }
        return ($page - 1) * $limit;
    }

    /**
     * @param array $criteria
     * @return array
     */
    private static function parseSort(array $criteria): array
    {
        if (!array_key_exists(Api::API_ORDER_FIELD, $criteria)) {
            return [];
        }

        $rawSort = $criteria[Api::API_ORDER_FIELD];
        if (is_string($rawSort)) {
            $decoded = json_decode($rawSort, true);
            $rawSort = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($rawSort)) {
            return [];
        }

        $sort = [];
        foreach ($rawSort as $field => $direction) {
            $sort[$field] = ((int)$direction === -1) ? -1 : 1;
        }

        return $sort;
    }

    /**
     * @param array $criteria
     * @return array
     */
    private static function parseProjection(array $criteria): array
    {
        if (!array_key_exists(Api::API_FIELDS_RESULT_FIELD, $criteria)) {
            return [];
        }

        $rawFields = $criteria[Api::API_FIELDS_RESULT_FIELD];
        if (is_string($rawFields)) {
            $rawFields = explode(',', $rawFields);
        }

        if (!is_array($rawFields) || empty($rawFields)) {
            return [];
        }

        $projection = ['_id' => 1];
        foreach ($rawFields as $field) {
            $name = trim((string)$field);
            if ($name !== '' && !str_starts_with($name, '__')) {
                $projection[$name] = 1;
            }
        }

        return count($projection) > 1 ? $projection : [];
    }

    /**
     * @param array $criteria
     * @param array $sort
     * @return array
     */
    private static function parseCursorFilter(array $criteria, array $sort): array
    {
        if (!self::isCursorMode($criteria) || empty($sort)) {
            return [];
        }

        if (!array_key_exists(self::NOSQL_CURSOR_AFTER_FIELD, $criteria)) {
            return [];
        }

        $rawAfter = $criteria[self::NOSQL_CURSOR_AFTER_FIELD];
        if (is_string($rawAfter)) {
            $decoded = json_decode($rawAfter, true);
            $rawAfter = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rawAfter) || empty($rawAfter)) {
            return [];
        }

        $sortFields = array_keys($sort);
        $ors = [];
        $equals = [];
        foreach ($sortFields as $field) {
            if (!array_key_exists($field, $rawAfter)) {
                break;
            }
            $direction = $sort[$field];
            $operator = $direction === 1 ? '$gt' : '$lt';
            $branch = $equals;
            $branch[$field] = [$operator => $rawAfter[$field]];
            $ors[] = $branch;
            $equals[$field] = ['$eq' => $rawAfter[$field]];
        }

        if (empty($ors)) {
            return [];
        }

        if (count($ors) === 1) {
            return $ors[0];
        }

        return ['$or' => $ors];
    }

    /**
     * @param array $criteria
     * @param NOSQLActiveRecord $model
     * @return array
     */
    private static function parseCriteria(array $criteria, NOSQLActiveRecord $model): array
    {
        $filters = [];

        if (array_key_exists(Api::API_COMBO_FIELD, $criteria) && !empty($criteria[Api::API_COMBO_FIELD])) {
            $filters['$text'] = ['$search' => $criteria[Api::API_COMBO_FIELD]];
        }

        foreach ($model->getSchema()->properties as $property) {
            if (array_key_exists($property->name, $criteria)) {
                $filters[$property->name] = self::composeFilter($criteria, $property);
            }
        }

        return $filters;
    }

    /**
     * @param array $criteria
     * @return array
     */
    private static function parsePipelines(array $criteria): array
    {
        $pipelines = [];

        foreach ($criteria as $key => $criterion) {
            if (!is_string($key) || !str_starts_with($key, '$')) {
                continue;
            }

            $stage = self::normalizePipelineStage($key);
            if (!in_array($stage, self::CUSTOM_PIPELINE_WHITELIST, true)) {
                continue;
            }

            $pipelines[] = [$stage => $criterion];
        }

        if (array_key_exists('custom_pipelines', $criteria) && is_array($criteria['custom_pipelines'])) {
            foreach ($criteria['custom_pipelines'] as $pipeline => $rules) {
                if (!is_string($pipeline)) {
                    continue;
                }

                $command = explode('_', $pipeline, 2)[0];
                $stage = self::normalizePipelineStage($command);
                if (!in_array($stage, self::CUSTOM_PIPELINE_WHITELIST, true)) {
                    continue;
                }

                $pipelines[] = [$stage => $rules];
            }
        }

        return $pipelines;
    }

    /**
     * @param string $stage
     * @return string
     */
    private static function normalizePipelineStage(string $stage): string
    {
        $normalized = '$' . ltrim($stage, '$');
        if (strtolower($normalized) === '$replaceroot') {
            return '$replaceRoot';
        }
        return $normalized;
    }

    /**
     * @param array $criteria
     * @param \NOSQL\Dto\PropertyDto $property
     * @return array|bool|float|int|mixed
     */
    private static function composeFilter(array $criteria, \NOSQL\Dto\PropertyDto $property)
    {
        $filterValue = $criteria[$property->name];

        if ('_id' === $property->name) {
            return self::composeObjectIdFilter($filterValue);
        }

        $matchOperator = is_array($filterValue) && count($filterValue) > 0 ? $filterValue[0] : self::NOSQL_EQUAL_OPERATOR;
        if (is_array($filterValue)) {
            if (in_array($matchOperator, [
                self::NOSQL_NOT_NULL_OPERATOR,
                self::NOSQL_IN_OPERATOR,
                self::NOSQL_NOT_IN_OPERATOR,
                self::NOSQL_EQUAL_OPERATOR,
                self::NOSQL_NOT_EQUAL_OPERATOR,
                self::NOSQL_LESS_OPERATOR,
                self::NOSQL_LESS_EQUAL_OPERATOR,
                self::NOSQL_GREATER_OPERATOR,
                self::NOSQL_GREATER_EQUAL_OPERATOR,
            ], true)) {
                $operator = array_shift($filterValue);
                $value = array_shift($filterValue);
                if (in_array($operator, [self::NOSQL_IN_OPERATOR, self::NOSQL_NOT_IN_OPERATOR], true) && !is_array($value)) {
                    $value = [$value];
                }
                return [$operator => $value];
            }

            return [self::NOSQL_IN_OPERATOR => $filterValue];
        }

        if (in_array($filterValue, [self::NOSQL_NOT_NULL_OPERATOR], true)) {
            return [$filterValue => null];
        }

        if (in_array($property->type, [
            NOSQLBase::NOSQL_TYPE_BOOLEAN,
            NOSQLBase::NOSQL_TYPE_INTEGER,
            NOSQLBase::NOSQL_TYPE_DOUBLE,
            NOSQLBase::NOSQL_TYPE_LONG,
        ], true)) {
            if ($property->type === NOSQLBase::NOSQL_TYPE_BOOLEAN) {
                $filterValue = in_array($filterValue, ['1', 1, 'true', true], true);
            } elseif (NOSQLBase::NOSQL_TYPE_INTEGER === $property->type) {
                $filterValue = (int)$filterValue;
            } else {
                $filterValue = (float)$filterValue;
            }
            return [self::NOSQL_EQUAL_OPERATOR => $filterValue];
        }

        $stringMode = strtolower((string)($criteria[self::NOSQL_STRING_MODE_FIELD]
            ?? Config::getParam('nosql.query.stringMode', 'contains')));
        $stringValue = preg_quote((string)$filterValue, '/');
        switch ($stringMode) {
            case 'exact':
                return [self::NOSQL_EQUAL_OPERATOR => (string)$filterValue];
            case 'prefix':
                return ['$regex' => '^' . $stringValue, '$options' => 'i'];
            default:
                return ['$regex' => $stringValue, '$options' => 'i'];
        }
    }

    /**
     * @param mixed $filterValue
     * @return array
     */
    private static function composeObjectIdFilter(mixed $filterValue): array
    {
        if (is_array($filterValue) && count($filterValue) > 0) {
            $operator = array_shift($filterValue);
            $value = array_shift($filterValue);
            if (in_array($operator, [self::NOSQL_IN_OPERATOR, self::NOSQL_NOT_IN_OPERATOR], true) && is_array($value)) {
                $normalized = [];
                foreach ($value as $item) {
                    $normalized[] = self::toObjectId($item);
                }
                return [$operator => $normalized];
            }
            return [$operator => self::toObjectId($value)];
        }

        return [self::NOSQL_EQUAL_OPERATOR => self::toObjectId($filterValue)];
    }

    /**
     * @param mixed $value
     * @return ObjectId
     */
    private static function toObjectId(mixed $value): ObjectId
    {
        if ($value instanceof ObjectId) {
            return $value;
        }

        try {
            return new ObjectId((string)$value);
        } catch (\Throwable $exception) {
            throw new ApiException(t('Invalid ObjectId format'), 400);
        }
    }

    /**
     * @param array $driverOptions
     * @param array $criteria
     * @param array $filters
     * @param Collection $collection
     * @return array
     */
    private static function appendCollationOptions(
        array $driverOptions,
        array $criteria,
        array $filters,
        Collection $collection
    ): array {
        if (array_key_exists(self::NOSQL_COLLATION_FIELD, $criteria) && is_array($criteria[self::NOSQL_COLLATION_FIELD])) {
            $driverOptions['collation'] = $criteria[self::NOSQL_COLLATION_FIELD];
            return $driverOptions;
        }

        $indexMeta = self::getCollectionIndexMeta($collection);
        if (empty($filters) || empty($indexMeta)) {
            return $driverOptions;
        }

        $filterKeys = array_keys($filters);
        foreach ($indexMeta as $meta) {
            $indexKeys = $meta['keys'] ?? [];
            if (!empty($indexKeys) && empty(array_diff($indexKeys, $filterKeys)) && isset($meta['collation'])) {
                $driverOptions['collation'] = $meta['collation'];
                break;
            }
        }

        return $driverOptions;
    }

    /**
     * @param Collection $collection
     * @return array
     */
    private static function getCollectionIndexMeta(Collection $collection): array
    {
        $cacheKey = self::collectionIdentifier($collection);
        $now = time();
        if (isset(self::$collectionIndexesCache[$cacheKey])) {
            $cacheEntry = self::$collectionIndexesCache[$cacheKey];
            if (($cacheEntry['ts'] + self::INDEX_CACHE_TTL_SECONDS) > $now) {
                return $cacheEntry['indexes'];
            }
        }

        $indexes = [];
        foreach ($collection->listIndexes(self::buildDriverOptions()) as $index) {
            $indexInfo = $index->__debugInfo();
            $keys = array_keys((array)($index['key'] ?? []));
            $entry = ['keys' => $keys];
            if (array_key_exists('collation', $indexInfo)) {
                $collation = $indexInfo['collation'];
                $entry['collation'] = [
                    'locale' => $collation['locale'] ?? 'en',
                    'strength' => $collation['strength'] ?? 1,
                ];
            }
            $indexes[] = $entry;
        }

        self::$collectionIndexesCache[$cacheKey] = [
            'ts' => $now,
            'indexes' => $indexes,
        ];

        return $indexes;
    }

    /**
     * @param Collection $collection
     * @return string
     */
    private static function collectionIdentifier(Collection $collection): string
    {
        $database = method_exists($collection, 'getDatabaseName') ? $collection->getDatabaseName() : 'db';
        $name = method_exists($collection, 'getCollectionName') ? $collection->getCollectionName() : 'collection';
        return $database . '.' . $name;
    }

    /**
     * @return array
     */
    private static function buildDriverOptions(array $criteria = []): array
    {
        $options = NOSQLApiHelper::getReadPreferenceOptions();
        $maxTimeMs = (int)($criteria[self::NOSQL_MAX_TIME_MS_FIELD] ?? Config::getParam('nosql.query.maxTimeMS', 0));
        if ($maxTimeMs > 0) {
            $options['maxTimeMS'] = $maxTimeMs;
        }

        if (array_key_exists(self::NOSQL_HINT_FIELD, $criteria)) {
            $hint = $criteria[self::NOSQL_HINT_FIELD];
            if (is_array($hint) || is_string($hint)) {
                $options['hint'] = $hint;
            }
        }

        if (array_key_exists(self::NOSQL_ALLOW_DISK_USE_FIELD, $criteria)) {
            $options['allowDiskUse'] = (bool)$criteria[self::NOSQL_ALLOW_DISK_USE_FIELD];
        }

        return $options;
    }

    /**
     * @param array $criteria
     * @return bool
     */
    private static function isCursorMode(array $criteria): bool
    {
        $mode = strtolower((string)($criteria[self::NOSQL_CURSOR_MODE_FIELD] ?? 'offset'));
        return in_array($mode, ['cursor', 'seek', 'after'], true);
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
            } else {
                $model->feed($row, true);
                yield $model->getDtoCopy(true);
            }
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
}
