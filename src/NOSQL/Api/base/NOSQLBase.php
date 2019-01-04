<?php
namespace NOSQL\Api\base;

use PSFS\base\types\CustomApi;

/**
 * Class NOSQLBase
 * @package NOSQL\Api\base
 */
abstract class NOSQLBase extends CustomApi {
    /**
     * @Injectable
     * @var \NOSQL\Services\ParserService
     */
    protected $parser;

    public function getModelTableMap()
    {
        return null;
    }

    /**
     * @label {__API__} Manager
     * @GET
     * @route /admin/{__DOMAIN__}/{__API__}
     * @return string HTML
     */
    public function admin() {
        // TODO create nosql manager
        $this->getRequest()->redirect($this->getRoute('admin-nosql', true));
    }

    public function modelList()
    {
        return parent::modelList();
    }

    public function get($pk)
    {
        return parent::get($pk);
    }

    public function post()
    {
        return parent::post();
    }

    public function put($pk)
    {
        return parent::put($pk);
    }

    public function delete($pk = null)
    {
        return parent::delete($pk);
    }

    public function bulk()
    {
        return parent::bulk();
    }
}