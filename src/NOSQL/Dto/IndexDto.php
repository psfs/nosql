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
     * @required
     */
    public $properties = [];
    /**
     * @var string
     * @required
     * @label Index name
     */
    public $name;
    /**
     * @var bool
     * @label Index is unique?
     */
    public $unique = false;
}
