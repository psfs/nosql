<?php
namespace NOSQL\Dto;

use PSFS\base\dto\Dto;

/**
 * Class CollectionDto
 * @package NOSQL\Dto
 */
class CollectionDto extends Dto {
    /**
     * @var string
     * @required
     * @label Name of the collection
     */
    public $name;
    /**
     * @var string
     * @required
     * @label Identifier for the collection
     */
    public $id;
    /**
     * @var \NOSQL\Dto\PropertyDto[]
     */
    public $properties = [];
    /**
     * @var \NOSQL\Dto\IndexDto[]
     */
    public $indexes = [];
}