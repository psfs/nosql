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
        $con = NOSQLParserTrait::initConnection($con, $model);
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
     * @param $modelName
     * @param array $criteria
     * @param Database|null $con
     * @return ResultsetDto
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function find($modelName, array $criteria, Database $con = null) {
        $model = new $modelName();
        $con = NOSQLParserTrait::initConnection($con, $model);
        $collection = $con->selectCollection($model->getSchema()->name);
        $nosqlOptions = [
            'limit' => array_key_exists(Api::API_LIMIT_FIELD, $criteria) ? $criteria[Api::API_LIMIT_FIELD] : Config::getParam('pagination.limit', 50),
        ];
        $resultSet = new ResultsetDto(false);
        $resultSet->count = $collection->countDocuments($criteria, $nosqlOptions);
        $results = $collection->find($criteria, $nosqlOptions);
        /** @var  $result */
        foreach($results->toArray() as $result) {
            $model->feed($result->getArrayCopy());
            $resultSet->items[] = $model->getDtoCopy(true);
        }
        return $resultSet;
    }
}