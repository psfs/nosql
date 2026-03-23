<?php
namespace NOSQL\Services\Base;

use NOSQL\Services\Helpers\NOSQLApiHelper;
use PSFS\base\dto\JsonResponse;
use PSFS\base\types\AuthAdminController;
use PSFS\base\types\helpers\ApiFormHelper;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Visible;

/**
 * Trait NOSQLManagetTrait
 * @package NOSQL\Services\base
 */
trait NOSQLManagerTrait {
    /**
     * @label Returns form data for any nosql document
     * @POST
     * @visible false
     * @route /admin/api/form/{__DOMAIN__}/{__API__}/nosql
     * @return \PSFS\base\dto\JsonResponse(data=\PSFS\base\dto\Form)
     * @throws \Exception
     */
    #[Label('Returns form data for any nosql document')]
    #[HttpMethod('POST')]
    #[Visible(false)]
    #[Route('/admin/api/form/{__DOMAIN__}/{__API__}/nosql')]
    public function getForm()
    {
        $form = NOSQLApiHelper::parseForm($this->getModel()->getSchema());
        $form->actions = ApiFormHelper::checkApiActions(get_called_class(), $this->getDomain(), $this->getApi());

        return $this->_json(new JsonResponse($form->toArray(), TRUE), 200);
    }

    protected function generateForm() {

    }

    /**
     * @label {__API__} NOSQL Manager
     * @GET
     * @route /admin/{__DOMAIN__}/{__API__}/manager
     * @return string HTML
     */
    #[Label('{__API__} NOSQL Manager')]
    #[HttpMethod('GET')]
    #[Route('/admin/{__DOMAIN__}/{__API__}/manager')]
    public function admin() {
        $domain = $this->getDomain();
        $api = $this->getApi();
        $data = array(
            "api" => $api,
            "domain" => $this->getDomain(),
            "listLabel" => self::API_LIST_NAME_FIELD,
            'modelId' => self::NOSQL_MODEL_PRIMARY_KEY,
            'formUrl' => preg_replace('/\/\{(.*)\}$/i', '', $this->getRoute(strtolower('admin-api-form-' . $domain . '-' . $api . '-nosql'), TRUE)),
            "url" => preg_replace('/\/\{(.*)\}$/i', '', $this->getRoute(strtolower($domain . '-' . 'api-' . $api . "-pk"), TRUE)),
        );
        return AuthAdminController::getInstance()->render('api.admin.html.twig', $data, [], '');
    }
}
