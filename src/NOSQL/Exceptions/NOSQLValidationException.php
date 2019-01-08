<?php
namespace NOSQL\Exceptions;

/**
 * Class NOSQLValidationException
 * @package NOSQL\Exceptions
 */
final class NOSQLValidationException extends \Exception {
    const NOSQL_VALIDATION_REQUIRED = 2000;
    const NOSQL_VALIDATION_NOT_VALID = 2001;
    const NOSQL_VALIDATION_ID_ALREADY_DEFINED = 2002;
}