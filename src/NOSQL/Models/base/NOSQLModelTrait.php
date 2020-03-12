<?php
namespace NOSQL\Models\base;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use NOSQL\Dto\Model\NOSQLModelDto;

/**
 * Trait NOSQLModelTrait
 * @package NOSQL\Models\base
 */
trait NOSQLModelTrait {
    use NOSQLStatusTrait;
    /**
     * @var NOSQLModelDto
     */
    protected $dto;

    /**
     * @param string $name
     * @return mixed|null
     */
    public function __get($name)
    {
        $value = null;
        if(null !== $this->dto && property_exists($this->dto, $name)) {
            $value = $this->dto->$name;
        }
        return $value;
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        if(null !== $this->dto && property_exists($this->dto, $name)) {
            $this->dto->$name = $value;
            $this->addChanges($name);
        }
        $this->setIsModified(true);
    }

    public function __call($name, $arguments)
    {
        if(preg_match('/^(set|get)/', $name)) {
            $property = strtolower(str_replace(['set', 'Set', 'get', 'Get'], '', $name));
            if(false !== stripos($name, 'set')) {
                $this->dto->$property = $arguments[0];
            } else {
                return $this->dto->$property;
            }

        }
    }

    /**
     * @param array $data
     * @param bool $withName
     * @throws \NOSQL\Exceptions\NOSQLValidationException
     */
    public function feed(array $data, $withName = false) {
        $name = '';
        $sep = '';
        foreach($data as $key => $value) {
            if($value instanceof ObjectId) {
                $this->dto->setPk($value->jsonSerialize()['$oid']);
                $name = $this->dto->getPk();
            } elseif($key === '_last_update') {
                $this->dto->setLastUpdate($value instanceof UTCDateTime ? $value : null);
            } else {
                if (is_object($value)) {
                    switch (get_class($value)) {
                        case UTCDateTime::class:
                            $value = $value->toDateTime()->format('Y-m-d H:i:s');
                            break;
                    }
                }
                $this->$key = $value;
                if(in_array(strtolower($key), ['name', 'label', 'title', 'method'])) {
                    $name .= $sep . $value;
                    $sep = ' ';
                }
            }
        }
        if($withName) {
            $this->dto->setName($name);
        }
    }

    /**
     * @param array $data
     * @return NOSQLModelTrait
     * @throws \NOSQL\Exceptions\NOSQLValidationException
     */
    public static function fromArray(array $data) {
        $modelName = get_called_class();
        /** @var NOSQLModelTrait $model */
        $model = new $modelName();
        $model->feed($data);
        return $model;
    }

    /**
     * Before insert hook
     * @param Database $con
     */
    protected function preInsert(Database $con = null) {}

    /**
     * Before update hook
     * @param Database $con
     */
    protected function preUpdate(Database $con = null) {}

    /**
     * Before save hook
     * @param Database $con
     */
    protected function preSave(Database $con = null) {}

    /**
     * Before delete hook
     * @param Database $con
     */
    protected function preDelete(Database $con = null) {}

    /**
     * After insert hook
     * @param Database $con
     */
    protected function postInsert(Database $con = null) {}

    /**
     * After update hook
     * @param Database $con
     */
    protected function postUpdate(Database $con = null) {}

    /**
     * After save hook
     * @param Database $con
     */
    protected function postSave(Database $con = null) {}

    /**
     * After delete hook
     * @param Database $con
     */
    protected function postDelete(Database $con = null) {}
}
