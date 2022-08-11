<?php

/**
 * Entry file for IP Analyzer worker
 * use terminal command to start service
 * ---------
 * php start-analyzer.php start -d
 * php start-analyzer.php restart -d
 * php start-analyzer.php reload
 * php start-analyzer.php stop
 * php start-analyzer.php status
 * php start-analyzer.php connections
 * ---------
 * This file is part of the IP Analyzer service.
 *
 * @license MIT
 * (c) 2022 Eric Chow <https://cmchow.com>
 * License at https://opensource.org/licenses/MIT
 */

require_once __DIR__ . '/vendor/autoload.php';

use Core\Config;
use Core\Logger;
use Core\Analyzer;
use Core\IpSum;
use Core\Validator;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Crontab\Crontab;

use GeoIp2\Database\Reader as MmdbReader;

const ANALYZER_VERSION = '1.0.0';
const ANALYZER_NAME = 'IpAnalyzer';

// init Config and Validator instance
$env = null;
$basePath = null;
$validator = null;
$config = null;

if (isset($argv[1])) {
    echo ANALYZER_NAME . ' - version ' .  ANALYZER_VERSION . PHP_EOL;
    echo '---------------------------' . PHP_EOL;

    // accept CLI params
    if (($argv[1] === 'start' || $argv[1] === 'restart')) {
        $cmdEnv = array_search('--env', $argv);
        if ($cmdEnv !== false && isset($argv[$cmdEnv+1])) {
            $env = $argv[$cmdEnv+1];
        }
        $baseEnv = array_search('--basepath', $argv);
        if ($baseEnv !== false && isset($argv[$baseEnv+1])) {
            $basePath = $argv[$baseEnv+1];
        }
        $validator = Validator::createInstance();
        $config = Config::createInstance($env, $basePath);
    }
}

// check service is running in PHAR
if (!empty(\Phar::running(false))) {
    $parts = explode('/', \Phar::running(false));
    array_pop($parts);
    $pharPath = implode('/', $parts) . '/';
    Worker::$logFile = $pharPath . 'ip-analyzer-workerman.log';
    Worker::$pidFile = $pharPath . 'ip-analyzer-workerman.pid';
    Worker::$statusFile = $pharPath . 'ip-analyzer-workerman.status';
}

$analyzer = null; // primary service worker

