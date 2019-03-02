<?php
namespace NOSQL\Dto\Model;

use PSFS\base\dto\Dto;

/**
 * Class ResulsetDto
 * @package NOSQL\Dto\Model
 */
class ResultsetDto extends Dto {
    /**
     * @var NOSQLModelDto[]
     * @label Array of items
     */
    public $items = [];
    /**
     * @var int
     */
    public $count = 0;
    /**
     * @var int
     */
    public $page = 1;
    /**
     * @var int
     */
    public $limit = 50;
}