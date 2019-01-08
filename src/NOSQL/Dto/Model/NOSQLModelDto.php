<?php
namespace NOSQL\Dto\Model;

use NOSQL\Exceptions\NOSQLValidationException;
use NOSQL\Services\base\NOSQLBase;
use PSFS\base\dto\Dto;
use PSFS\base\types\helpers\InjectorHelper;

/**
 * Class NOSQLModelDto
 * @package NOSQL\Dto\Model
 */
abstract class NOSQLModelDto extends Dto {
    /**
     * @var string
     * @label Model identifier
     */
    protected $_id;

    /**
     * @return string
     */
    public function getPk()
    {
        return $this->_id;
    }

    public function resetPk() {
        $this->_id = null;
        $this->_last_update = null;
    }

    /**
     * @param string $id
     * @throws NOSQLValidationException
     */
    public function setPk(string $id)
    {
        if(!empty($this->_id)) {
            throw new NOSQLValidationException(t('Primary key already defined'), NOSQLValidationException::NOSQL_VALIDATION_ID_ALREADY_DEFINED);
        }
        $this->_id = $id;
    }

    /**
     * @param string $format
     * @return \DateTime|string
     */
    public function getLastUpdate($format = null)
    {
        return null !== $format && null !== $this->_last_update ? $this->_last_update->format($format) : $this->_last_update;
    }

    /**
     * @param \DateTime|null $last_update
     * @throws \Exception
     */
    public function setLastUpdate(\DateTime $last_update = null)
    {
        $this->_last_update = $last_update ?: new \DateTime();
    }
    /**
     * @var \DateTime
     * @label Last update at
     */
    protected $_last_update;

    /**
     * @param bool $throwException
     * @return array
     * @throws NOSQLValidationException
     * @throws \ReflectionException
     */
    public function validate($throwException = false) {
        $errors = [];
        $reflection = new \ReflectionClass(get_called_class());
        foreach($reflection->getProperties() as $property) {
            $required = InjectorHelper::checkIsRequired($property->getDocComment());
            $value = $property->getValue($this);
            if($required && empty($value)) {
                if($throwException) {
                    throw new NOSQLValidationException(t('Empty value for property ') . $property->getName(), NOSQLValidationException::NOSQL_VALIDATION_REQUIRED);
                } else {
                    $errors[] = $property->getName();
                }
            } else {
                $this->checkType($throwException, $property, $value, $errors);
            }
        }
        return $errors;
    }

    public function toArray()
    {
        $array = parent::toArray();
        if(null !== $this->getPk()) {
            $array['_id'] = $this->getPk();
        }
        $array['_last_update'] = $this->getLastUpdate(\DateTime::ATOM);
        return $array;
    }

    /**
     * @param $throwException
     * @param \ReflectionProperty $property
     * @param $value
     * @param array $errors
     * @throws NOSQLValidationException
     */
    private function checkType($throwException, \ReflectionProperty $property, $value, array &$errors)
    {
        $type = InjectorHelper::extractVarType($property->getDocComment());
        switch (strtolower($type)) {
            case NOSQLBase::NOSQL_TYPE_LONG:
            case NOSQLBase::NOSQL_TYPE_INTEGER:
            case NOSQLBase::NOSQL_TYPE_DOUBLE:
                if (!is_numeric($value)) {
                    $errors[] = $property->getName();
                }
                break;
            case NOSQLBase::NOSQL_TYPE_ENUM:
                $values = explode('|', InjectorHelper::getValues($property->getDocComment()));
                if (!in_array($value, $values)) {
                    $errors[] = $property->getName();
                }
                break;
            case NOSQLBase::NOSQL_TYPE_ARRAY:
                if (!is_array($value)) {
                    $errors[] = $property->getName();
                }
                break;
            case NOSQLBase::NOSQL_TYPE_BOOLEAN:
                if (!in_array($value, [true, false, 0, 1])) {
                    $errors[] = $property->getName();
                }
                break;
        }
        if (in_array($property->getName(), $errors) && $throwException) {
            throw new NOSQLValidationException(t('Format not valid for property ') . $property->getName(), NOSQLValidationException::NOSQL_VALIDATION_NOT_VALID);
        }
    }
}