// enable service SSL if needed
if (Config::isSslEnabled()) {
    if (is_readable(Config::getEnv('ANALYZER_SSL_CERT')) && is_readable(Config::getEnv('ANALYZER_SSL_KEY'))) {
        $context = [
            'ssl' => [
                'local_cert' => Config::getEnv('ANALYZER_SSL_CERT'),
                'local_pk' => Config::getEnv('ANALYZER_SSL_KEY'),
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $analyzer = new Worker(Config::serviceListenAddress(), $context);
        $analyzer->transport = 'ssl';
    } else {
        throw new \Exception('unable to read SSL cert/key file');
    }
} else {
    $analyzer = new Worker(Config::serviceListenAddress());
}

$analyzer->count = Config::getEnv('ANALYZER_WORKERS'); // worker threads

$analyzer->name = ANALYZER_NAME; // worker name

$analyzer->geoIpReaders = []; // IP Analyzer instances

// enable Redis connections
if (Config::isRedisEnabled()) {
    $analyzer->redisConnection = [];
}

$analyzer->onWorkerStart = function (Worker $worker) use ($analyzer) {
    $id = $worker->id;

    $maxMemory = Config::getEnv('ANALYZER_MAX_MEMORY');
    Timer::add(60, function () use ($worker, $maxMemory) {
        if (memory_get_usage(true) > $maxMemory * 1024 * 1024 && count($worker->connections) == 0) {
            // Restart current process if memory leak is detected.
            Worker::stopAll();
        }
    });

    $restartCron = Config::getEnv('ANALYZER_RESTART_CRON');
    if (!empty($restartCron)) {
        // Restart current process according to Cron schedule
        new Crontab($restartCron, function () use ($id) {
            // avoid restarting all workers at the same time
            Timer::add(30 * $id, function () {
                Worker::stopAll();
            }, [], false);
        });
    }

    $analyzer->redisConnection[$id] = null;
    if (Config::isRedisEnabled()) {
        try {
            // init Redis server connection
            $analyzer->redisConnection[$id] = new \Redis();
            if (!Config::connectRedis($analyzer->redisConnection[$id])) {
                Logger::log('error', "Redis connection failed");
            }
        } catch (\Throwable $e) {
            Logger::log('error', "Redis connection exception {$e->getMessage()}");
        }
    }

    $analyzer->ipSum[$id] = [];
    if (Config::isIpSumEnabled()) {
        $analyzer->ipSum[$id] = IpSum::parse();
        Logger::log('debug', "IPsum loaded");

        if (!empty(Config::getEnv('IPSUM_UPDATE_CRON'))) {
            // Download and update IPsum list
            new Crontab(Config::getEnv('IPSUM_UPDATE_CRON'), function () use ($analyzer, $id) {
                if ($id === 0) {
                    // only perform download in single worker
                    $updated = IpSum::update();
                    if ($updated) {
                        $analyzer->ipSum[$id] = IpSum::parse();
                        Logger::log('debug', "IPsum updated and reloaded");
                    } else {
                        Logger::log('error', "IPsum update failed");
                    }
                } else {
                    // check update status
                    if (isset($analyzer->reloadIpSumTimer)) {
                        Timer::del($analyzer->reloadIpSumTimer);
                    }

                    $analyzer->reloadIpSumTimer = Timer::add(1, function () use ($analyzer, $id) {
                        if (!IpSum::isUpdating()) {
                            $analyzer->ipSum[$id] = IpSum::parse();
                            Logger::log('debug', "IPsum reloaded");
                            Timer::del($analyzer->reloadIpSumTimer);
                        }
                    });
                }
            });
        }
    }

    $mmdbDir = Config::getEnv('MMDB_DIR');
    if (!is_readable($mmdbDir) && !empty(Config::getEnv('MMDB_FALLBACK'))) {
        // use fallback mmdb if not found
        $mmdbDir = Config::getEnv('MMDB_FALLBACK');
    }

    try {
        // init IP Analyzer
        $analyzer->ipReaderList[$id] = new MmdbReader($mmdbDir);
        Logger::log('debug', "GeoIP reader init");
    } catch (\Throwable $e) {
        echo "GeoIP reader init failed {$e}";
        Logger::log('error', "GeoIP reader init failed {$e}");
        $analyzer->ipReaderList[$id] = null;
    }

    if ($analyzer->ipReaderList[$id] !== null && !empty(Config::getEnv('MMDB_RELOAD_CRON'))) {
        // reload mmdb at specified cron time
        new Crontab(Config::getEnv('MMDB_RELOAD_CRON'), function () use ($analyzer, $id) {
            $mmdbDir = Config::getEnv('MMDB_DIR');
            if (!is_readable($mmdbDir) && !empty(Config::getEnv('MMDB_FALLBACK'))) {
                // use fallback mmdb if not found
                $mmdbDir = Config::getEnv('MMDB_FALLBACK');
            }

            $analyzer->ipReaderList[$id] = new MmdbReader($mmdbDir);
            Logger::log('info', "GeoIP reader reloaded");
        });
    }

    if ($id === 0) {
        // init Redis data
        if (Config::isRedisEnabled() && !is_null($analyzer->redisConnection[$id])) {
            Analyzer::resetRedisStats($analyzer->redisConnection[$id]);
        }
    }
};

$analyzer->onMessage = function (TcpConnection $connection, $payload) use ($analyzer) {
    $id = $connection->worker->id;
    $response = Analyzer::response('error', null, null);
    try {
        $data = json_decode($payload, true); // decode incoming payload
        $response = Analyzer::authenticateRequest($data, $analyzer->ipReaderList[$id], $analyzer->redisConnection[$id], $analyzer->ipSum[$id]);
    } catch (\Throwable $e) {
        Logger::log('error', "service worker exception: {$e->getMessage()}, Trace: {$e->getTraceAsString()}");
        $response = Analyzer::response('error', null, 'exception occurred');
    }
    $connection->send(json_encode($response)); // return response

    $maxRequest = Config::getEnv('ANALYZER_MAX_REQUEST');
    if ($maxRequest > 0) {
        static $requestCount = 0;
        if (++$requestCount >= $maxRequest) {
            // Restart current process if max request is exceeded
            Worker::stopAll();
        }
    }
};

$analyzer->onError = function (TcpConnection $connection, $code, $msg) {
    Logger::log('error', "service worker error ({$code}): {$msg}");
};

// when service is reloaded
Worker::$onMasterReload = function () use ($env, $basePath) {
    $validator = Validator::reloadInstance();
    $config = Config::reloadInstance($env, $basePath);

    // get active workers
    $activeWorkers = [];
    foreach (Worker::getAllWorkers() as $worker) {
        $activeWorkers[$worker->name] = $worker;
    }

    // change old worker config
    foreach ($activeWorkers as $service => $worker) {
        if ($worker->name === ANALYZER_NAME) {
            $worker->count = Config::getEnv('ANALYZER_WORKERS');
        }
    }
};

Worker::runAll();
