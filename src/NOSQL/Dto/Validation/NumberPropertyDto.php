<?php
namespace NOSQL\Dto\Validation;

/**
 * Class NumberPropertyDto
 * @package NOSQL\Dto\Validation
 */
class NumberPropertyDto extends BsonTypePropertyDto {
    public $minimun = 0;
    public $maximum = null;
    public $exclusiveMaixun = false;
}