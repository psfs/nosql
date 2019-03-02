<?php
namespace NOSQL\Dto\Model;

use MongoDB\BSON\UTCDateTime;
use NOSQL\Exceptions\NOSQLValidationException;
use NOSQL\Services\Base\NOSQLBase;
use PSFS\base\dto\Dto;
use PSFS\base\types\Api;
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
     * @var \DateTime
     * @label Last update at
     */
    protected $_last_update;


    /**
     * @var string
     * @label List name in string
     */
    protected $__name__;

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
        $value = $this->_last_update;
        if(null !== $format) {

        }
        return $value;
    }

    /**
     * @param \DateTime|null $last_update
     * @throws \Exception
     */
    public function setLastUpdate($last_update = null)
    {
        $this->_last_update = $last_update ?: new UTCDateTime();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->__name__;
    }

    /**
     * @param string $_name__
     */
    public function setName($_name__)
    {
        $this->__name__ = $_name__;
    }

    /**
     * @param bool $throwException
     * @return array
     * @throws NOSQLValidationException
     * @throws \ReflectionException
     */
    public function validate($throwException = false) {
        $errors = [];
        $reflection = new \ReflectionClass(get_called_class());
        foreach($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
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
        if(null !== $this->getName()) {
            $array[Api::API_LIST_NAME_FIELD] = $this->getName();
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
    private function checkType($throwException, \ReflectionProperty &$property, $value, array &$errors)
    {
        $type = InjectorHelper::extractVarType($property->getDocComment());
        switch (strtolower($type)) {
            case NOSQLBase::NOSQL_TYPE_LONG:
            case NOSQLBase::NOSQL_TYPE_INTEGER:
            case NOSQLBase::NOSQL_TYPE_DOUBLE:
                if (!is_numeric($value)) {
                    $errors[] = $property->getName();
                } else {
                    if(NOSQLBase::NOSQL_TYPE_INTEGER === strtolower($type)) {
                        $property->setValue($this, (integer)$value);
                    } else {
                        $property->setValue($this, (float)$value);
                    }
                }
                break;
            case NOSQLBase::NOSQL_TYPE_ENUM:
                $values = InjectorHelper::getValues($property->getDocComment());
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
                $property->setValue($this, (bool)$value);
                break;
            case NOSQLBase::NOSQL_TYPE_DATE:
                $dateTime = new \DateTime($value, new \DateTimeZone('UTC'));
                if(!$dateTime) {
                    $errors[] = $property->getName();
                } else {
                    $dateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                    $property->setValue($this, new UTCDateTime($dateTime->getTimestamp()*1000));
                }
                break;
        }
        if (in_array($property->getName(), $errors) && $throwException) {
            throw new NOSQLValidationException(t('Format not valid for property ') . $property->getName(), NOSQLValidationException::NOSQL_VALIDATION_NOT_VALID);
        }
    }
}