<?php
namespace NOSQL\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\BulkWriteResult;
use MongoDB\Database;
use NOSQL\Dto\Model\NOSQLModelDto;
use NOSQL\Exceptions\NOSQLValidationException;
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
     * @param bool $cleanPk
     * @return \NOSQL\Dto\Model\NOSQLModelDto
     */
    public function getDtoCopy($cleanPk = false) {
        $copy = clone $this->dto;
        if($cleanPk) {
            $this->dto->resetPk();
        }
        return $copy;
    }

    private function prepareData() {
        $this->dto->validate(true);
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
            $this->prepareData();
            $this->dto->setLastUpdate();
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
                $this->countAction();
            }
        } catch(\Exception $exception) {
            if($exception instanceof NOSQLValidationException) {
                throw $exception;
            } else {
                Logger::log($exception->getMessage(), LOG_CRIT, $this->toArray());
            }
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
            $this->prepareData();
            $this->dto->setLastUpdate();
            $this->preUpdate($con);
            $data = $this->toArray();
            unset($data['_id']);
            $collection->findOneAndReplace(['_id' => new ObjectId($this->dto->getPk())], $data);
            $this->postUpdate($con);
            $updated = true;
            $this->countAction();
        } catch(\Exception $exception) {
            if($exception instanceof NOSQLValidationException) {
                throw $exception;
            } else {
                Logger::log($exception->getMessage(), LOG_CRIT, $this->toArray());
            }
        }
        return $updated;
    }

    /**
     * @param array $data
     * @param Database|null $con
     * @return int
     */
    public function bulkInsert(array $data, Database $con = null) {
        $inserts = 0;
        if(null === $con) {
            $con = ParserService::getInstance()->createConnection($this->getDomain());
        }
        $collection = $con->selectCollection($this->getSchema()->name);
        try {
            [$dtos, $data] = $this->prepareInsertDtos($data, $con);
            $result = $collection->insertMany($data);
            $ids = $result->getInsertedIds();
            $inserts = $this->parseInsertedDtos($con, $ids, $dtos);
            $this->setActionCount($inserts);
        } catch(\Exception $exception) {
            Logger::log($exception->getMessage(), LOG_CRIT, $this->toArray());
        }
        return $inserts;
    }

    /**
     * Function to make a bulk upsert of documents
     * @param array $data
     * @param string $id
     * @param Database|null $con
     * @return int
     */
    public function bulkUpsert(array $data, $id, Database $con = null) {
        if(null === $con) {
            $con = ParserService::getInstance()->createConnection($this->getDomain());
        }
        $collection = $con->selectCollection($this->getSchema()->name);

        $upserts = 0;
        $filter = $options = $operations = [];
        try {
            // Check index collation
            $indexes = $collection->listIndexes();
            foreach($indexes as $index) {
                $indexInfo = $index->__debugInfo();
                $keys = array_keys($index["key"]);
                if ((count($keys) === 1) && ($keys[0] === $id) && (array_key_exists("collation", $indexInfo))) {
                    $collation = $indexInfo["collation"];
                    $options["collation"] = ["locale" => $collation["locale"], "strength" => $collation["strength"]];
                    break;
                }
            }

            foreach($data as $item) {
                $filter[$id] = ['$eq' => $item[$id]];
                $update = [];
                $update['$set'] = $item;
                $options['upsert'] = true;
                $operation = [
                    "updateOne" => [$filter, $update, $options]
                ];
                $operations[] = $operation;
            }
            /** @var BulkWriteResult $result */
            $result = $collection->bulkWrite($operations);
            $upserts = $result->getModifiedCount();
        } catch (\Exception $exception) {
            Logger::log($exception->getMessage(), LOG_CRIT, $this->toArray());
        }

        return $upserts;
    }

    /**
     * @param Database|null $con
     * @return bool
     */
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
            $this->countAction();
        } catch(\Exception $exception) {
            Logger::log($exception->getMessage(), LOG_CRIT, $this->toArray());
        }
        return $deleted;
    }

    /**
     * Function to make a bulk delete of documents
     * @param array $filters
     * @param Database|null $con
     * @return int
     */
    public function bulkDelete(array $filters, Database $con = null) {
        $deletedCount = 0;
        if(null === $con) {
            $con = ParserService::getInstance()->createConnection($this->getDomain());
        }
        $collection = $con->selectCollection($this->getSchema()->name);
        try {
            $result = $collection->deleteMany($filters);
            $deletedCount = $result->getDeletedCount();
        } catch(\Exception $exception) {
            Logger::log($exception->getMessage(), LOG_CRIT, $this->toArray());
        }
        return $deletedCount;
    }

    /**
     * @param array $data
     * @param Database $con
     * @return array
     * @throws \NOSQL\Exceptions\NOSQLValidationException
     */
    private function prepareInsertDtos(array $data, Database $con)
    {
        $dtos = [];
        /** @var NOSQLModelDto $dto */
        foreach ($data as &$insertData) {
            if(is_object($insertData) && $insertData instanceof NOSQLModelDto) {
                $dto = clone $insertData;
            } else {
                $dto = $this->getDtoCopy(true);
                $dto->fromArray($insertData);
            }
            $dto->validate();
            $dto->setLastUpdate();
            self::invokeHook($this, $dto, 'preInsert', $con);
            self::invokeHook($this, $dto, 'preSave', $con);
            $dtos[] = $dto;
            $insertData = $dto->toArray();
        }
        unset($dto);
        return [$dtos, $data];
    }

    /**
     * @param Database $con
     * @param ObjectId[] $ids
     * @param NOSQLModelDto[] $dtos
     * @return int
     * @throws \NOSQL\Exceptions\NOSQLValidationException
     */
    private function parseInsertedDtos(Database $con, $ids, $dtos)
    {
        $inserts = 0;
        foreach ($ids as $index => $insertedId) {
            $id = is_string($insertedId) ? ['$oid' => $insertedId] : $insertedId->jsonSerialize();
            $dto = $dtos[$index];
            if($dto instanceof  NOSQLModelDto) {
                $dto->setPk($id['$oid']);
            } else {

            }
            self::invokeHook($this, $dtos[$index], 'postInsert', $con);
            self::invokeHook($this, $dtos[$index], 'postSave', $con);
            $inserts++;
        }
        return $inserts;
    }
}
