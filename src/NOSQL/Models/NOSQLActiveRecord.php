<?php
namespace NOSQL\Models;

use NOSQL\Models\base\NOSQLModelTrait;
use NOSQL\Models\base\NOSQLParserTrait;
use NOSQL\Models\base\NOSQLStatusTrait;
use PSFS\base\types\traits\SingletonTrait;

/**
 * Class NOSQLActiveRecord
 * @package NOSQL\Models
 */
abstract class NOSQLActiveRecord {
    use NOSQLModelTrait;
    use NOSQLStatusTrait;
    use NOSQLParserTrait;
    use SingletonTrait;


}