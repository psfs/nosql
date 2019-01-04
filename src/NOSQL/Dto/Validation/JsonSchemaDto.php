<?php
namespace NOSQL\Dto\Validation;

use PSFS\base\dto\Dto;

/**
 * Class JsonSchemaDto
 * @package NOSQL\Dto\Validation
 */
class JsonSchemaDto extends Dto {
    public $bsonType = "object";
    public $required = [];
    public $properties = [];
}