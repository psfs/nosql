<?php

if(!defined('NOSQL_MODULE_LOADER')) {
    define('NOSQL_MODULE_LOADER', true);
    spl_autoload_register(function (string $class) {
        if (str_starts_with($class, 'NOSQL\\')) {
            $relativeClass = substr($class, strlen('NOSQL\\'));
            $file = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    });
}
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'config.php';
