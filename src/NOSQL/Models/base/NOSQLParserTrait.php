<?php
namespace NOSQL\Models\base;

use MongoDB\Database;
use NOSQL\Dto\CollectionDto;
use NOSQL\Dto\IndexDto;
use NOSQL\Dto\PropertyDto;
use NOSQL\Exceptions\NOSQLParserException;
use NOSQL\Models\NOSQLActiveRecord;
use NOSQL\Services\ParserService;
use PSFS\base\Cache;

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
     * @throws \PSFS\base\exception\GeneratorException
     */
    protected function hydrate() {
        if(empty($this->domain)) {
            throw new NOSQLParserException(t('Domain not defined'), NOSQLParserException::NOSQL_PARSER_DOMAIN_NOT_DEFINED);
        }
        $schemaFilename = CORE_DIR . DIRECTORY_SEPARATOR . $this->domain . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'schema.json';
        if(file_exists($schemaFilename)) {
            $schema = Cache::getInstance()->getDataFromFile($schemaFilename, Cache::JSON, true);
            $class = get_called_class();
            $this->schema = new CollectionDto(false);
            foreach($schema as $collection) {
                $collectionName = $collection['name'];
                if(false !== strpos($class, $collectionName)) {
                    $this->schema->fromArray($collection);
                    break;
                }
            }
        } else {
            throw  new NOSQLParserException(t('Schema file not exists'), NOSQLParserException::NOSQL_PARSER_SCHEMA_NOT_DEFINED);
        }
    }

    /**
     * @param Database $con
     * @param NOSQLActiveRecord $model
     * @return Database
     */
    public static function initConnection(Database $con = null, NOSQLActiveRecord $model)
    {
        if (null === $con) {
            $con = ParserService::getInstance()->createConnection($model->getDomain());
        }
        return $con;
    }

}