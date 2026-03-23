<?php

namespace NOSQL\Models\Query;

use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;
use NOSQL\Models\NOSQLActiveRecord;
use PSFS\base\exception\ApiException;

final class NOSQLQueryPlanner
{
    private ?NOSQLQueryFilterBuilder $filterBuilder = null;
    private ?NOSQLQueryPipelineBuilder $pipelineBuilder = null;
    private ?NOSQLQueryOptionsBuilder $optionsBuilder = null;

    /**
     * @param array $criteria
     * @param NOSQLActiveRecord $model
     * @param Collection $collection
     * @return array
     */
    public function buildQueryPlan(array $criteria, NOSQLActiveRecord $model, Collection $collection): array
    {
        $sort = $this->pipelineBuilder()->parseSort($criteria);
        $filters = $this->filterBuilder()->parseCriteria($criteria, $model, fn (mixed $value): ObjectId => $this->toObjectId($value));
        $cursorFilter = $this->pipelineBuilder()->parseCursorFilter($criteria, $sort);
        if (!empty($cursorFilter)) {
            $filters = empty($filters) ? $cursorFilter : ['$and' => [$filters, $cursorFilter]];
        }

        $page = $this->pipelineBuilder()->parsePage($criteria);
        $limit = $this->pipelineBuilder()->parseLimit($criteria);
        $driverOptions = $this->optionsBuilder()->appendCollationOptions(
            $this->optionsBuilder()->buildDriverOptions($criteria),
            $criteria,
            $filters,
            $collection
        );

        return [
            'filters' => $filters,
            'sort' => $sort,
            'projection' => $this->pipelineBuilder()->parseProjection($criteria),
            'customPipelines' => $this->pipelineBuilder()->parseCustomPipelines($criteria),
            'page' => $page,
            'limit' => $limit,
            'skip' => $this->pipelineBuilder()->parseSkip($criteria, $page, $limit),
            'driverOptions' => $driverOptions,
        ];
    }

    /**
     * @param array $plan
     * @return array
     */
    public function buildFindPipeline(array $plan): array
    {
        return $this->pipelineBuilder()->buildFindPipeline($plan);
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
        return $this->optionsBuilder()->buildDriverOptions($criteria);
    }

    private function filterBuilder(): NOSQLQueryFilterBuilder
    {
        if (null === $this->filterBuilder) {
            $this->filterBuilder = new NOSQLQueryFilterBuilder();
        }

        return $this->filterBuilder;
    }

    private function pipelineBuilder(): NOSQLQueryPipelineBuilder
    {
        if (null === $this->pipelineBuilder) {
            $this->pipelineBuilder = new NOSQLQueryPipelineBuilder();
        }

        return $this->pipelineBuilder;
    }

    private function optionsBuilder(): NOSQLQueryOptionsBuilder
    {
        if (null === $this->optionsBuilder) {
            $this->optionsBuilder = new NOSQLQueryOptionsBuilder();
        }

        return $this->optionsBuilder;
    }
}
