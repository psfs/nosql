<?php
namespace NOSQL\Dto;

use PSFS\base\dto\Dto;

/**
 * Class PropertyDto
 * @package NOSQL\Dto
 */
class PropertyDto extends Dto {
    /**
     * @var string
     * @required
     * @label Property identifier
     */
    public $id;
    /**
     * @var string
     * @required
     * @label Property name
     */
    public $name;
    /**
     * @var string
     * @required
     * @label
     */
    public $type = 'string';
    /**
     * @var bool
     * @required
     * @label Define is property is required
     */
    public $required = false;
    /**
     * @var string
     * @required
     * @label Property description
     */
    public $description;
    /**
     * @var array
     * @required
     * @label
     */
    public $enum = [];
}