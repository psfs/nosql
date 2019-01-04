<?php
namespace NOSQL\Dto\Validation;

use PSFS\base\dto\Dto;

/**
 * Class BsonTypePRopertyDto
 * @package NOSQL\Dto\Validation
 */
abstract class BsonTypePropertyDto extends Dto {
    public $bsonType;
    public $description;
}