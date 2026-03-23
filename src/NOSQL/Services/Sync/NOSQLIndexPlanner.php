<?php

namespace NOSQL\Services\Sync;

use NOSQL\Services\Base\NOSQLBase;

final class NOSQLIndexPlanner
{
    /**
     * @param array $collectionDto
     * @return array
     */
    public function buildRecommendedIndexes(array $collectionDto): array
    {
        $recommended = [];
        $properties = $collectionDto['properties'] ?? [];
        foreach ($properties as $property) {
            $name = (string)($property['name'] ?? '');
            $type = strtolower((string)($property['type'] ?? ''));
            $required = (bool)($property['required'] ?? false);
            if ($name === '' || !$required) {
                continue;
            }
            if (!in_array($type, [
                NOSQLBase::NOSQL_TYPE_INTEGER,
                NOSQLBase::NOSQL_TYPE_DOUBLE,
                NOSQLBase::NOSQL_TYPE_LONG,
                NOSQLBase::NOSQL_TYPE_DATE,
                NOSQLBase::NOSQL_TYPE_TIMESTAMP,
                NOSQLBase::NOSQL_TYPE_BOOLEAN,
                NOSQLBase::NOSQL_TYPE_STRING,
            ], true)) {
                continue;
            }
            $recommended[] = [
                'key' => [$name => 1],
                'name' => 'idx_auto_' . ($collectionDto['name'] ?? 'collection') . '_' . $name,
                'unique' => false,
            ];
        }

        return $recommended;
    }

    /**
     * @param array $collectionDto
     * @param bool $includeRecommended
     * @return array
     */
    public function buildIndexesForCollection(array $collectionDto, bool $includeRecommended = false): array
    {
        $indexes = $this->parseDeclaredIndexes($collectionDto);
        if ($includeRecommended) {
            $indexes = array_merge($indexes, $this->buildRecommendedIndexes($collectionDto));
        }

        return $this->deduplicateByName($indexes);
    }

    /**
     * @param array $collectionDto
     * @return array
     */
    private function parseDeclaredIndexes(array $collectionDto): array
    {
        $indexes = [];
        foreach (($collectionDto['indexes'] ?? []) as $index) {
            $dbIndex = [];
            foreach (($index['properties'] ?? []) as $idx) {
                [$property, $direction] = explode('.', (string)$idx);
                switch (strtoupper($direction)) {
                    case 'ASC':
                        $dbIndex[$property] = 1;
                        break;
                    case 'DESC':
                        $dbIndex[$property] = -1;
                        break;
                }
            }

            if (empty($dbIndex)) {
                continue;
            }

            $indexes[] = [
                'key' => $dbIndex,
                'name' => $index['name'] ?? '',
                'unique' => (bool)($index['unique'] ?? false),
            ];
        }

        return $indexes;
    }

    /**
     * @param array $indexes
     * @return array
     */
    private function deduplicateByName(array $indexes): array
    {
        $uniqueByName = [];
        foreach ($indexes as $idx) {
            if (!empty($idx['name']) && !array_key_exists($idx['name'], $uniqueByName)) {
                $uniqueByName[$idx['name']] = $idx;
            }
        }

        return array_values($uniqueByName);
    }
}
