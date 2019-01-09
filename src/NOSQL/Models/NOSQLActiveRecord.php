<?php
namespace NOSQL\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use NOSQL\Dto\Model\NOSQLModelDto;
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
            Logger::log($exception->getMessage(), LOG_CRIT, $this->toArray());
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
            Logger::log($exception->getMessage(), LOG_CRIT, $this->toArray());
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
            $dtos = $this->prepareInsertDtos($data, $con);
            $result = $collection->insertMany($data);
            $ids = $result->getInsertedIds();
            $inserts = $this->parseInsertedDtos($con, $ids, $dtos);
        } catch(\Exception $exception) {
            Logger::log($exception->getMessage(), LOG_CRIT, $this->toArray());
        }
        return $inserts;
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
        } catch(\Exception $exception) {
            Logger::log($exception->getMessage(), LOG_CRIT, $this->toArray());
        }
        return $deleted;
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
        $now = new \DateTime();
        foreach ($data as $insertData) {
            $dto = $this->getDtoCopy(true);
            $dto->fromArray($insertData);
            $dto->setLastUpdate($now);
            $dtos[] = $dto;
            self::invokeHook($this, $dto, 'preInsert', $con);
            self::invokeHook($this, $dto, 'preSave', $con);
        }
        unset($dto);
        return $dtos;
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
            $id = $insertedId->jsonSerialize();
            $dtos[$index]->setPk($id['$oid']);
            self::invokeHook($this, $dtos[$index], 'postInsert', $con);
            self::invokeHook($this, $dtos[$index], 'postSave', $con);
            $inserts++;
        }
        return $inserts;
    }
}