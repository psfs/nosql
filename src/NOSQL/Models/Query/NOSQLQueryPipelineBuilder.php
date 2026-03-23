<?php

namespace NOSQL\Models\Query;

use NOSQL\Models\NOSQLQuery;
use PSFS\base\config\Config;
use PSFS\base\types\Api;

final class NOSQLQueryPipelineBuilder
{
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
     * @param array $criteria
     * @return int
     */
    public function parsePage(array $criteria): int
    {
        $page = (int)($criteria[Api::API_PAGE_FIELD] ?? 1);
        return $page > 0 ? $page : 1;
    }

    /**
     * @param array $criteria
     * @return int|null
     */
    public function parseLimit(array $criteria): ?int
    {
        $defaultLimit = (int)Config::getParam('pagination.limit', 50);
        $limit = (int)($criteria[Api::API_LIMIT_FIELD] ?? $defaultLimit);
        return $limit < 0 ? null : $limit;
    }

    /**
     * @param array $criteria
     * @param int $page
     * @param int|null $limit
     * @return int
     */
    public function parseSkip(array $criteria, int $page, ?int $limit): int
    {
        if (null === $limit || $this->isCursorMode($criteria)) {
            return 0;
        }
        return ($page - 1) * $limit;
    }

    /**
     * @param array $criteria
     * @return array
     */
    public function parseSort(array $criteria): array
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
    public function parseProjection(array $criteria): array
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
    public function parseCursorFilter(array $criteria, array $sort): array
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

    /**
     * @param array $criteria
     * @return array
     */
    public function parseCustomPipelines(array $criteria): array
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
     * @param string $stage
     * @return string
     */
    private function normalizePipelineStage(string $stage): string
    {
        $normalized = '$' . ltrim($stage, '$');
        return strtolower($normalized) === '$replaceroot' ? '$replaceRoot' : $normalized;
    }

    /**
     * @param array $criteria
     * @return bool
     */
    private function isCursorMode(array $criteria): bool
    {
        $mode = strtolower((string)($criteria[NOSQLQuery::NOSQL_CURSOR_MODE_FIELD] ?? 'offset'));
        return in_array($mode, ['cursor', 'seek', 'after'], true);
    }
}
