<?php

namespace NOSQL\Test\Unit;

use NOSQL\Api\NOSQL;
use NOSQL\Dto\CollectionDto;
use NOSQL\Dto\PropertyDto;
use NOSQL\Models\NOSQLActiveRecord;
use NOSQL\Services\Base\NOSQLManagerTrait;
use NOSQL\Services\NOSQLService;
use PHPUnit\Framework\TestCase;
use PSFS\base\Router;
use PSFS\base\SingletonRegistry;
use PSFS\base\config\Config;
use PSFS\base\dto\JsonResponse;
use PSFS\base\types\AuthAdminController;

final class NOSQLApiControllerManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        SingletonRegistry::clear();
    }

    public function testNosqlApiReadMethodsReturn200AndExpectedPayload(): void
    {
        $api = (new \ReflectionClass(TestableNOSQLApi::class))->newInstanceWithoutConstructor();
        $service = $this->createMock(NOSQLService::class);
        $service->method('getTypes')->willReturn(['string', 'integer']);
        $service->method('getValidations')->willReturn(['required', 'email']);
        $service->method('getDomains')->willReturn(['Sales', 'Billing']);
        $service->method('getCollections')->with('Sales')->willReturn([['name' => 'Orders']]);
        $api->setService($service);

        $types = $api->getNOSQLTypes();
        self::assertSame(200, $types['status']);
        self::assertSame(['string', 'integer'], $types['body']['data']);

        $validations = $api->getFormValidations();
        self::assertSame(200, $validations['status']);
        self::assertSame(['required', 'email'], $validations['body']['data']);

        $modules = $api->readModules();
        self::assertSame(200, $modules['status']);
        self::assertSame(['Sales', 'Billing'], $modules['body']['data']);

        $collections = $api->readCollections('Sales');
        self::assertSame(200, $collections['status']);
        self::assertSame([['name' => 'Orders']], $collections['body']['data']);
    }

    public function testNosqlApiStoreAndSyncCollectionsHandleExceptions(): void
    {
        $api = (new \ReflectionClass(TestableNOSQLApi::class))->newInstanceWithoutConstructor();
        $api->setRequestData([['name' => 'Orders']]);
        $service = $this->createMock(NOSQLService::class);
        $service->method('setCollections')->willThrowException(new \RuntimeException('boom'));
        $service->method('syncCollections')->willThrowException(new \RuntimeException('boom'));
        $api->setService($service);

        $store = $api->storeCollections('Sales');
        self::assertSame(400, $store['status']);
        self::assertFalse($store['body']['success']);

        $sync = $api->syncCollections('Sales');
        self::assertSame(400, $sync['status']);
        self::assertFalse($sync['body']['success']);
    }

    public function testControllerIndexAndConfigParams(): void
    {
        $controller = new TestableNOSQLController();
        $router = (new \ReflectionClass(Router::class))->newInstanceWithoutConstructor();
        $domainsRef = new \ReflectionProperty(Router::class, 'domains');
        $domainsRef->setAccessible(true);
        $domainsRef->setValue($router, [
            '@Sales/' => ['base' => '/tmp/sales/'],
        ]);
        SingletonRegistry::register($router);
        $this->setConfigMap([
            'db.host' => 'localhost',
            'db.port' => '3306',
            'db.name' => 'test',
            'db.user' => 'test',
            'db.password' => 'test',
            'home.action' => 'admin',
            'default.language' => 'en_US',
            'debug' => true,
        ]);

        self::assertSame('render:index.html.twig', $controller->index());
        $params = $controller->configParams();
        self::assertSame(200, $params['status']);
        self::assertContains('nosql.host', $params['body']);
        self::assertContains('sales.nosql.database', $params['body']);
    }

    public function testManagerTraitGetFormAndAdminBuildExpectedResponse(): void
    {
        $manager = new TestableManagerApi();
        $adminController = (new \ReflectionClass(AuthAdminController::class))->newInstanceWithoutConstructor();
        $tplRef = new \ReflectionProperty(\PSFS\base\types\Controller::class, 'tpl');
        $tplRef->setAccessible(true);
        $tplRef->setValue($adminController, new class {
            public function render($template, array $vars = []): string
            {
                return 'admin:' . $template . ':' . ($vars['api'] ?? '');
            }
        });
        SingletonRegistry::register($adminController);

        $formResponse = $manager->getForm();
        self::assertSame(200, $formResponse['status']);
        self::assertTrue($formResponse['body']['success']);
        self::assertNotEmpty($formResponse['body']['data']['fields']);

        $adminResponse = $manager->admin();
        self::assertStringContainsString('admin:api.admin.html.twig', $adminResponse);
    }

    private function setConfigMap(array $map): void
    {
        $config = Config::getInstance();
        $property = new \ReflectionProperty(Config::class, 'config');
        $property->setAccessible(true);
        $property->setValue($config, $map);
    }
}

