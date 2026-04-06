<?php

namespace NOSQL\Test\Unit;

use NOSQL\Services\Sync\NOSQLSchemaBuilder;
use PHPUnit\Framework\TestCase;

final class NOSQLSchemaBuilderTest extends TestCase
{
    public function testBuildCollectionSchemaCreatesRequiredAndTypedProperties(): void
    {
        $builder = new NOSQLSchemaBuilder();
        $schema = $builder->buildCollectionSchema([
            'properties' => [
                [
                    'name' => 'code',
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Order code',
                ],
                [
                    'name' => 'amount',
                    'type' => 'double',
                    'required' => false,
                ],
                [
                    'name' => 'status',
                    'type' => 'enum',
                    'enum' => 'OPEN|CLOSED',
                    'required' => true,
                ],
            ],
        ]);

        $payload = $schema->toArray();
        self::assertSame(['code', 'status'], $payload['required']);
        self::assertSame('string', $payload['properties']['code']['bsonType']);
        self::assertSame('Order code', $payload['properties']['code']['description']);
        self::assertSame('double', $payload['properties']['amount']['bsonType']);
        self::assertSame(['OPEN', 'CLOSED'], $payload['properties']['status']['enum']);
    }
}
