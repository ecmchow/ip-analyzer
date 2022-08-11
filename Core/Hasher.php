<?php

/**
 * Hashing functions
 * This file is part of the IP Analyzer service.
 *
 * @license MIT
 * (c) 2022 Eric Chow <https://cmchow.com>
 * License at https://opensource.org/licenses/MIT
 */

namespace Core;

use Core\Config;

class Hasher {

    /**
     * Get hash of data
     * @param string $data string to be hashed
     * @return string|false return a hashed text, false when failed
     */
    public static function hash(string $data) {
        switch (Config::getEnv('ANALYZER_AUTH_HASH_METHOD')) {
            case 'sodium':
                try {
                    return sodium_crypto_pwhash_str($data, SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
                } catch (\Throwable $e) {
                    Logger::log('error', "hash exception: {$e->getMessage()}, Trace: {$e->getTraceAsString()}");
                    return false;
                }
                break;
            case 'argon2i':
                return password_hash($data, PASSWORD_ARGON2I);
                break;
            default:
                return password_hash($data, PASSWORD_BCRYPT, ['cost' => 11]);
        }
    }

    /**
     * Verify the hash with user input string
     * @param string $data user input string
     * @return bool user input is verified against password hash
     */
    public static function verify(string $input): bool {
        if (!empty($input)) {
            $hash = Config::getAuthHash();
            switch (Config::getEnv('ANALYZER_AUTH_HASH_METHOD')) {
                case 'sodium':
                    try {
                        return sodium_crypto_pwhash_str_verify($hash, $input);
                    } catch (\Throwable $e) {
                        Logger::log('error', "hash verify exception: {$e->getMessage()}, Trace: {$e->getTraceAsString()}");
                        return false;
                    }
                    break;
                case 'argon2i':
                    return password_verify($input, $hash);
                    break;
                default:
                    return password_verify($input, $hash);
            }
        }
        return false;
    }
}
