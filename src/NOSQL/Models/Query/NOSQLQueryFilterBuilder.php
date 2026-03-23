<?php

namespace NOSQL\Models\Query;

use NOSQL\Models\NOSQLActiveRecord;
use NOSQL\Models\NOSQLQuery;
use NOSQL\Services\Base\NOSQLBase;
use PSFS\base\config\Config;
use PSFS\base\types\Api;

final class NOSQLQueryFilterBuilder
{
    /**
     * @param array $criteria
     * @param NOSQLActiveRecord $model
     * @param callable $toObjectId
     * @return array
     */
    public function parseCriteria(array $criteria, NOSQLActiveRecord $model, callable $toObjectId): array
    {
        $filters = [];
        if (!empty($criteria[Api::API_COMBO_FIELD] ?? null)) {
            $filters['$text'] = ['$search' => $criteria[Api::API_COMBO_FIELD]];
        }
        foreach ($model->getSchema()->properties as $property) {
            if (array_key_exists($property->name, $criteria)) {
                $filters[$property->name] = $this->composeFilter($criteria, $property, $toObjectId);
            }
        }

        return $filters;
    }

    /**
     * @param array $criteria
     * @param \NOSQL\Dto\PropertyDto $property
     * @param callable $toObjectId
     * @return mixed
     */
    private function composeFilter(array $criteria, \NOSQL\Dto\PropertyDto $property, callable $toObjectId): mixed
    {
        $filterValue = $criteria[$property->name];
        if ('_id' === $property->name) {
            return $this->composeObjectIdFilter($filterValue, $toObjectId);
        }
        if (is_array($filterValue)) {
            return $this->composeArrayFilter($filterValue);
        }
        if (in_array($filterValue, [NOSQLQuery::NOSQL_NOT_NULL_OPERATOR], true)) {
            return [$filterValue => null];
        }
        if (in_array($property->type, [
            NOSQLBase::NOSQL_TYPE_BOOLEAN,
            NOSQLBase::NOSQL_TYPE_INTEGER,
            NOSQLBase::NOSQL_TYPE_DOUBLE,
            NOSQLBase::NOSQL_TYPE_LONG,
        ], true)) {
            return [NOSQLQuery::NOSQL_EQUAL_OPERATOR => $this->castScalarFilter($property->type, $filterValue)];
        }

        return $this->composeStringFilter($criteria, $filterValue);
    }

    /**
     * @param array $filterValue
     * @return array
     */
    private function composeArrayFilter(array $filterValue): array
    {
        $operator = $filterValue[0] ?? NOSQLQuery::NOSQL_EQUAL_OPERATOR;
        if (in_array($operator, [
            NOSQLQuery::NOSQL_NOT_NULL_OPERATOR,
            NOSQLQuery::NOSQL_IN_OPERATOR,
            NOSQLQuery::NOSQL_NOT_IN_OPERATOR,
            NOSQLQuery::NOSQL_EQUAL_OPERATOR,
            NOSQLQuery::NOSQL_NOT_EQUAL_OPERATOR,
            NOSQLQuery::NOSQL_LESS_OPERATOR,
            NOSQLQuery::NOSQL_LESS_EQUAL_OPERATOR,
            NOSQLQuery::NOSQL_GREATER_OPERATOR,
            NOSQLQuery::NOSQL_GREATER_EQUAL_OPERATOR,
        ], true)) {
            array_shift($filterValue);
            $value = array_shift($filterValue);
            if (in_array($operator, [NOSQLQuery::NOSQL_IN_OPERATOR, NOSQLQuery::NOSQL_NOT_IN_OPERATOR], true) && !is_array($value)) {
                $value = [$value];
            }
            return [$operator => $value];
        }

        return [NOSQLQuery::NOSQL_IN_OPERATOR => $filterValue];
    }

    /**
     * @param array $criteria
     * @param mixed $filterValue
     * @return array
     */
    private function composeStringFilter(array $criteria, mixed $filterValue): array
    {
        $mode = strtolower((string)($criteria[NOSQLQuery::NOSQL_STRING_MODE_FIELD] ?? Config::getParam('nosql.query.stringMode', 'contains')));
        $stringValue = preg_quote((string)$filterValue, '/');
        return match ($mode) {
            'exact' => [NOSQLQuery::NOSQL_EQUAL_OPERATOR => (string)$filterValue],
            'prefix' => ['$regex' => '^' . $stringValue, '$options' => 'i'],
            default => ['$regex' => $stringValue, '$options' => 'i'],
        };
    }

    /**
     * @param string $type
     * @param mixed $filterValue
     * @return bool|int|float
     */
    private function castScalarFilter(string $type, mixed $filterValue): bool|int|float
    {
        if ($type === NOSQLBase::NOSQL_TYPE_BOOLEAN) {
            return in_array($filterValue, ['1', 1, 'true', true], true);
        }
        if ($type === NOSQLBase::NOSQL_TYPE_INTEGER) {
            return (int)$filterValue;
        }

        return (float)$filterValue;
    }

    /**
     * @param mixed $filterValue
     * @param callable $toObjectId
     * @return array
     */
    private function composeObjectIdFilter(mixed $filterValue, callable $toObjectId): array
    {
        if (!is_array($filterValue) || count($filterValue) === 0) {
            return [NOSQLQuery::NOSQL_EQUAL_OPERATOR => $toObjectId($filterValue)];
        }
        $operator = array_shift($filterValue);
        $value = array_shift($filterValue);
        if (in_array($operator, [NOSQLQuery::NOSQL_IN_OPERATOR, NOSQLQuery::NOSQL_NOT_IN_OPERATOR], true) && is_array($value)) {
            return [$operator => array_map($toObjectId, $value)];
        }

        return [$operator => $toObjectId($value)];
    }
}
