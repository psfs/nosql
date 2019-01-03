<?php
namespace NOSQL\Models\base;

/**
 * Trait NOSQLStatusTrail
 * @package NOSQL\Models\base
 */
trait NOSQLStatusTrait {
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

}