final class TestableNOSQLApi extends NOSQL
{
    private array $rawData = [];

    public function setService(NOSQLService $service): void
    {
        $ref = new \ReflectionProperty(NOSQL::class, 'srv');
        $ref->setAccessible(true);
        $ref->setValue($this, $service);
    }

    public function setRequestData(array $rawData): void
    {
        $this->rawData = $rawData;
    }

    protected function getRequest(): object
    {
        return new class($this->rawData) {
            private array $rawData;

            public function __construct(array $rawData)
            {
                $this->rawData = $rawData;
            }

            public function getRawData(): array
            {
                return $this->rawData;
            }
        };
    }

    public function json($response, $statusCode = 200)
    {
        return [
            'status' => $statusCode,
            'body' => ($response instanceof JsonResponse) ? $response->toArray() : $response,
        ];
    }
}

final class TestableNOSQLController extends \NOSQL\Controller\NOSQLController
{
    public function __construct()
    {
    }

    public function render($template, array $vars = [], $cookies = [], $domain = null): string
    {
        return 'render:' . $template;
    }

    public function json($response, $statusCode = 200)
    {
        return ['status' => $statusCode, 'body' => $response];
    }
}

final class TestableManagerApi
{
    use NOSQLManagerTrait;

    public const API_LIST_NAME_FIELD = '__name__';
    public const NOSQL_MODEL_PRIMARY_KEY = '_id';

    private NOSQLActiveRecord $model;

    public function __construct()
    {
        /** @var NOSQLActiveRecord $model */
        $model = (new \ReflectionClass(TestableManagerModel::class))->newInstanceWithoutConstructor();
        $dtoRef = new \ReflectionProperty(NOSQLActiveRecord::class, 'dto');
        $dtoRef->setAccessible(true);
        $dtoRef->setValue($model, new TestableManagerDto(false));

        $schema = new CollectionDto(false);
        $schema->name = 'Orders';
        $field = new PropertyDto(false);
        $field->name = 'code';
        $field->type = 'string';
        $field->required = true;
        $field->description = 'Code';
        $schema->properties = [$field];
        $schemaRef = new \ReflectionProperty(TestableManagerModel::class, 'schema');
        $schemaRef->setAccessible(true);
        $schemaRef->setValue($model, $schema);

        $this->model = $model;
    }

    public function getModel(): NOSQLActiveRecord
    {
        return $this->model;
    }

    public function getDomain(): string
    {
        return 'BLOG';
    }

    public function getApi(): string
    {
        return 'users';
    }

    public function getRoute($route = '', $absolute = false, array $params = [])
    {
        if (str_contains($route, 'admin-api-form')) {
            return '/admin/api/form/BLOG/users/nosql/{id}';
        }
        return '/BLOG/api/users/{id}';
    }

    public function _json($response, $status = 200)
    {
        return [
            'status' => $status,
            'body' => ($response instanceof JsonResponse) ? $response->toArray() : $response,
        ];
    }
}

final class TestableManagerModel extends NOSQLActiveRecord
{
    protected $domain = 'BLOG';
}

final class TestableManagerDto extends \NOSQL\Dto\Model\NOSQLModelDto
{
    public string $code = '';
}
