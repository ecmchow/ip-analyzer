<?php

/**
 * IPsum blacklist parser
 * This file is part of the IP Analyzer service.
 *
 * @license MIT
 * (c) 2022 Eric Chow <https://cmchow.com>
 * License at https://opensource.org/licenses/MIT
 */

namespace Core;

use Core\Config;

class IpSum {

    /**
     * Get IPsum txt last updated time
     * @return int|false file modified time or failed to read
     */
    public static function lastUpdatedTime() {
        $ipsumFile = Config::getEnv('IPSUM_DIR');
        return filemtime($ipsumFile);
    }

    /**
     * Check IPsum txt is updating
     */
    public static function isUpdating() {
        $output = Config::getEnv('IPSUM_DIR');
        $fp = fopen($output, 'r');
        if (!flock($fp, LOCK_SH | LOCK_NB)) {
            fclose($fp);
            return true;
        }
        return false;
    }

    /**
     * Download latest IPsum txt
     */
    public static function update(): bool {
        $url = Config::getEnv('IPSUM_URL');
        $output = Config::getEnv('IPSUM_DIR');

        if (!empty($url) && !empty($output)) {
            try {
                $fp = fopen($output, 'w+');
                if ($fp) {
                    if (flock($fp, LOCK_EX)) {
                        $c = curl_init($url);
                        $options = [
                            CURLOPT_ENCODING => '',
                            CURLOPT_FILE => $fp,
                            CURLOPT_CONNECTTIMEOUT => 60,
                            CURLOPT_FOLLOWLOCATION => true
                        ];
                        curl_setopt_array($c, $options);
                        curl_exec($c);
                        curl_close($c);
                        fflush($fp);
                        flock($fp, LOCK_UN);
                        fclose($fp);
                        return true;
                    } else {
                        Logger::log('error', "IPsum update failed to acquire an exclusive lock");
                    }
                    fclose($fp);
                } else {
                    Logger::log('error', "IPsum update failed to fopen");
                }
            } catch (\Throwable $e) {
                Logger::log('error', "IPsum update exception: {$e->getMessage()}");
            }
        }
        return false;
    }

    /**
     * Parse IPsum txt
     * @return array an associative array of IPsum list
     */
    public static function parse(): array {
        $blacklist = [];
        $ipsumFile = Config::getEnv('IPSUM_DIR');
        $minLevel = Config::getEnv('IPSUM_MIN_LEVEL');
        $maxLines = Config::getEnv('IPSUM_MAX_LINES');
        $fp = fopen($ipsumFile, 'r');
        if ($fp) {
            $count = 0;
            while (($count < $maxLines && $line = fgets($fp)) !== false) {
                if ($line[0] !== '#') {
                    $split = explode("\t", $line, 2);
                    if ($split !== false) {
                        $level = intval($split[1], 10) ?? 0;
                        if ($level >= $minLevel) {
                            $blacklist[$split[0]] = $level;
                        }
                    }
                    ++$count;
                }
            }
            fclose($fp);
        }
        return $blacklist;
    }
}
