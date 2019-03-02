<?php
namespace NOSQL\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use NOSQL\Services\Base\NOSQLBase;
use NOSQL\Dto\Model\ResultsetDto;
use NOSQL\Models\base\NOSQLParserTrait;
use PSFS\base\config\Config;
use PSFS\base\exception\ApiException;
use PSFS\base\types\Api;

final class NOSQLQuery {
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
        $filters = self::parseCriteria($criteria, $model);
        $resultSet->count = $collection->countDocuments($filters);
        $nosqlOptions = [
            'limit' => (integer)(array_key_exists(Api::API_LIMIT_FIELD, $criteria) ? $criteria[Api::API_LIMIT_FIELD] : Config::getParam('pagination.limit', 50)),
        ];
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
    private static function parseCriteria(array $criteria, NOSQLActiveRecord $model)
    {
        $filters = [];
        foreach ($model->getSchema()->properties as $property) {
            if (array_key_exists($property->name, $criteria)) {
                $filterValue = self::composeFilter($criteria, $property);
                $filters[$property->name] = $filterValue;
            }
        }
        return $filters;
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
                '$options' => 'i',
            ];
        }
        return $filterValue;
    }
}