<?php
namespace NOSQL\Controller\base;
use PSFS\base\types\AuthAdminController;

/**
* Class NOSQLBaseController
* @package NOSQL\Controller\base
* @author Fran López <fran.lopez84@hotmail.es>
* @version 1.0
* @Api NOSQL
* Autogenerated controller [2019-01-03 15:30:45]
*/
abstract class NOSQLBaseController extends AuthAdminController {

    const DOMAIN = 'NOSQL';

    /**
    * @Autoload
    * @var \NOSQL\Services\NOSQLService
    */
    protected $srv;

    /**
    * Constructor por defecto
    */
    function __construct() {
        $this->init();
        $this->setDomain('NOSQL')
            ->setTemplatePath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Templates');
    }

}
