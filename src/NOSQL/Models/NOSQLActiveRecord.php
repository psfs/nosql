<?php
namespace NOSQL\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use NOSQL\Models\base\NOSQLModelTrait;
use NOSQL\Models\base\NOSQLParserTrait;
use NOSQL\Services\ParserService;
use PSFS\base\Logger;
use PSFS\base\types\traits\SingletonTrait;

/**
 * Class NOSQLActiveRecord
 * @package NOSQL\Models
 */
abstract class NOSQLActiveRecord {
    use NOSQLModelTrait;
    use NOSQLParserTrait;
    use SingletonTrait;

    /**
     * NOSQLActiveRecord constructor.
     * @throws \NOSQL\Exceptions\NOSQLParserException
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function __construct()
    {
        $this->hydrate();
    }

    /**
     * @return array
     */
    public function toArray() {
        return $this->dto->toArray();
    }

    /**
     * @param Database|null $con
     * @return bool
     */
    public function save(Database $con = null) {
        $saved = false;
        if(null === $con) {
            $con = ParserService::getInstance()->createConnection($this->getDomain());
        }
        $collection = $con->selectCollection($this->getSchema()->name);
        try {
            $isInsert = $isUpdate = false;
            $this->dto->setLastUpdate(new \DateTime());
            if($this->isNew()) {
                $this->preInsert($con);
                $isInsert = true;
            } elseif ($this->isModified()) {
                $this->preUpdate($con);
                $isUpdate = true;
            }
            $result = $collection->insertOne($this->toArray());
            if($result->getInsertedCount() > 0) {
                $id = $result->getInsertedId();
                $this->dto->setPk($id->jsonSerialize()['$oid']);
                if($isInsert) {
                    $this->postInsert($con);
                } elseif($isUpdate) {
                    $this->postUpdate($con);
                }
                $saved = true;
            }
        } catch(\Exception $exception) {
            Logger::log($exception, LOG_CRIT, $this->toArray());
        }
        return $saved;
    }

    /**
     * @param Database|null $con
     * @return bool
     */
    public function update(Database $con = null) {
        $updated = false;
        if(null === $con) {
            $con = ParserService::getInstance()->createConnection($this->getDomain());
        }
        $collection = $con->selectCollection($this->getSchema()->name);
        try {
            $this->dto->setLastUpdate(new \DateTime());
            $this->preUpdate($con);
            $data = $this->toArray();
            unset($data['_id']);
            $collection->findOneAndReplace(['_id' => new ObjectId($this->dto->getPk())], $data);
            $this->postUpdate($con);
            $updated = true;
        } catch(\Exception $exception) {
            Logger::log($exception, LOG_CRIT, $this->toArray());
        }
        return $updated;
    }



    public function delete(Database $con = null) {
        $deleted = false;
        if(null === $con) {
            $con = ParserService::getInstance()->createConnection($this->getDomain());
        }
        $collection = $con->selectCollection($this->getSchema()->name);
        try {
            $this->preDelete($con);
            $collection->deleteOne(['_id' => new ObjectId($this->dto->getPk())]);
            $this->postDelete($con);
            $deleted = true;
            $this->dto = null;
        } catch(\Exception $exception) {
            Logger::log($exception, LOG_CRIT, $this->toArray());
        }
        return $deleted;
    }

    /**
     * @param string $pk
     * @param Database|null $con
     * @return NOSQLActiveRecord
     */
    public static function findPk($pk, Database $con = null) {
        $modelName = get_called_class();
        $model = new $modelName();
        if(null === $con) {
            $con = ParserService::getInstance()->createConnection($model->getDomain());
        }
        $collection = $con->selectCollection($model->getSchema()->name);
        $result = $collection->findOne(['_id' => new ObjectId($pk)]);
        $model->feed($result->getArrayCopy());
        return $model;
    }
}