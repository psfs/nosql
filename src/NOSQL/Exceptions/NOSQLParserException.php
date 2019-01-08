<?php
namespace NOSQL\Exceptions;

/**
 * Class NOSQLParserException
 * @package NOSQL\Exceptions
 */
class NOSQLParserException extends \Exception {
    const NOSQL_PARSER_DOMAIN_NOT_DEFINED = '1000';
    const NOSQL_PARSER_SCHEMA_NOT_DEFINED = '1001';
}