<?php
namespace NOSQL\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\Database;
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
        $resultSet->count = $collection->countDocuments($filters);
        $nosqlOptions = [
            'limit' => (integer)(array_key_exists(Api::API_LIMIT_FIELD, $criteria) ? $criteria[Api::API_LIMIT_FIELD] : Config::getParam('pagination.limit', 50)),
        ];
        $filters = self::parseCriteria($criteria, $model);
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
                $filters[$property->name] = [
                    '$regex' => $criteria[$property->name],
                    '$options' => 'i',
                ];
            }
        }
        return $filters;
    }
}