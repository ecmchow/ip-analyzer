<?php

/**
 * Service env variables
 * This file is part of the IP Analyzer service.
 *
 * @license MIT
 * (c) 2022 Eric Chow <https://cmchow.com>
 * License at https://opensource.org/licenses/MIT
 */

namespace Core;

use Core\Validator;

/*
 * Block direct access to this file
 */

if (count(get_included_files()) == 1) {
    die();
}

class Config {

    /**
     * singleton instance
     * @var Config
     */
    private static $_instance;

    /**
     * base working directory
     * @var string
     */
    private static $basePath = __DIR__ . '/../';

    /**
     * path to env file
     * @var string
     */
    private static $envPath = '.env';

    /**
     * env variables with default values
     * @var array
     */
    private static $env = [
        'ANALYZER_PROTO' => 'tcp',
        'ANALYZER_ADDR' => '127.0.0.1',
        'ANALYZER_PORT' => 3000,
        'ANALYZER_SSL_CERT' => '',
        'ANALYZER_SSL_KEY' => '',
        'ANALYZER_WORKERS' => 1,
        'ANALYZER_MAX_MEMORY' => 64,
        'ANALYZER_MAX_REQUEST' => -1,
        'ANALYZER_RESTART_CRON' => '',
        'ANALYZER_LOG' => true,
        'ANALYZER_LOG_LEVEL' => 'notice',
        'ANALYZER_LOG_OUTPUT' => 'error_log',
        'ANALYZER_AUTH' => false,
        'ANALYZER_AUTH_HASH_METHOD' => 'bcrypt',
        'REDIS_ENABLE' => false,
        'REDIS_PROTO' => 'tcp',
        'REDIS_ADDR' => '127.0.0.1',
        'REDIS_PORT' => 6379,
        'REDIS_KEY_PREFIX' => 'IP_ANALYZER:',
        'REDIS_TIMEOUT' => 0,
        'REDIS_RETRY_INTERVAL' => 100,
        'REDIS_READ_TIMEOUT' => 10,
        'REDIS_CACHE_RESULT' => true,
        'REDIS_CACHE_EXPIRE_MODE' => 'ttl',
        'REDIS_CACHE_EXPIRE_TTL' => 3600,
        'REDIS_CACHE_MAX_ITEM' => 100,
        'REDIS_RESET_STATS_ON_START' => true,
        'MMDB_DIR' => '/usr/share/GeoIP/GeoLite2-City.mmdb',
        'MMDB_FALLBACK' => '',
        'MMDB_RELOAD_CRON' => '',
        'IPSUM_ENABLE' => false,
        'IPSUM_URL' => '',
        'IPSUM_DIR' => 'blacklist/ipsum.txt',
        'IPSUM_MAX_LINES' => 100000,
        'IPSUM_MIN_LEVEL' => 2,
        'IPSUM_UPDATE_CRON' => '15 1 * * *'
    ];

    /**
     * Service auth password hash
     * @var string
     */
    private static $authHash = null;

    /**
     * Redis user
     * @var string
     */
    private static $redisUser = null;

    /**
     * Redis password
     * @var string
     */
    private static $redisPassword = null;

    /**
     * Constructor
     * @param string|null $envPath custom env file path
     * @param string|null $basePath custom base path
     */
    private function __construct($envPath = null, $basePath = null) {
        $this::readEnv($envPath, $basePath);
        $this::validateEnv();
    }

    /**
     * Disable cloning
     */
    private function __clone() {
    }

    /**
     * Returns whether the file path is an absolute path.
     * @param string $file file path
     * (https://github.com/symfony/symfony/blob/6.1/src/Symfony/Component/Filesystem/Filesystem.php#method_isAbsolutePath)
     */
    private static function isAbsolutePath(string $file): bool {
        return '' !== $file && (
            strspn($file, '/\\', 0, 1)
            || (
                \strlen($file) > 3 && ctype_alpha($file[0])
                && ':' === $file[1]
                && strspn($file, '/\\', 2, 1)
            )
            || null !== parse_url($file, \PHP_URL_SCHEME)
        );
    }

