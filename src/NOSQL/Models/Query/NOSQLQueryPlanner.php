<?php

namespace NOSQL\Models\Query;

use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;
use NOSQL\Models\NOSQLActiveRecord;
use NOSQL\Models\NOSQLQuery;
use NOSQL\Services\Base\NOSQLBase;
use NOSQL\Services\Helpers\NOSQLApiHelper;
use PSFS\base\config\Config;
use PSFS\base\exception\ApiException;
use PSFS\base\types\Api;

final class NOSQLQueryPlanner
{
    private const INDEX_CACHE_TTL_SECONDS = 60;

    private const CUSTOM_PIPELINE_WHITELIST = [
        '$match', '$project', '$addFields', '$lookup', '$unwind', '$group',
        '$sort', '$skip', '$limit', '$count', '$unset', '$replaceRoot',
    ];

    /**
     * @var array<string,array{ts:int,indexes:array}>
     */
    private static array $collectionIndexesCache = [];

    /**
     * @param array $criteria
     * @param NOSQLActiveRecord $model
     * @param Collection $collection
     * @return array
     */
    public function buildQueryPlan(array $criteria, NOSQLActiveRecord $model, Collection $collection): array
    {
        $filters = $this->parseCriteria($criteria, $model);
        $sort = $this->parseSort($criteria);
        $projection = $this->parseProjection($criteria);
        $customPipelines = $this->parsePipelines($criteria);
        $page = $this->parsePage($criteria);
        $limit = $this->parseLimit($criteria);
        $skip = $this->parseSkip($criteria, $page, $limit);

        $cursorFilter = $this->parseCursorFilter($criteria, $sort);
        if (!empty($cursorFilter)) {
            $filters = empty($filters) ? $cursorFilter : ['$and' => [$filters, $cursorFilter]];
        }

        $driverOptions = $this->buildDriverOptions($criteria);
        $driverOptions = $this->appendCollationOptions($driverOptions, $criteria, $filters, $collection);

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
    public function buildFindPipeline(array $plan): array
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
    public function resolveTotalCount(Collection $collection, array $plan): int
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
                return (int)($item->getArrayCopy()['total_count'] ?? 0);
            }
            if (is_array($item)) {
                return (int)($item['total_count'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * @param mixed $value
     * @return ObjectId
     */
    public function toObjectId(mixed $value): ObjectId
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
     * @param array $criteria
     * @return array
     */
    public function buildDriverOptions(array $criteria = []): array
    {
        $options = NOSQLApiHelper::getReadPreferenceOptions();
        $maxTimeMs = (int)($criteria[NOSQLQuery::NOSQL_MAX_TIME_MS_FIELD] ?? Config::getParam('nosql.query.maxTimeMS', 0));
        if ($maxTimeMs > 0) {
            $options['maxTimeMS'] = $maxTimeMs;
        }
        if (array_key_exists(NOSQLQuery::NOSQL_HINT_FIELD, $criteria)) {
            $hint = $criteria[NOSQLQuery::NOSQL_HINT_FIELD];
            if (is_array($hint) || is_string($hint)) {
                $options['hint'] = $hint;
            }
        }
        if (array_key_exists(NOSQLQuery::NOSQL_ALLOW_DISK_USE_FIELD, $criteria)) {
            $options['allowDiskUse'] = (bool)$criteria[NOSQLQuery::NOSQL_ALLOW_DISK_USE_FIELD];
        }
        return $options;
    }

    private function parsePage(array $criteria): int
    {
        $page = (int)($criteria[Api::API_PAGE_FIELD] ?? 1);
        return $page > 0 ? $page : 1;
    }

    private function parseLimit(array $criteria): ?int
    {
        $defaultLimit = (int)Config::getParam('pagination.limit', 50);
        $limit = (int)($criteria[Api::API_LIMIT_FIELD] ?? $defaultLimit);
        return $limit < 0 ? null : $limit;
    }

    private function parseSkip(array $criteria, int $page, ?int $limit): int
    {
        if (null === $limit || $this->isCursorMode($criteria)) {
            return 0;
        }
        return ($page - 1) * $limit;
    }

    private function parseSort(array $criteria): array
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

    private function parseProjection(array $criteria): array
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

    private function parseCursorFilter(array $criteria, array $sort): array
    {
        if (!$this->isCursorMode($criteria) || empty($sort) || !array_key_exists(NOSQLQuery::NOSQL_CURSOR_AFTER_FIELD, $criteria)) {
            return [];
        }
        $rawAfter = $criteria[NOSQLQuery::NOSQL_CURSOR_AFTER_FIELD];
        if (is_string($rawAfter)) {
            $decoded = json_decode($rawAfter, true);
            $rawAfter = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rawAfter) || empty($rawAfter)) {
            return [];
        }

        $ors = [];
        $equals = [];
        foreach (array_keys($sort) as $field) {
            if (!array_key_exists($field, $rawAfter)) {
                break;
            }
            $operator = ($sort[$field] === 1) ? '$gt' : '$lt';
            $branch = $equals;
            $branch[$field] = [$operator => $rawAfter[$field]];
            $ors[] = $branch;
            $equals[$field] = ['$eq' => $rawAfter[$field]];
        }

        if (empty($ors)) {
            return [];
        }
        return count($ors) === 1 ? $ors[0] : ['$or' => $ors];
    }

    private function parseCriteria(array $criteria, NOSQLActiveRecord $model): array
    {
        $filters = [];
        if (!empty($criteria[Api::API_COMBO_FIELD] ?? null)) {
            $filters['$text'] = ['$search' => $criteria[Api::API_COMBO_FIELD]];
        }
        foreach ($model->getSchema()->properties as $property) {
            if (array_key_exists($property->name, $criteria)) {
                $filters[$property->name] = $this->composeFilter($criteria, $property);
            }
        }
        return $filters;
    }

    private function parsePipelines(array $criteria): array
    {
        $pipelines = [];
        foreach ($criteria as $key => $criterion) {
            if (!is_string($key) || !str_starts_with($key, '$')) {
                continue;
            }
            $stage = $this->normalizePipelineStage($key);
            if (!in_array($stage, self::CUSTOM_PIPELINE_WHITELIST, true)) {
                continue;
            }
            $pipelines[] = [$stage => $criterion];
        }
        if (is_array($criteria['custom_pipelines'] ?? null)) {
            foreach ($criteria['custom_pipelines'] as $pipeline => $rules) {
                if (!is_string($pipeline)) {
                    continue;
                }
                $stage = $this->normalizePipelineStage(explode('_', $pipeline, 2)[0]);
                if (!in_array($stage, self::CUSTOM_PIPELINE_WHITELIST, true)) {
                    continue;
                }
                $pipelines[] = [$stage => $rules];
            }
        }
        return $pipelines;
    }

    private function normalizePipelineStage(string $stage): string
    {
        $normalized = '$' . ltrim($stage, '$');
        return strtolower($normalized) === '$replaceroot' ? '$replaceRoot' : $normalized;
    }

    private function composeFilter(array $criteria, \NOSQL\Dto\PropertyDto $property): mixed
    {
        $filterValue = $criteria[$property->name];
        if ('_id' === $property->name) {
            return $this->composeObjectIdFilter($filterValue);
        }

        if (is_array($filterValue)) {
            $operator = $filterValue[0] ?? NOSQLQuery::NOSQL_EQUAL_OPERATOR;
            if (in_array($operator, [
                NOSQLQuery::NOSQL_NOT_NULL_OPERATOR,
                NOSQLQuery::NOSQL_IN_OPERATOR,
                NOSQLQuery::NOSQL_NOT_IN_OPERATOR,
                NOSQLQuery::NOSQL_EQUAL_OPERATOR,
                NOSQLQuery::NOSQL_NOT_EQUAL_OPERATOR,
                NOSQLQuery::NOSQL_LESS_OPERATOR,
                NOSQLQuery::NOSQL_LESS_EQUAL_OPERATOR,
                NOSQLQuery::NOSQL_GREATER_OPERATOR,
                NOSQLQuery::NOSQL_GREATER_EQUAL_OPERATOR,
            ], true)) {
                array_shift($filterValue);
                $value = array_shift($filterValue);
                if (in_array($operator, [NOSQLQuery::NOSQL_IN_OPERATOR, NOSQLQuery::NOSQL_NOT_IN_OPERATOR], true) && !is_array($value)) {
                    $value = [$value];
                }
                return [$operator => $value];
            }
            return [NOSQLQuery::NOSQL_IN_OPERATOR => $filterValue];
        }

        if (in_array($filterValue, [NOSQLQuery::NOSQL_NOT_NULL_OPERATOR], true)) {
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
            } elseif ($property->type === NOSQLBase::NOSQL_TYPE_INTEGER) {
                $filterValue = (int)$filterValue;
            } else {
                $filterValue = (float)$filterValue;
            }
            return [NOSQLQuery::NOSQL_EQUAL_OPERATOR => $filterValue];
        }

        $mode = strtolower((string)($criteria[NOSQLQuery::NOSQL_STRING_MODE_FIELD] ?? Config::getParam('nosql.query.stringMode', 'contains')));
        $stringValue = preg_quote((string)$filterValue, '/');
        return match ($mode) {
            'exact' => [NOSQLQuery::NOSQL_EQUAL_OPERATOR => (string)$filterValue],
            'prefix' => ['$regex' => '^' . $stringValue, '$options' => 'i'],
            default => ['$regex' => $stringValue, '$options' => 'i'],
        };
    }

    private function composeObjectIdFilter(mixed $filterValue): array
    {
        if (!is_array($filterValue) || count($filterValue) === 0) {
            return [NOSQLQuery::NOSQL_EQUAL_OPERATOR => $this->toObjectId($filterValue)];
        }
        $operator = array_shift($filterValue);
        $value = array_shift($filterValue);
        if (in_array($operator, [NOSQLQuery::NOSQL_IN_OPERATOR, NOSQLQuery::NOSQL_NOT_IN_OPERATOR], true) && is_array($value)) {
            return [$operator => array_map(fn ($item) => $this->toObjectId($item), $value)];
        }
        return [$operator => $this->toObjectId($value)];
    }

    private function appendCollationOptions(array $driverOptions, array $criteria, array $filters, Collection $collection): array
    {
        if (is_array($criteria[NOSQLQuery::NOSQL_COLLATION_FIELD] ?? null)) {
            $driverOptions['collation'] = $criteria[NOSQLQuery::NOSQL_COLLATION_FIELD];
            return $driverOptions;
        }

        if (empty($filters)) {
            return $driverOptions;
        }

        $indexMeta = $this->getCollectionIndexMeta($collection);
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

    private function getCollectionIndexMeta(Collection $collection): array
    {
        $cacheKey = $this->collectionIdentifier($collection);
        $now = time();
        $cacheEntry = self::$collectionIndexesCache[$cacheKey] ?? null;
        if (null !== $cacheEntry && ($cacheEntry['ts'] + self::INDEX_CACHE_TTL_SECONDS) > $now) {
            return $cacheEntry['indexes'];
        }

        $indexes = [];
        foreach ($collection->listIndexes($this->buildDriverOptions()) as $index) {
            $indexInfo = $index->__debugInfo();
            $entry = ['keys' => array_keys((array)($index['key'] ?? []))];
            if (array_key_exists('collation', $indexInfo)) {
                $collation = $indexInfo['collation'];
                $entry['collation'] = [
                    'locale' => $collation['locale'] ?? 'en',
                    'strength' => $collation['strength'] ?? 1,
                ];
            }
            $indexes[] = $entry;
        }

        self::$collectionIndexesCache[$cacheKey] = ['ts' => $now, 'indexes' => $indexes];
        return $indexes;
    }

    private function collectionIdentifier(Collection $collection): string
    {
        $database = method_exists($collection, 'getDatabaseName') ? $collection->getDatabaseName() : 'db';
        $name = method_exists($collection, 'getCollectionName') ? $collection->getCollectionName() : 'collection';
        return $database . '.' . $name;
    }

    private function isCursorMode(array $criteria): bool
    {
        $mode = strtolower((string)($criteria[NOSQLQuery::NOSQL_CURSOR_MODE_FIELD] ?? 'offset'));
        return in_array($mode, ['cursor', 'seek', 'after'], true);
    }
}
