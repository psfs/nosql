<?php
namespace NOSQL\Dto\Validation;

/**
 * Class EnumPropertyDto
 * @package NOSQL\Dto\Validation
 */
class EnumPropertyDto extends BsonTypePropertyDto {
    public $enum = [];
}