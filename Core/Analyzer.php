<?php

/**
 * IP Analyzer functions
 * This file is part of the IP Analyzer service.
 *
 * @license MIT
 * (c) 2022 Eric Chow <https://cmchow.com>
 * License at https://opensource.org/licenses/MIT
 */

namespace Core;

use Core\Config;
use Core\Logger;
use Core\Validator;
use GeoIp2\Database\Reader as MmdbReader;

class Analyzer {

    /**
     * max number of ip address to analyze in a list
     * @var int
     */
    private const IP_LIST_LIMIT = 100;

    /**
     * Redis key for Success mail delivery count
     * @var string
     */
    private const REDIS_KEY_ANALYZE_SUCCESS = 'analyze:success';

    /**
     * Redis key for Failed mail delivery count
     * @var string
     */
    private const REDIS_KEY_ANALYZE_FAILED = 'analyze:failed';

    /**
     * Redis key for Success mail delivery count
     * @var string
     */
    private const REDIS_KEY_REPORT_SUCCESS = 'report:success';

    /**
     * Redis key for Failed mail delivery count
     * @var string
     */
    private const REDIS_KEY_REPORT_FAILED = 'report:failed';

    /**
     * Redis key for cached IP result
     * @var string
     */
    private const REDIS_KEY_CACHE_RESULT = 'result:';

    /**
     * Redis key for cache key index list
     * @var string
     */
    private const REDIS_KEY_CACHE_INDEX = 'index';

    /**
     * Successful IP analysis count
     * Used only when worker count is 1
     * @var int
     */
    private static $analyzeSuccess = 0;

    /**
     * Failed IP analysis count
     * Used only when worker count is 1
     * @var int
     */
    private static $analyzeFailed = 0;

    /**
     * Analyze an IP address
     * @param string $ip ip address
     * @param MmdbReader|null $geoIP GeoIP2 reader instance
     * @param \Redis|null $redis Redis instance
     * @param array $ipSum IPsum list
     */
    protected static function analyzeIp(string $ip, $geoIP, $redis, $ipSum): array {
        $record = null;
        $useRedis = !is_null($redis) && Config::isRedisCacheResult();
        $useTTL = Config::getEnv('REDIS_CACHE_EXPIRE_MODE') === 'ttl';
        $resetTTL = Config::getEnv('REDIS_CACHE_RESET_TTL_ON_GET') === true;
        $expireTTL = Config::getEnv('REDIS_CACHE_EXPIRE_TTL');
        $maxCacheItem = Config::getEnv('REDIS_CACHE_MAX_ITEM');
        $redisKey = self::REDIS_KEY_CACHE_RESULT . $ip;

        if ($useRedis) {
            // check for cache
            $cache = false;
            $cache = $redis->get($redisKey);

            if ($cache !== false) {
                // cache is present

                if ($useTTL && $resetTTL) {
                    // reset TTL
                    $redis->expire($redisKey, $expireTTL);
                }

                return $cache;
            } else {
                // no cache available
                $record = $geoIP->city($ip);
            }
        } else {
            // cache disabled
            $record = $geoIP->city($ip);
        }

        $result = [
            // continent
            'code' => $record->continent->code ?? '',
            'continent' => $record->continent->name ?? '',
            // country
            'iso' => $record->country->isoCode ?? '',
            'country' => $record->country->name ?? '',
            'isEU' => $record->country->isInEuropeanUnion ?? false,
            // city
            'city' => $record->city->name ?? '',
            'postal' => $record->postal->code ?? '',
            // Subdivision
            'div' => $record->mostSpecificSubdivision->name ?? '',
            'divIso' => $record->mostSpecificSubdivision->isoCode ?? '',
            // location
            'accuracy' => $record->location->accuracyRadius ?? 0,
            'lat' => $record->location->latitude ?? 0,
            'long' => $record->location->longitude ?? 0,
            'timezone' => $record->location->timeZone ?? 0
        ];

        if (Config::isIpSumEnabled()) {
            // threat level
            $result['threat'] = $ipSum[$ip] ?? 0;
        }

        if ($useRedis) {
            if ($useTTL) {
                // TTL mode
                if ($redis->setEx($redisKey, $expireTTL, $result) === false) {
                    Logger::log('debug', "analyzeIp unable to setEx Redis");
                }
            } else {
                // max item mode
                if ($redis->set($redisKey, $result)) {
                    // check list index
                    $update = $redis->multi();
                    $update->lPush(self::REDIS_KEY_CACHE_INDEX, $ip);
                    $update->lLen(self::REDIS_KEY_CACHE_INDEX);
                    $updateResult = $update->exec();

                    if (is_array($updateResult) && isset($updateResult[1]) && is_int($updateResult[1]) && $updateResult[1] > $maxCacheItem) {
                        // clear old keys
                        $trim = $redis->multi();
                        $trim->lRange(self::REDIS_KEY_CACHE_INDEX, $maxCacheItem, -1);
                        $trim->lTrim(self::REDIS_KEY_CACHE_INDEX, 0, $maxCacheItem - 1);
                        $trimResult = $trim->exec();

                        if (is_array($trimResult) && isset($trimResult[0]) && is_array($trimResult[0])) {
                            $delete = $redis->multi();
                            foreach ($trimResult[0] as $key) {
                                $delete->del(self::REDIS_KEY_CACHE_RESULT . $key);
                            }
                            $delete->exec();
                        }
                    }
                } else {
                    Logger::log('debug', "analyzeIp unable to set Redis");
                }
            }
        }

        return $result;
    }

