<?php
namespace NOSQL\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Database;
use NOSQL\Services\Base\NOSQLBase;
use NOSQL\Dto\Model\ResultsetDto;
use NOSQL\Models\base\NOSQLParserTrait;
use PSFS\base\config\Config;
use PSFS\base\dto\Dto;
use PSFS\base\exception\ApiException;
use PSFS\base\types\Api;

final class NOSQLQuery {
    const NOSQL_COLLATION_FIELD = '__collation';
    const NOSQL_IN_OPERATOR = '$in';
    const NOSQL_NOT_IN_OPERATOR = '$nin';
    const NOSQL_NOT_NULL_OPERATOR = '$ne';
    const NOSQL_EQUAL_OPERATOR = '$eq';
    const NOSQL_NOT_EQUAL_OPERATOR = '$ne';
    const NOSQL_LESS_OPERATOR = '$lt';
    const NOSQL_LESS_EQUAL_OPERATOR = '$lte';
    const NOSQL_GREATER_OPERATOR = '$gt';
    const NOSQL_GREATER_EQUAL_OPERATOR = '$gte';

    public static $pipelines = [
        '$lookup',
        '$count',
        '$unwind',
        '$unset',
        '$replaceroot',
        '$mergeobjects',
        '$match',
    ];

    /**
     * @param $pk
     * @param Database|null $con
     * @return mixed
     * @throws ApiException
     */
    public static function findPk($modelName, $pk, Database $con = null) {
        $model = new $modelName();
        $con = NOSQLActiveRecord::initConnection($model, $con);
        $collection = $con->selectCollection($model->getSchema()->name);
        $result = $collection->findOne(['_id' => new ObjectId($pk)]);
        if(null !== $result) {
            $model->feed($result->getArrayCopy());
        } else {
            throw new ApiException(t('Document not found'), 404);
        }
        return $model;
    }

    private static function generateItems($model, $items) {
        foreach($items as $item) {
            $model->feed($item->getArrayCopy(), true);
            yield $model->getDtoCopy(true);
        }
    }

    private static function getItemIterator($items) {
        foreach($items as $item) {
            yield $item;
        }
    }

    public static function count($modelName, array $criteria, Database $con = null) {
        /** @var NOSQLActiveRecord $model */
        $model = new $modelName();
        $con = NOSQLActiveRecord::initConnection($model, $con);
        $collection = $con->selectCollection($model->getSchema()->name);
        $resultSet = new ResultsetDto(false);
        // TODO create Query model for it
        [$filters, $nosqlOptions] = self::parseCriteria($criteria, $model, $collection);
        $pipelines = [];
        if(count($filters)) {
            $pipelines[] = [
                '$match' => $filters,
            ];
        }
        if(array_key_exists(Api::API_ORDER_FIELD, $criteria)) {
            $pipelines[] = [
                '$sort' => $criteria[Api::API_ORDER_FIELD],
            ];
        }
        $criteria['$count'] = 'total_count';
        $customPipelines = self::parsePipelines($criteria);
        foreach($customPipelines as $customPipeline) {
            $pipelines[] = $customPipeline;
        }
        if(array_key_exists('custom_pipelines', $criteria)) {
            foreach($criteria['custom_pipelines'] as $pipeline => $rules) {
                $pipelines[] = [$pipeline => $rules];
            };
        }
        $items = self::getItemIterator($collection->aggregate($pipelines));
        foreach($items as $item) {
            $data = $item->getArrayCopy();
            $resultSet->items[] = $data['total_count'];
        }
        return $resultSet;
    }

    /**
     * @param string $modelName
     * @param array $criteria
     * @param Database|null $con
     * @return ResultsetDto
     * @throws \NOSQL\Exceptions\NOSQLValidationException
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function find($modelName, array $criteria, Database $con = null) {
        /** @var NOSQLActiveRecord $model */
        $model = new $modelName();
        $con = NOSQLActiveRecord::initConnection($model, $con);
        $collection = $con->selectCollection($model->getSchema()->name);
        $resultSet = new ResultsetDto(false);
        // TODO create Query model for it
        [$filters, $nosqlOptions] = self::parseCriteria($criteria, $model, $collection);

        $resultSet->count = $collection->countDocuments($filters, $nosqlOptions);

        $nosqlOptions["limit"] = (integer)(array_key_exists(Api::API_LIMIT_FIELD, $criteria) ? $criteria[Api::API_LIMIT_FIELD] : Config::getParam('pagination.limit', 50));
        $page = (integer)(array_key_exists(Api::API_PAGE_FIELD, $criteria) ? $criteria[Api::API_PAGE_FIELD] : 1);
        $nosqlOptions["skip"] = ($page === 1) ? 0 : ($page - 1) * $nosqlOptions["limit"];

