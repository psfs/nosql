<?php

namespace NOSQL\Test\Unit;

use NOSQL\Api\base\NOSQLBase;
use NOSQL\Dto\CollectionDto;
use NOSQL\Dto\Model\NOSQLModelDto;
use NOSQL\Dto\PropertyDto;
use NOSQL\Services\Helpers\NOSQLApiHelper;
use NOSQL\Services\ParserService;
use PHPUnit\Framework\TestCase;
use PSFS\base\exception\ApiException;

final class NOSQLParserAndApiHelperTest extends TestCase
{
    public function testHydrateModelFromRequestReturnsModelForValidApiSubclass(): void
    {
        $service = (new \ReflectionClass(ParserService::class))->newInstanceWithoutConstructor();
        $model = $service->hydrateModelFromRequest(['name' => 'Alice'], TestableParserApi::class);

        self::assertInstanceOf(TestableParserModel::class, $model);
    }

    public function testHydrateModelFromRequestReturnsNullForInvalidSubclass(): void
    {
        $service = (new \ReflectionClass(ParserService::class))->newInstanceWithoutConstructor();
        $model = $service->hydrateModelFromRequest(['name' => 'Alice'], \stdClass::class);

        self::assertNull($model);
    }

    public function testCheckAndSaveThrowsApiExceptionWhenDtoIsInvalid(): void
    {
        $service = (new \ReflectionClass(ParserService::class))->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $service->checkAndSave('NOSQL', 'items', new InvalidParserDto(false));
    }

    public function testApiHelperParseFormCoversSupportedTypes(): void
    {
        $collection = new CollectionDto(false);
        $collection->properties = [
            $this->property('count', 'int', true),
            $this->property('ratio', 'double', false),
            $this->property('enabled', 'bool', true),
            $this->property('tags', 'array', false),
            $this->property('publishedAt', 'date', false),
            $this->property('payload', 'object', false),
            $this->property('status', 'enum', true, 'OPEN|CLOSED'),
            $this->property('title', 'string', true),
        ];

        $form = NOSQLApiHelper::parseForm($collection);
        $fields = $form->toArray()['fields'];

        self::assertNotEmpty($fields);
        self::assertSame('_id', $fields[0]['name']);
        self::assertTrue((bool)$fields[0]['readonly']);
        self::assertTrue((bool)$fields[0]['pk']);

        $typesByName = [];
        foreach ($fields as $field) {
            $typesByName[$field['name']] = $field['type'];
        }
        self::assertSame('number', $typesByName['count']);
        self::assertSame('switch', $typesByName['enabled']);
        self::assertSame('select', $typesByName['tags']);
        self::assertSame('timestamp', $typesByName['publishedAt']);
        self::assertSame('textarea', $typesByName['payload']);
        self::assertSame('select', $typesByName['status']);
        self::assertSame('text', $typesByName['title']);
    }

    private function property(string $name, string $type, bool $required, string $enum = ''): PropertyDto
    {
        $property = new PropertyDto(false);
        $property->name = $name;
        $property->type = $type;
        $property->required = $required;
        $property->description = ucfirst($name);
        $property->enum = $enum;
        return $property;
    }
}

final class TestableParserApi extends NOSQLBase
{
    public const MODEL_CLASS = TestableParserModel::class;

    public function __construct()
    {
    }
}

final class TestableParserModel extends \NOSQL\Models\NOSQLActiveRecord
{
    protected $domain = 'NOSQL';

    public function __construct()
    {
        $dtoRef = new \ReflectionProperty(\NOSQL\Models\NOSQLActiveRecord::class, 'dto');
        $dtoRef->setAccessible(true);
        $dtoRef->setValue($this, new TestableParserDto(false));
    }
}

final class TestableParserDto extends NOSQLModelDto
{
    public string $name = '';
}

final class InvalidParserDto extends NOSQLModelDto
{
    /** @var string @required */
    public string $name = '';

    public function validate($throwException = false)
    {
        return ['name'];
    }
}
