<?php
namespace NOSQL\Models\base;

use MongoDB\Database;
use NOSQL\Dto\Model\NOSQLModelDto;
use NOSQL\Models\NOSQLActiveRecord;

/**
 * Trait NOSQLStatusTrail
 * @package NOSQL\Models\base
 */
trait NOSQLStatusTrait {
    /**
     * @var int
     */
    protected $actionCount = 0;
    /**
     * @var bool
     */
    protected $isNew = true;
    /**
     * @var bool
     */
    protected $isModified = false;
    /**
     * @var array
     */
    protected $changes = [];

    /**
     * @return bool
     */
    public function isNew()
    {
        return $this->isNew;
    }

    /**
     * @param bool $isNew
     */
    public function setIsNew($isNew)
    {
        $this->isNew = $isNew;
    }

    /**
     * @return bool
     */
    public function isModified()
    {
        return $this->isModified;
    }

    /**
     * @param bool $isModified
     */
    public function setIsModified($isModified)
    {
        $this->isModified = $isModified;
    }

    /**
     * @return array
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * @param array $changes
     */
    public function setChanges($changes)
    {
        $this->changes = $changes;
    }

    /**
     * @param string $property
     */
    public function addChanges($property) {
        if(!in_array($property, $this->changes)) {
            $this->changes[] = $property;
        }
    }
    /**
     * @return int
     */
    protected function getActionCount()
    {
        return $this->actionCount;
    }

    /**
     * @param int $actionCount
     */
    protected function setActionCount($actionCount)
    {
        $this->actionCount = $actionCount;
    }

    protected function countAction() {
        $this->actionCount++;
    }

    /**
     * @param NOSQLActiveRecord $model
     * @param NOSQLModelDto $dto
     * @param $hook
     * @param Database|null $con
     * @throws \NOSQL\Exceptions\NOSQLValidationException
     */
    public static function invokeHook(NOSQLActiveRecord $model, NOSQLModelDto $dto, $hook, Database $con = null) {
        if(method_exists($model, $hook)) {
            $con = self::initConnection($model, $con);
            $model->feed($dto->toArray());
            $model->$hook($con);
        }
        unset($model);
    }

}