    /**
     * Get geo info on an IP address
     * @param mixed $data incoming payload
     * @param MmdbReader|null $geoIP GeoIP2 reader instance
     * @param \Redis|null $redis Redis instance
     * @param array $ipSum IPsum list
     */
    protected static function getIpInfo($data, $geoIP, $redis, $ipSum): array {
        $response = self::response('error', null, 'invalid ip');

        [$valid, $error] = Validator::validate('ip', $data);

        if ($valid) {
            try {
                $ip = $data['ip'];
                $result = self::analyzeIp($ip, $geoIP, $redis, $ipSum);
                $response = self::response('success', $result, null);

                if (Config::isRedisEnabled() && !is_null($redis)) {
                    $redis->incr(self::REDIS_KEY_ANALYZE_SUCCESS);
                } elseif (Config::isSingleWorker()) {
                    ++static::$analyzeSuccess;
                }
            } catch (\Throwable $e) {
                Logger::log('debug', "getIpInfo exception: {$e->getMessage()})");
                $response = self::response('error', null, $e->getMessage());

                if (Config::isRedisEnabled() && !is_null($redis)) {
                    $redis->incr(self::REDIS_KEY_ANALYZE_FAILED);
                } elseif (Config::isSingleWorker()) {
                    ++static::$analyzeFailed;
                }
            }
        } else {
            Logger::log('debug', "invalid ip payload: " . json_encode($error));
        }
        return $response;
    }

    /**
     * Get geo info on a list of IP address
     * @param mixed $data incoming payload
     * @param MmdbReader|null $geoIP GeoIP2 reader instance
     * @param \Redis|null $redis Redis instance
     * @param array $ipSum IPsum list
     */
    protected static function getIpListInfo($data, $geoIP, $redis, $ipSum): array {
        $response = self::response('error', null, 'invalid ip list');

        [$valid, $error] = Validator::validate('iplist', $data);

        if ($valid) {
            $list = $data['iplist'];
            if (count($list) <= self::IP_LIST_LIMIT) {
                $result = [];
                try {
                    foreach ($list as $ip) {
                        if (is_string($ip) && !empty($ip)) {
                            try {
                                $result[$ip] = self::analyzeIp($ip, $geoIP, $redis, $ipSum);

                                if (Config::isRedisEnabled() && !is_null($redis)) {
                                    $redis->incr(self::REDIS_KEY_ANALYZE_SUCCESS);
                                } elseif (Config::isSingleWorker()) {
                                    ++static::$analyzeSuccess;
                                }
                            } catch (\Throwable $e) {
                                Logger::log('debug', "analyzeIp exception: {$e->getMessage()})");

                                if (Config::isRedisEnabled() && !is_null($redis)) {
                                    $redis->incr(self::REDIS_KEY_ANALYZE_FAILED);
                                } elseif (Config::isSingleWorker()) {
                                    ++static::$analyzeFailed;
                                }
                            }
                        }
                    }
                    $response = self::response('success', $result, null);
                } catch (\Throwable $e) {
                    Logger::log('debug', "getIpListInfo exception: {$e->getMessage()})");
                    $response = self::response('error', null, $e->getMessage());
                }
            } else {
                $response = self::response('error', null, 'max. ' . self::IP_LIST_LIMIT . ' ip address is allowed');
            }
        } else {
            Logger::log('debug', "invalid iplist payload: " . json_encode($error));
        }
        return $response;
    }

