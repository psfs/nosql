<?php
namespace NOSQL\Services\base;

/**
 * Class NOSQLBase
 * This class store all the standar MongoDB definitions
 * @package NOSQL\Services\base
 */
final class NOSQLBase {
    const NOSQL_TYPE_DOUBLE = 'double';
    const NOSQL_TYPE_STRING = 'string';
    const NOSQL_TYPE_OBJECT = 'object';
    const NOSQL_TYPE_ARRAY = 'array';
    const NOSQL_TYPE_BINARY = 'binData';
    const NOSQL_TYPE_IDENTIFIER = 'objectId';
    const NOSQL_TYPE_BOOLEAN = 'bool';
    const NOSQL_TYPE_DATE = 'date';
    const NOSQL_TYPE_NULL = 'null';
    const NOSQL_TYPE_JAVASCRIPT = 'javascript';
    const NOSQL_TYPE_INTEGER = 'int';
    const NOSQL_TYPE_TIMESTAMP = 'timestamp';
    const NOSQL_TYPE_LONG = 'long';
    const NOSQL_TYPE_ENUM = 'enum';
}