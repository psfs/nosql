<?php
namespace NOSQL\Services;

use MongoDB\Client;
use NOSQL\Api\base\NOSQLBase;
use NOSQL\Dto\Model\NOSQLModelDto;
use NOSQL\Models\base\NOSQLModelTrait;
use NOSQL\Models\NOSQLActiveRecord;
use PSFS\base\config\Config;
use PSFS\base\exception\ApiException;
use PSFS\base\Singleton;

/**
 * Class ParserService
 * @package NOSQL\Services
 */
final class ParserService extends  Singleton {
    /**
     * @param $domain
     * @return \MongoDB\Database
     */
    public function createConnection($domain) {
        $lowerDomain = strtolower($domain);
        $protocol = Config::getParam('nosql.protocol', 'mongodb');
        $authSource = Config::getParam('nosql.authDb', 'admin');
        $dns = $protocol . '://';
        $dns .= Config::getParam('nosql.user', '', $lowerDomain);
        $dns .= ':' . Config::getParam('nosql.password', '', $lowerDomain);
        $dns .= '@' . Config::getParam('nosql.host', 'localhost', $lowerDomain);

        $database = Config::getParam('nosql.database', 'nosql', $lowerDomain);
        if(null !== Config::getParam('nosql.replicaset')) {
            $dns .= '/' . $database . '?ssl=true&replicaSet=' . Config::getParam('nosql.replicaset', null, $lowerDomain);
            $dns .= '&authSource=' . $authSource . '&serverSelectionTryOnce=false&serverSelectionTimeoutMS=15000';
        } else {
            if(strtolower($protocol) !== 'mongodb+srv') {
                $dns .= ':' . Config::getParam('nosql.port', '27017', $lowerDomain);
            }
            $dns .= '/' . $database . "?authSource=" . $authSource;
        }
        $dns .= '&retryWrites=true&w=majority';
        $client = new Client($dns);
        return $client->selectDatabase($database);
    }

    /**
     * @param string $domain
     * @param $collection
     * @param NOSQLModelDto $dto
     * @throws ApiException
     * @throws \NOSQL\Exceptions\NOSQLValidationException
     * @throws \ReflectionException
     */
    public function checkAndSave($domain, $collection, NOSQLModelDto $dto) {
        $errors = $dto->validate();
        if(empty($errors)) {

        } else {
            throw new ApiException(t('Dto not valid'), 400);
        }
    }

    /**
     * @param array $data
     * @param $className
     * @return NOSQLModelTrait|null
     * @throws \NOSQL\Exceptions\NOSQLValidationException
     * @throws \ReflectionException
     */
    public function hydrateModelFromRequest(array $data, $className) {
        $model = null;
        $reflectionClass = new \ReflectionClass($className);
        if($reflectionClass->isSubclassOf(NOSQLBase::class)) {
            /** @var NOSQLActiveRecord $modelName */
            $modelName = $className::MODEL_CLASS;
            $model = $modelName::fromArray($data);
        }
        return $model;
    }
}
