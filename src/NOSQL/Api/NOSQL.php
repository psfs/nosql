<?php
namespace NOSQL\Api;

use NOSQL\Dto\CollectionDto;
use PSFS\base\dto\JsonResponse;
use PSFS\base\Logger;
use PSFS\base\types\CustomApi;

/**
 * Class NOSQL
 * @package NOSQL\Api
 * @Api __admin
 */
class NOSQL extends CustomApi {
    /**
     * @Injectable
     * @var \NOSQL\Services\NOSQLService
     */
    protected $srv;

    /**
     * @GET
     * @route /{__DOMAIN__}/APi/{__API__}/types
     * @return \PSFS\base\dto\JsonResponse(data=array)
     */
    public function getNOSQLTypes() {
        return $this->json(new JsonResponse($this->srv->getTypes(), true), 200);
    }

    /**
     * @GET
     * @route /{__DOMAIN__}/APi/{__API__}/domains
     * @return \PSFS\base\dto\JsonResponse(data=array)
     */
    public function readModules() {
        return $this->json(new JsonResponse($this->srv->getDomains(), true), 200);
    }

    /**
     * @GET
     * @route /{__DOMAIN__}/APi/{__API__}/{module}/collections
     * @return \PSFS\base\dto\JsonResponse(data=array)
     */
    public function readCollections($module) {
        return $this->json(new JsonResponse($this->srv->getCollections($module), true), 200);
    }

    /**
     * @PUT
     * @param string $module
     * @payload \NOSQL\Dto\CollectionDto[]
     * @route /{__DOMAIN__}/APi/{__API__}/{module}/collections
     * @return \PSFS\base\dto\JsonResponse(data=boolean)
     */
    public function storeCollections($module) {
        $success = true;
        $code = 200;
        try {
            $this->srv->setCollections($module, $this->getRequest()->getRawData());
        } catch(\Exception $exception) {
            $success = false;
            $code = 400;
            Logger::log($exception->getMessage(), LOG_WARNING);
        }
        return $this->json(new JsonResponse($success, $success), $code);
    }

    /**
     * @POST
     * @param string $module
     * @route /{__DOMAIN__}/APi/{__API__}/{module}/sync
     * @return \PSFS\base\dto\JsonResponse(data=boolean)
     */
    public function syncCollections($module) {
        $code = 200;
        try {
            $success = $this->srv->syncCollections($module);
        } catch(\Exception $exception) {
            $success = false;
            $code = 400;
            Logger::log($exception->getMessage(), LOG_WARNING);
        }
        return $this->json(new JsonResponse($success, $success), $code);

    }
}