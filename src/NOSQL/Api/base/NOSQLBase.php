<?php
namespace NOSQL\Api\base;

use NOSQL\Dto\Model\ResultsetDto;
use NOSQL\Models\NOSQLActiveRecord;
use NOSQL\Models\NOSQLQuery;
use PSFS\base\dto\JsonResponse;
use PSFS\base\Logger;
use PSFS\base\types\CustomApi;

/**
 * Class NOSQLBase
 * @package NOSQL\Api\base
 * @method NOSQLActiveRecord getModel()
 * @property NOSQLActiveRecord $model
 */
abstract class NOSQLBase extends CustomApi {
    /**
     * @Injectable
     * @var \NOSQL\Services\ParserService
     */
    protected $parser;
    /**
     * @var \MongoDB\Database
     */
    protected $con;

    public function getModelTableMap()
    {
        return null;
    }

    public function closeTransaction($status) { }

    /**
     * @throws \ReflectionException
     */
    public function init()
    {
        parent::init();
        $this->con = $this->parser->createConnection($this->getDomain());
        $this->model = $this->parser->hydrateModelFromRequest($this->data, get_called_class());
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
        $success = true;
        $code = 200;
        try {
            $className = get_called_class();
            $modelName = $className::MODEL_CLASS;
            $results = NOSQLQuery::find($modelName, $this->query, $this->con);
        } catch (\Exception $exception) {
            $results = new ResultsetDto(false);
            $success = false;
            $code = 404;
            Logger::log($exception->getMessage(), LOG_WARNING, $this->query);
        }
        return $this->json(new JsonResponse($results->items, $success, $results->count, ceil($results->count / $results->limit)), $code);
    }

    public function get($pk)
    {
        $success = true;
        $code = 200;
        try {
            $this->feedModel($pk);
        } catch (\Exception $exception) {
            $this->model = null;
            $success = false;
            $code = 404;
            Logger::log($exception->getMessage(), LOG_WARNING, [$pk]);
        }
        return $this->json(new JsonResponse(null !== $this->model ? $this->getModel()->toArray() : [], $success), $code);
    }

    public function post()
    {
        $success = true;
        $code = 200;
        try {
            $success = $this->getModel()->save($this->con);
        } catch (\Exception $exception) {
            Logger::log($exception->getMessage(), LOG_WARNING, $this->getModel()->toArray());
        }
        return $this->json(new JsonResponse($this->getModel()->toArray(), $success), $code);
    }

    public function put($pk)
    {
        $code = 200;
        try {
            $this->feedModel($pk);
            $this->getModel()->feed($this->getRequest()->getData());
            $success = $this->getModel()->update($this->con);
        } catch (\Exception $exception) {
            $this->model = null;
            $success = false;
            $code = 404;
            Logger::log($exception->getMessage(), LOG_WARNING, [$pk]);
        }
        return $this->json(new JsonResponse(null !== $this->model ? $this->getModel()->toArray() : [], $success), $code);
    }

    public function delete($pk = null)
    {
        $code = 200;
        try {
            $this->feedModel($pk);
            $success = $this->getModel()->delete($this->con);
        } catch (\Exception $exception) {
            $this->model = null;
            $success = false;
            $code = 404;
            Logger::log($exception->getMessage(), LOG_WARNING, [$pk]);
        }
        return $this->json(new JsonResponse([], $success), $code);
    }

    public function bulk()
    {
        $code = 200;
        try {
            $className = get_called_class();
            $modelName = $className::MODEL_CLASS;
            $this->model = new $modelName();
            $inserts = $this->model->bulkInsert($this->getRequest()->getData());
            $success = $inserts > 0;
        } catch (\Exception $exception) {
            $inserts = 0;
            $success = false;
            $code = 404;
            Logger::log($exception->getMessage(), LOG_WARNING);
        }
        return $this->json(new JsonResponse($success, $success, $inserts), $code);
    }

    /**
     * @param $pk
     * @throws \PSFS\base\exception\ApiException
     */
    private function feedModel($pk): void
    {
        $className = get_called_class();
        $modelName = $className::MODEL_CLASS;
        $this->model = NOSQLQuery::findPk($modelName, $pk);
    }
}