<?php
namespace NOSQL\Api;

use PSFS\base\dto\JsonResponse;
use PSFS\base\Logger;
use PSFS\base\types\CustomApi;
use PSFS\base\types\helpers\attributes\Api;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\attributes\Route;

/**
 * Class NOSQL
 * @package NOSQL\Api
 * @Api __admin
 */
#[Api('__admin')]
class NOSQL extends CustomApi {
    /**
     * @Injectable
     * @var \NOSQL\Services\NOSQLService
     */
    #[Injectable]
    protected $srv;

    /**
     * @GET
     * @route /{__DOMAIN__}/Api/{__API__}/types
     * @return \PSFS\base\dto\JsonResponse(data=array)
     */
    #[HttpMethod('GET')]
    #[Route('/{__DOMAIN__}/Api/{__API__}/types')]
    public function getNOSQLTypes() {
        return $this->json(new JsonResponse($this->srv->getTypes(), true), 200);
    }

    /**
     * @GET
     * @route /{__DOMAIN__}/Api/{__API__}/validations
     * @return \PSFS\base\dto\JsonResponse(data=array)
     */
    #[HttpMethod('GET')]
    #[Route('/{__DOMAIN__}/Api/{__API__}/validations')]
    public function getFormValidations() {
        return $this->json(new JsonResponse($this->srv->getValidations(), true), 200);
    }

    /**
     * @GET
     * @route /{__DOMAIN__}/Api/{__API__}/domains
     * @return \PSFS\base\dto\JsonResponse(data=array)
     */
    #[HttpMethod('GET')]
    #[Route('/{__DOMAIN__}/Api/{__API__}/domains')]
    public function readModules() {
        return $this->json(new JsonResponse($this->srv->getDomains(), true), 200);
    }

    /**
     * @GET
     * @route /{__DOMAIN__}/Api/{__API__}/{module}/collections
     * @return \PSFS\base\dto\JsonResponse(data=array)
     */
    #[HttpMethod('GET')]
    #[Route('/{__DOMAIN__}/Api/{__API__}/{module}/collections')]
    public function readCollections($module) {
        return $this->json(new JsonResponse($this->srv->getCollections($module), true), 200);
    }

    /**
     * @PUT
     * @param string $module
     * @payload \NOSQL\Dto\CollectionDto[]
     * @route /{__DOMAIN__}/Api/{__API__}/{module}/collections
     * @return \PSFS\base\dto\JsonResponse(data=boolean)
     */
    #[HttpMethod('PUT')]
    #[Route('/{__DOMAIN__}/Api/{__API__}/{module}/collections')]
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
     * @route /{__DOMAIN__}/Api/{__API__}/{module}/sync
     * @return \PSFS\base\dto\JsonResponse(data=boolean)
     */
    #[HttpMethod('POST')]
    #[Route('/{__DOMAIN__}/Api/{__API__}/{module}/sync')]
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