        if ((array_key_exists(Api::API_ORDER_FIELD, $criteria)) && (is_array($criteria[Api::API_ORDER_FIELD]))) {
            $nosqlOptions["sort"] = [];
            foreach ($criteria[Api::API_ORDER_FIELD] as $field => $direction) {
                $nosqlOptions["sort"][$field] = (abs($direction) === 1)  ? $direction : 1;
            }
        }
        $pipelines = [];
        if(count($filters)) {
            $pipelines[] = [
                '$match' => $filters,
            ];
        }
        if(array_key_exists(Api::API_ORDER_FIELD, $criteria)) {
            $pipelines[] = [
                '$sort' => $criteria[Api::API_ORDER_FIELD],
            ];
        }
        $customPipelines = self::parsePipelines($criteria);
        foreach($customPipelines as $customPipeline) {
            $pipelines[] = $customPipeline;
        }
        if(array_key_exists(Api::API_LIMIT_FIELD, $criteria)) {
            $pipelines[] = [
                '$limit' => (int)$criteria[Api::API_LIMIT_FIELD],
            ];
        }
        if(array_key_exists('custom_pipelines', $criteria)) {
            foreach($criteria['custom_pipelines'] as $pipeline => $rules) {
                $pipelines[] = [$pipeline => $rules];
            };
        }
        $items = self::getItemIterator($collection->aggregate($pipelines));
        $resultSet->items = self::generateItems($model, $items);
        return $resultSet;
    }

    /**
     * @param array $criteria
     * @param NOSQLActiveRecord $model
     * @return array
     */
    private static function parseCriteria(array $criteria, NOSQLActiveRecord $model, Collection $collection)
    {
        $filters = [];
        if (array_key_exists(Api::API_COMBO_FIELD, $criteria)) {
            $filters['$text'] = ['$search' => $criteria[Api::API_COMBO_FIELD]];
        }

        foreach ($model->getSchema()->properties as $property) {
            if (array_key_exists($property->name, $criteria)) {
                $filterValue = self::composeFilter($criteria, $property);
                $filters[$property->name] = $filterValue;
            }
        }

        // Check index collation
        $options = [];
        $indexes = $collection->listIndexes();
        foreach($indexes as $index) {
            $indexInfo = $index->__debugInfo();
            if (empty(array_diff(array_keys($index["key"]), array_keys($filters)))) {
                if (array_key_exists("collation", $indexInfo)) {
                    $collation = $indexInfo["collation"];
                    $options["collation"] = ["locale" => $collation["locale"], "strength" => $collation["strength"]];
                    break;
                }
            }
        }

        if (array_key_exists("collation", $options)) {
            foreach($filters as $key=>$filter) {
                if (is_string($criteria[$key])) {
                    $filters[$key] = $criteria[$key];
                }
            }
        }
        return [$filters, $options];
    }

    /**
     * @param array $criteria
     * @return array
     */
    private static function parsePipelines(array $criteria) {
        $pipelines = [];
        foreach($criteria as $key => $criterion) {
            if(in_array(strtolower($key), self::$pipelines, true)) {
                $pipelines[] = [
                    $key => $criterion,
                ];
            }
        }
        return $pipelines;
    }

    /**
     * @param array $criteria
     * @param \NOSQL\Dto\PropertyDto $property
     * @return array|bool|float|int|mixed
     */
    private static function composeFilter(array $criteria, \NOSQL\Dto\PropertyDto $property)
    {
        $filterValue = $criteria[$property->name];
        $matchOperator = is_array($filterValue) ? $filterValue[0] : self::NOSQL_EQUAL_OPERATOR;
        if (is_array($filterValue)) {
            if(in_array($matchOperator, [
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
                $filterValue = [
                    $operator => $value,
                ];
            } else {
                // Default case for back compatibility
                $filterValue = [
                    self::NOSQL_IN_OPERATOR => $filterValue,
                ];
            }
        } elseif(in_array($filterValue, [
            self::NOSQL_NOT_NULL_OPERATOR,
        ], true)) {
            $filterValue = [
                $filterValue => null,
            ];
        } elseif (in_array($property->type, [
            NOSQLBase::NOSQL_TYPE_BOOLEAN,
            NOSQLBase::NOSQL_TYPE_INTEGER,
            NOSQLBase::NOSQL_TYPE_DOUBLE,
            NOSQLBase::NOSQL_TYPE_LONG], true)) {
            if ($property->type === NOSQLBase::NOSQL_TYPE_BOOLEAN) {
                switch ($filterValue) {
                    case '1':
                    case 1:
                    case 'true':
                    case true:
                        $filterValue = true;
                        break;
                    default:
                        $filterValue = false;
                        break;
                }
            } elseif (NOSQLBase::NOSQL_TYPE_INTEGER === $property->type) {
                $filterValue = (integer)$filterValue;
            } else {
                $filterValue = (float)$filterValue;
            }
            $filterValue = [
                self::NOSQL_EQUAL_OPERATOR => $filterValue,
            ];
        } else {
            $filterValue = [
                '$regex' => $filterValue,
                '$options' => 'i'
            ];
        }
        return $filterValue;
    }
}