    /**
     * Ping request
     */
    protected static function ping(): array {
        return self::response('success', null, 'pong');
    }

    /**
     * Status request
     * @param \Redis|null $redis Redis instance
     */
    protected static function status($redis): array {
        $analyzed = 0;
        $failed = 0;

        if (Config::isRedisEnabled() && !is_null($redis)) {
            $analyzed = $redis->get(self::REDIS_KEY_ANALYZE_SUCCESS);
            $failed = $redis->get(self::REDIS_KEY_ANALYZE_FAILED);
        } elseif (Config::isSingleWorker()) {
            $analyzed = static::$analyzeSuccess;
            $failed = static::$analyzeFailed;
        } else {
            return self::response('error', null, 'Redis store or running in single worker is required');
        }

        return self::response('success', [
            'analyzed' => $analyzed !== false ? $analyzed : 0,
            'failed' => $failed !== false ? $failed : 0
        ], null);
    }

    /**
     * Process incoming request
     * @param string $request request method name
     * @param mixed $payload incoming payload
     * @param MmdbReader|null $geoIP GeoIP2 reader instance
     * @param \Redis|null $redis Redis instance
     * @param array $ipSum IPsum list
     */
    protected static function processRequest(string $request, $data, $geoIP, $redis, $ipSum): array {
        $response = self::response('error', null, 'invalid request');

        switch ($request) {
            case 'ping':
                $response = self::ping();
                break;
            case 'status':
                $response = self::status($redis);
                break;
            case 'ip':
                $response = self::getIpInfo($data, $geoIP, $redis, $ipSum);
                break;
            case 'iplist':
                $response = self::getIpListInfo($data, $geoIP, $redis, $ipSum);
                break;
            default:
                Logger::log('warning', "invalid request");
        }

        Logger::log('debug', "request processed: {$request}");
        return $response;
    }

    /**
     * Authenticate incoming request
     * @param mixed $data incoming payload
     * @param MmdbReader|null $geoIP GeoIP2 reader instance
     * @param \Redis|null $redis Redis instance
     * @param array $ipSum IPsum list
     */
    public static function authenticateRequest($data, $geoIP, $redis, array $ipSum): array {
        $response = self::response('error', null, 'invalid request');

        $requireAuth = Config::getEnv('ANALYZER_AUTH');
        $auth = false;

        if ($requireAuth) {
            if (!empty($data) && is_array($data) && array_key_exists('auth', $data) && Hasher::verify($data['auth'])) { // verify auth password
                $auth = true;
                unset($data['auth']);
            }
        } else {
            $auth = true;
        }

        if ($auth) {
            if (!empty($data) && is_array($data)) {
                $request = array_key_first($data);
                if ($request !== null) {
                    $response = self::processRequest($request, $data, $geoIP, $redis, $ipSum);
                } else {
                    Logger::log('warning', "request with invalid payload");
                    $response = self::response('error', null, 'invalid payload');
                }
            } else {
                Logger::log('warning', "request with empty payload");
                $response = self::response('error', null, 'payload cannot be empty');
            }
        } else {
            Logger::log('warning', "unauthorized request");
            $response = self::response('error', null, 'unauthorized request');
        }

        return $response;
    }

    /**
     * API response object
     * @param string $status status string
     * @param mixed $data response data (null if none)
     * @param mixed $message additional message (null if none)
     */
    public static function response(string $status, $data, $message): array {
        return [
            'status' => $status,
            'data' => $data,
            'message' => $message
        ];
    }

    /**
     * Reset Redis count
     * @param \Redis|null $redis Redis instance
     */
    public static function resetRedisStats($redis): bool {
        if (Config::getEnv('REDIS_RESET_STATS_ON_START')) {
            $redis->set(self::REDIS_KEY_ANALYZE_SUCCESS, 0);
            $redis->set(self::REDIS_KEY_ANALYZE_FAILED, 0);
            $redis->set(self::REDIS_KEY_REPORT_SUCCESS, 0);
            $redis->set(self::REDIS_KEY_REPORT_FAILED, 0);
        } else {
            $redis->setNx(self::REDIS_KEY_ANALYZE_SUCCESS, 0);
            $redis->setNx(self::REDIS_KEY_ANALYZE_FAILED, 0);
            $redis->setNx(self::REDIS_KEY_REPORT_SUCCESS, 0);
            $redis->setNx(self::REDIS_KEY_REPORT_FAILED, 0);
        }
        return false;
    }
}
