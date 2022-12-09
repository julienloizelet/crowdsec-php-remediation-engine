<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use CrowdSec\LapiClient\Bouncer;
use CrowdSec\RemediationEngine\CacheStorage\Memcached;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CacheStorage\Redis;
use CrowdSec\RemediationEngine\LapiRemediation;
use CrowdSec\RemediationEngine\Logger\FileLog;

$startup = isset($argv[1]) ? (bool) $argv[1] : false;
$filter = isset($argv[2]) ? json_decode($argv[2], true)
    : ['scopes' => Constants::SCOPE_IP . ',' . Constants::SCOPE_RANGE];
$bouncerKey = $argv[3] ?? false;
$lapiUrl = $argv[4] ?? false;
if (!$bouncerKey || !$lapiUrl) {
    exit('Params <BOUNCER_KEY> and <LAPI_URL> are required' . \PHP_EOL
         . 'Usage: php refresh-decisions-lapi.php <STARTUP> <FILTER_JSON> <BOUNCER_KEY> <LAPI_URL>'
         . \PHP_EOL);
}

if (is_null($filter)) {
    exit('Param <FILTER_JSON> is not a valid json' . \PHP_EOL
         . 'Usage: php refresh-decisions-lapi.php <STARTUP> <FILTER_JSON> <BOUNCER_KEY> <LAPI_URL>'
         . \PHP_EOL);
}

// Init  logger
$logger = new FileLog(['debug_mode' => true]);
// Init client
$clientConfigs = [
    'auth_type' => 'api_key',
    'api_url' => $lapiUrl,
    'api_key' => $bouncerKey,
];
$lapiClient = new Bouncer($clientConfigs, null, $logger);
// Init PhpFiles cache storage
$cacheFileConfigs = [
    'fs_cache_path' => __DIR__ . '/.cache/lapi',
];
$phpFileCache = new PhpFiles($cacheFileConfigs, $logger);
// Init Memcached cache storage
$cacheMemcachedConfigs = [
    'memcached_dsn' => 'memcached://memcached:11211',
];
$memcachedCache = new Memcached($cacheMemcachedConfigs, $logger);
// Init Redis cache storage
$cacheRedisConfigs = [
    'redis_dsn' => 'redis://redis:6379',
];
$redisCache = new Redis($cacheRedisConfigs, $logger);
// Init CAPI remediation
$remediationConfigs = [];
$remediationEngine = new LapiRemediation($remediationConfigs, $lapiClient, $phpFileCache, $logger);
// Retrieve fresh decisions from CAPI and update the cache
echo json_encode($remediationEngine->refreshDecisions($startup, $filter)) . \PHP_EOL;
