<?php

namespace NOSQL\Models\Query;

use MongoDB\Collection;
use NOSQL\Models\NOSQLQuery;
use NOSQL\Services\Helpers\NOSQLApiHelper;
use PSFS\base\config\Config;

final class NOSQLQueryOptionsBuilder
{
    private const INDEX_CACHE_TTL_SECONDS = 60;

    /**
     * @var array<string,array{ts:int,indexes:array}>
     */
    private static array $collectionIndexesCache = [];

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

    /**
     * @param array $driverOptions
     * @param array $criteria
     * @param array $filters
     * @param Collection $collection
     * @return array
     */
    public function appendCollationOptions(array $driverOptions, array $criteria, array $filters, Collection $collection): array
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

    /**
     * @param Collection $collection
     * @return array
     */
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

    /**
     * @param Collection $collection
     * @return string
     */
    private function collectionIdentifier(Collection $collection): string
    {
        $database = method_exists($collection, 'getDatabaseName') ? $collection->getDatabaseName() : 'db';
        $name = method_exists($collection, 'getCollectionName') ? $collection->getCollectionName() : 'collection';
        return $database . '.' . $name;
    }
}