    /**
     * Validate directory access rights
     * @param string $key name of env variable
     * @throws Exception If unable to read/write directory
     */
    private static function validateReadWriteAccess(string $key) {
        if (!is_readable(self::$env[$key]) || !is_writable(self::$env[$key])) {
            throw new \Exception("Unable to read/write to {$key}");
        }
    }

    /**
     * Validate env variables
     * @throws Exception If any env variable is invalid
     */
    private static function validateEnv() {
        [$valid, $error] = Validator::validate('env', self::$env);

        if ($valid) {
            if (self::$env['ANALYZER_AUTH']) {
                if (self::$authHash === null) {
                    throw new \Exception('ANALYZER_AUTH_HASH cannot be empty');
                }
            }
        } else {
            throw new \Exception(json_encode($error));
        }
    }

    /**
     * Read and parse env variables
     * @param string|null $envPath custom env file path
     * @param string|null $basePath custom base path
     * @throws Exception If failed to load env file
     */
    private static function readEnv($envPath = null, $basePath = null) {
        self::$basePath = __DIR__ . '/../';
        self::$envPath = '.env';
        $pharPath = \Phar::running(false);
        if (!empty($pharPath)) {
            $parts = explode('/', $pharPath);
            array_pop($parts);
            self::$basePath = implode('/', $parts) . '/';
        }
        if (is_string($basePath) && !empty($pharPath)) {
            if (self::isAbsolutePath($basePath)) {
                // absolute path
                self::$basePath = $basePath;
            } else {
                // relative path
                self::$basePath .= $basePath;
            }
        }

        $iniPath = (self::$basePath . self::$envPath);
        if (is_string($envPath) && !empty($envPath)) {
            if (self::isAbsolutePath($envPath)) {
                // absolute path
                $iniPath = $envPath;
            } else {
                // relative path
                self::$envPath = $envPath;
                $iniPath = (self::$basePath . $envPath);
            }
        }
        if (is_readable($iniPath)) {
            $tempEnv = parse_ini_file($iniPath, false, INI_SCANNER_TYPED);
            if ($tempEnv !== false) {
                if (!isset($tempEnv['ANALYZER_ADDR'])) {
                    throw new \Exception('ANALYZER_ADDR cannot be empty');
                }
                if (isset($tempEnv['ANALYZER_AUTH_HASH'])) {
                    self::$authHash = $tempEnv['ANALYZER_AUTH_HASH'];
                    unset($tempEnv['ANALYZER_AUTH_HASH']);
                }
                if (isset($tempEnv['REDIS_USER'])) {
                    self::$redisUser = $tempEnv['REDIS_USER'];
                    unset($tempEnv['REDIS_USER']);
                }
                if (isset($tempEnv['REDIS_PASSWORD'])) {
                    self::$redisPassword = $tempEnv['REDIS_PASSWORD'];
                    unset($tempEnv['REDIS_PASSWORD']);
                }
                foreach ($tempEnv as $key => $value) {
                    switch ($key) {
                        case 'ANALYZER_SSL_CERT':
                        case 'ANALYZER_SSL_KEY':
                        case 'MMDB_DIR':
                        case 'MMDB_FALLBACK':
                            if (self::isAbsolutePath($value)) {
                                // absolute path
                                self::$env[$key] = $value;
                            } else {
                                // relative path
                                self::$env[$key] = self::$basePath . $value;
                            }
                            break;
                        default:
                            self::$env[$key] = $value;
                    }
                }
            } else {
                throw new \Exception('env parse error');
            }
        } else {
            throw new \Exception("env file does not exist");
        }
    }

    /**
     * Get calling class
     * https://gist.github.com/hamstar/1122679
     */
    private static function getCallingClass() {

        // get the trace
        $trace = debug_backtrace();

        // Get the class that is asking for who awoke it
        $class = $trace[1]['class'];

        // +1 to i cos we have to account for calling this function
        for ($i = 1; $i < count($trace); $i++) {
            if (isset($trace[$i])) { // is it set?
                if ($class != $trace[$i]['class']) { // is it a different class
                    return $trace[$i]['class'];
                }
            }
        }
    }

    /**
     * Redis connection address
     */
    private static function redisListenAddress(): string {
        $proto = self::$env['REDIS_PROTO'];
        return ($proto !== 'tcp' ? "{$proto}://" : '') . self::$env['REDIS_ADDR'];
    }

