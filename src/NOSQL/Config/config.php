<?php
\PSFS\bootstrap::load();
/**
 * Autogenerated config file
 */
$serviceContainer = \Propel\Runtime\Propel::getServiceContainer();
$serviceContainer->checkVersion('2.0.0-dev');
$serviceContainer->setAdapterClass('NOSQL', 'mysql');
$manager = new \Propel\Runtime\Connection\ConnectionManagerSingle();
$manager->setConfiguration(array(
    'dsn' => 'mysql:host=' . \PSFS\base\config\Config::getParam('db.host', null, 'nosql') . ';port=' . \PSFS\base\config\Config::getParam('db.port', null, 'nosql') . ';dbname=' . \PSFS\base\config\Config::getParam('db.name', null, 'nosql') . '',
    'user' => \PSFS\base\config\Config::getParam('db.user', null, 'nosql'),
    'password' => \PSFS\base\config\Config::getParam('db.password', null, 'nosql'),
    'classname' => 'Propel\\Runtime\\Connection\\PropelPDO',
    'model_paths' => array(
        0 => 'src',
        1 => 'vendor',
    ),
));
$manager->setName('NOSQL');
$serviceContainer->setConnectionManager('NOSQL', $manager);

$serviceContainer->setAdapterClass('debugNOSQL', 'mysql');
$manager = new \Propel\Runtime\Connection\ConnectionManagerSingle();
$manager->setConfiguration(array(
    'dsn' => 'mysql:host=' . \PSFS\base\config\Config::getParam('db.host', null, 'nosql') . ';port=' . \PSFS\base\config\Config::getParam('db.port', null, 'nosql') . ';dbname=' . \PSFS\base\config\Config::getParam('db.name', null, 'nosql') . '',
    'user' => \PSFS\base\config\Config::getParam('db.user', null, 'nosql'),
    'password' => \PSFS\base\config\Config::getParam('db.password', null, 'nosql'),
    'classname' => 'Propel\\Runtime\\Connection\\DebugPDO',
    'model_paths' => array(
        0 => 'src',
        1 => 'vendor',
    ),
));
$manager->setName('debugNOSQL');
$serviceContainer->setConnectionManager('debugNOSQL', $manager);

$serviceContainer->setDefaultDatasource('NOSQL');
$serviceContainer->setLoggerConfiguration('defaultLogger', array(
    'type' => 'stream',
    'path' => LOG_DIR . '/db.log',
    'level' => 300,
    'bubble' => true,
));