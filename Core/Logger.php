<?php

/**
 * Service log functions
 * This file is part of the IP Analyzer service.
 *
 * @license MIT
 * (c) 2022 Eric Chow <https://cmchow.com>
 * License at https://opensource.org/licenses/MIT
 */

namespace Core;

use Core\Config;

class Logger {

    /**
     * log app name
     * @var string
     */
    private const LOG_NAME = 'IpAnalyzer';

    /**
     * log level definitions
     * @var array
     */
    private const LOGGER_LEVELS = [
        'debug',
        'info',
        'notice',
        'warning',
        'error'
    ];

    /**
     * Output service log
     * @param string $level log level
     * @param string $msg log message
     */
    public static function log(string $level, string $msg) {
        $name = self::LOG_NAME;
        $logLevel = array_search(Config::getEnv('ANALYZER_LOG_LEVEL'), self::LOGGER_LEVELS);

        if (array_search($level, self::LOGGER_LEVELS) >= $logLevel) {
            if (Config::getEnv('ANALYZER_LOG_OUTPUT') === 'syslog') {
                switch ($level) {
                    case 'error':
                        syslog(LOG_ERR, "[{$name}] {$msg}");
                        break;
                    case 'warning':
                        syslog(LOG_WARNING, "[{$name}] {$msg}");
                        break;
                    case 'notice':
                        syslog(LOG_NOTICE, "[{$name}] {$msg}");
                        break;
                    case 'info':
                        syslog(LOG_INFO, "[{$name}] {$msg}");
                        break;
                    default:
                        syslog(LOG_DEBUG, "[{$name}] {$msg}");
                }
            } else {
                switch ($level) {
                    case 'error':
                        error_log("[{$name}] ERROR: {$msg}");
                        break;
                    case 'warning':
                        error_log("[{$name}] WARN: {$msg}");
                        break;
                    case 'notice':
                        error_log("[{$name}] NOTICE: {$msg}");
                        break;
                    case 'info':
                        error_log("[{$name}] INFO: {$msg}");
                        break;
                    default:
                        error_log("[{$name}] DEBUG: {$msg}");
                }
            }
        }
    }
}