    /**
     * call
     */
    public function __call($name, $args) {
        return call_user_func_array([self::$_instance, $name], $args);
    }

    /**
     * callStatic
     */
    public static function __callStatic($name, $args) {
        return call_user_func_array([self::$_instance, $name], $args);
    }

    /**
     * create singleton instance
     * @param string|null $envPath custom env file path
     * @param string|null $basePath custom base path
     */
    public static function createInstance($envPath = null, $basePath = null) {
        if (!(self::$_instance instanceof self)) {
            // error_log('new Config instance');
            self::$_instance = new self($envPath, $basePath);
        }
        return self::$_instance;
    }

    /**
     * reload singleton instance
     * @param string|null $envPath custom env file path
     * @param string|null $basePath custom base path
     */
    public static function reloadInstance($envPath = null, $basePath = null) {
        // error_log('reload Config instance');
        self::$_instance = new self($envPath, $basePath);
        return self::$_instance;
    }

    /**
     * Get env variable by key
     * @param string $key name of env variable
     * @return mixed env values
     * @throws Exception If failed to find env variable
     */
    public static function getEnv(string $key) {
        if (!empty($key) && array_key_exists($key, self::$env)) {
            return self::$env[$key];
        } else {
            throw new \Exception('env variable does not exist');
        }
    }

    /**
     * Get auth password hash
     */
    public static function getAuthHash(): string {
        if (self::getCallingClass() === 'Core\Hasher') {
            return self::$authHash;
        }
        return '';
    }

    /**
     * get working base directory
     */
    public static function getBasePath(): string {
        return self::$basePath;
    }

    /**
     * get service is using single worker only
     */
    public static function isSingleWorker(): bool {
        return self::$env['ANALYZER_WORKERS'] === 1;
    }

    /**
     * check Redis is enabled in settings
     */
    public static function isRedisEnabled(): bool {
        return self::$env['REDIS_ENABLE'] === true;
    }

    /**
     * Redis is caching IP result
     */
    public static function isRedisCacheResult(): bool {
        return self::$env['REDIS_CACHE_RESULT'] === true;
    }

    /**
     * Init Redis connection
     * @param \Redis|null $redis Redis instance
     */
    public static function setRedisOptions($redis) {
        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
        $redis->setOption(\Redis::OPT_PREFIX, self::$env['REDIS_KEY_PREFIX']);
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_NOPREFIX);
    }

    /**
     * Init Redis connection
     * @param \Redis|null $redis Redis instance
     */
    public static function connectRedis($redis): bool {
        if (!empty(self::$redisUser) || !empty(self::$redisPassword)) {
            $auth = [
                'auth' => []
            ];
            if (!empty(self::$redisUser)) {
                $auth['auth']['user'] = self::$redisUser;
            }
            if (!empty(self::$redisPassword)) {
                $auth['auth']['pass'] = self::$redisPassword;
            }

            $connected = $redis->connect(self::redisListenAddress(), self::$env['REDIS_PORT'], self::$env['REDIS_TIMEOUT'], null, self::$env['REDIS_RETRY_INTERVAL'], self::$env['REDIS_READ_TIMEOUT'], $auth);
            self::setRedisOptions($redis);
            return $connected;
        }

        $connected = $redis->connect(self::redisListenAddress(), self::$env['REDIS_PORT'], self::$env['REDIS_TIMEOUT'], null, self::$env['REDIS_RETRY_INTERVAL'], self::$env['REDIS_READ_TIMEOUT']);
        self::setRedisOptions($redis);
        return $connected;
    }

    /**
     * check IPsum is enabled in settings
     */
    public static function isIpSumEnabled(): bool {
        return self::$env['IPSUM_ENABLE'] === true;
    }

    /**
     * service SSL is enable
     */
    public static function isSslEnabled(): bool {
        return self::$env['ANALYZER_PROTO'] === 'ssl';
    }

    /**
     * return full service listen address
     */
    public static function serviceListenAddress(): string {
        $serverProto = self::$env['ANALYZER_PROTO'];
        $serverAddr = self::$env['ANALYZER_ADDR'];
        $serverPort = strval(self::$env['ANALYZER_PORT']);
        return "{$serverProto}://{$serverAddr}:{$serverPort}";
    }
}
