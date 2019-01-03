<?php
namespace NOSQL\Models\base;

use NOSQL\Dto\CollectionDto;
use NOSQL\Dto\IndexDto;
use NOSQL\Dto\PropertyDto;
use NOSQL\Exceptions\NOSQLParserException;

/**
 * Trait NOSQLParserTrait
 * @package NOSQL\Models\base
 */
trait NOSQLParserTrait {
    protected $domain;
    /**
     * @var CollectionDto
     */
    protected $schema;

    /**
     * @return mixed
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @param mixed $domain
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * @return CollectionDto
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * @param CollectionDto $schema
     */
    public function setSchema(CollectionDto $schema)
    {
        $this->schema = $schema;
    }

    /**
     * @param PropertyDto $property
     */
    public function addProperty(PropertyDto $property) {
        $this->schema->properties[] = $property;
    }

    /**
     * @param IndexDto $index
     */
    public function addIndex(IndexDto $index) {
        $this->schema->indexes[] = $index;
    }

    /**
     * @throws NOSQLParserException
     */
    protected function hydrate() {
        if(empty($this->domain)) {
            throw new NOSQLParserException(t('Domain not defined'), NOSQLParserException::NOSQL_PARSER_DOMAIN_NOT_DEFINED);
        }
    }

}