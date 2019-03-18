<?php
namespace NOSQL\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Database;
use NOSQL\Services\Base\NOSQLBase;
use NOSQL\Dto\Model\ResultsetDto;
use NOSQL\Models\base\NOSQLParserTrait;
use PSFS\base\config\Config;
use PSFS\base\exception\ApiException;

final class NOSQLQuery {
    const NOSQL_ORDER_FIELD = '__order';
    const NOSQL_SORT_FIELD = '__sort';
    const NOSQL_PAGE_FIELD = '__page';
    const NOSQL_LIMIT_FIELD = '__limit';
    const NOSQL_COLLATION_FIELD = '__collation';

    /**
     * @param $pk
     * @param Database|null $con
     * @return mixed
     * @throws ApiException
     */
    public static function findPk($modelName, $pk, Database $con = null) {
        $model = new $modelName();
        $con = NOSQLParserTrait::initConnection($model, $con);
        $collection = $con->selectCollection($model->getSchema()->name);
        $result = $collection->findOne(['_id' => new ObjectId($pk)]);
        if(null !== $result) {
            $model->feed($result->getArrayCopy());
        } else {
            throw new ApiException(t('Document not found'), 404);
        }
        return $model;
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
        $con = NOSQLParserTrait::initConnection($model, $con);
        $collection = $con->selectCollection($model->getSchema()->name);
        $resultSet = new ResultsetDto(false);
        // TODO create Query model for it
        [$filters, $nosqlOptions] = self::parseCriteria($criteria, $model, $collection);

        $resultSet->count = $collection->countDocuments($filters, $nosqlOptions);

        $nosqlOptions["limit"] = (integer)(array_key_exists(self::NOSQL_LIMIT_FIELD, $criteria) ? $criteria[self::NOSQL_LIMIT_FIELD] : Config::getParam('pagination.limit', 50));
        $nosqlOptions["skip"] = (integer)(array_key_exists(self::NOSQL_PAGE_FIELD, $criteria) ? $criteria[self::NOSQL_PAGE_FIELD] : 0);

        if (array_key_exists(self::NOSQL_SORT_FIELD, $criteria)) {
            $nosqlOptions["sort"] = [];
            $nosqlOptions["sort"][$criteria[self::NOSQL_SORT_FIELD]] = (array_key_exists(self::NOSQL_ORDER_FIELD, $criteria)) ?
                                                                        $criteria[self::NOSQL_ORDER_FIELD] : 1;
        }

        $results = $collection->find($filters, $nosqlOptions);
        /** @var  $result */
        $items = $results->toArray();
        foreach($items as $item) {
            $model->feed($item->getArrayCopy(), true);
            $resultSet->items[] = $model->getDtoCopy(true);
        }
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
     * @param \NOSQL\Dto\PropertyDto $property
     * @return array|bool|float|int|mixed
     */
    private static function composeFilter(array $criteria, \NOSQL\Dto\PropertyDto $property)
    {
        $filterValue = $criteria[$property->name];
        if (is_array($filterValue)) {
            $filterValue = [
                '$in' => $filterValue,
            ];
        } elseif (in_array($property->type, [
            NOSQLBase::NOSQL_TYPE_BOOLEAN,
            NOSQLBase::NOSQL_TYPE_INTEGER,
            NOSQLBase::NOSQL_TYPE_DOUBLE,
            NOSQLBase::NOSQL_TYPE_LONG])) {
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
                '$eq' => $filterValue,
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