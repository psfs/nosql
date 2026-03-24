<?php

namespace NOSQL\Test\Integration;

use NOSQL\Services\NOSQLService;
use NOSQL\Services\ParserService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PSFS\base\Cache;
use PSFS\base\Router;
use PSFS\base\SingletonRegistry;
use PSFS\base\types\SimpleService;

final class NOSQLMongoIntegrationTest extends TestCase
{
    private const MODULE = 'ITESTNOSQL';
    private string $moduleRoot;

    protected function setUp(): void
    {
        $this->moduleRoot = CACHE_DIR . DIRECTORY_SEPARATOR . 'integration' . DIRECTORY_SEPARATOR . self::MODULE . DIRECTORY_SEPARATOR;
        @mkdir($this->moduleRoot . 'Config', 0777, true);
    }

    protected function tearDown(): void
    {
        SingletonRegistry::clear();
    }

    #[Group('integration')]
    public function testParserServiceCreateConnectionAndReadWriteFlow(): void
    {
        $collectionName = 'it_orders_' . time();
        $schema = [[
            'name' => $collectionName,
            'id' => $collectionName,
            'properties' => [
                ['name' => 'code', 'type' => 'string', 'required' => true, 'description' => 'Code'],
                ['name' => 'active', 'type' => 'boolean', 'required' => false, 'description' => 'Active'],
            ],
            'indexes' => [[
                'name' => 'idx_' . $collectionName . '_code',
                'properties' => ['code.ASC'],
                'unique' => false,
            ]],
        ]];
        file_put_contents($this->moduleRoot . 'Config' . DIRECTORY_SEPARATOR . 'schema.json', json_encode($schema, JSON_PRETTY_PRINT));

        $router = (new \ReflectionClass(Router::class))->newInstanceWithoutConstructor();
        $domainsRef = new \ReflectionProperty(Router::class, 'domains');
        $domainsRef->setAccessible(true);
        $domainsRef->setValue($router, [
            '@' . self::MODULE . '/' => ['base' => $this->moduleRoot],
        ]);
        SingletonRegistry::register($router);

        $parser = ParserService::getInstance();
        try {
            $db = $parser->createConnection(self::MODULE);
            $db->command(['ping' => 1]);
        } catch (\Throwable $e) {
            self::markTestSkipped('Mongo integration not available: ' . $e->getMessage());
        }

        $service = (new \ReflectionClass(NOSQLService::class))->newInstanceWithoutConstructor();
        $cacheRef = new \ReflectionProperty(SimpleService::class, 'cache');
        $cacheRef->setAccessible(true);
        $cacheRef->setValue($service, Cache::getInstance());

        $collections = $service->getCollections(self::MODULE);
        self::assertCount(1, $collections);
        self::assertSame($collectionName, $collections[0]['name']);

        $collection = $db->selectCollection($collectionName);
        $insert = $collection->insertOne(['code' => 'A1', 'active' => true]);
        self::assertSame(1, $insert->getInsertedCount());

        $found = $collection->findOne(['code' => 'A1']);
        self::assertNotNull($found);
    }

}
