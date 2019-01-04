<?php
namespace NOSQL\Services;

use MongoDB\Client;
use PSFS\base\config\Config;
use PSFS\base\Singleton;

/**
 * Class ParserService
 * @package NOSQL\Services
 */
final class ParserService extends  Singleton {
    /**
     * @param $domain
     * @return \MongoDB\Database
     */
    public function createConnection($domain) {
        $lowerDomain = strtolower($domain);
        $dns = 'mongodb://';
        $dns .= Config::getParam('nosql.user', '', $lowerDomain);
        $dns .= ':' . Config::getParam('nosql.password', '', $lowerDomain);
        $dns .= '@' . Config::getParam('nosql.host', 'localhost', $lowerDomain);
        $dns .= ':' . Config::getParam('nosql.port', '27017', $lowerDomain);
        $database = Config::getParam('nosql.database', 'nosql', $lowerDomain);
        $dns .= '/' . $database;
        $client = new Client($dns);
        return $client->selectDatabase($database);
    }
}