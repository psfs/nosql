<?php

namespace NOSQL\Services\Sync;

use NOSQL\Dto\Validation\EnumPropertyDto;
use NOSQL\Dto\Validation\JsonSchemaDto;
use NOSQL\Dto\Validation\NumberPropertyDto;
use NOSQL\Dto\Validation\StringPropertyDto;
use NOSQL\Services\Base\NOSQLBase;

final class NOSQLSchemaBuilder
{
    /**
     * @param array $raw
     * @return JsonSchemaDto
     */
    public function buildCollectionSchema(array $raw): JsonSchemaDto
    {
        $jsonSchema = new JsonSchemaDto(false);
        foreach (($raw['properties'] ?? []) as $rawProperty) {
            $property = $this->buildPropertyDto($rawProperty);
            if (array_key_exists('type', $rawProperty)) {
                $property->bsonType = $rawProperty['type'];
            }
            if (array_key_exists('description', $rawProperty)) {
                $property->description = $rawProperty['description'];
            }
            if (array_key_exists('required', $rawProperty) && $rawProperty['required']) {
                $jsonSchema->required[] = $rawProperty['name'];
            }
            $jsonSchema->properties[$rawProperty['name']] = $property->toArray();
        }

        return $jsonSchema;
    }

    /**
     * @param array $rawProperty
     * @return EnumPropertyDto|NumberPropertyDto|StringPropertyDto
     */
    private function buildPropertyDto(array $rawProperty)
    {
        switch ($rawProperty['type']) {
            case NOSQLBase::NOSQL_TYPE_INTEGER:
            case NOSQLBase::NOSQL_TYPE_DOUBLE:
            case NOSQLBase::NOSQL_TYPE_LONG:
                return new NumberPropertyDto(false);
            case NOSQLBase::NOSQL_TYPE_ENUM:
                $property = new EnumPropertyDto(false);
                $property->enum = explode('|', (string)($rawProperty['enum'] ?? ''));
                return $property;
            default:
                return new StringPropertyDto(false);
        }
    }
}
