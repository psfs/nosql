<?php
namespace NOSQL\Dto;

use PSFS\base\dto\Dto;

/**
 * Class IndexDto
 */
class IndexDto extends Dto {
    /**
     * @var array
     * @label Index keys
     */
    public $keys = [];
}