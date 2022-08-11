<?php

/**
 * Data validation
 * This file is part of the IP Analyzer service.
 *
 * @license MIT
 * (c) 2022 Eric Chow <https://cmchow.com>
 * License at https://opensource.org/licenses/MIT
 */

namespace Core;

use Core\Logger;
use Opis\JsonSchema\{
    Exceptions\SchemaException,
    Validator as JsonValidator,
    ValidationResult,
    Errors\ErrorFormatter,
    Errors\ValidationError
};

class Validator {

    /**
     * schema file path
     * @var string
     */
    private const SCHEMA_PATH = __DIR__ . '/schema/';

    /**
     * schema file path
     * @var array
     */
    private const SCHEMA_LIST = [
        'env' => ['schema://ecmchow/ip-analyzer/env.json', 'env.json'],
        'ip' => ['schema://ecmchow/ip-analyzer/ip.json', 'ip.json'],
        'iplist' => ['schema://ecmchow/ip-analyzer/iplist.json', 'iplist.json']
    ];

    /**
     * singleton instance
     * @var bool
     */
    private static $_instance;

    /**
     * JsonSchema Validator instance
     * @var JsonValidator
     */
    private static $validator = null;

    /**
     * Constructor
     */
    private function __construct() {
        if ($this::$validator === null) {
            $this::$validator = new JsonValidator();
            $this::$validator->setMaxErrors(10);

            foreach ($this::SCHEMA_LIST as $key => $value) {
                // register schema by file name
                // $this::$validator->resolver()->registerFile($value[0], $this::SCHEMA_PATH . $value[1]);

                // register schema directly into memory
                $this::$validator->resolver()->registerRaw(file_get_contents($this::SCHEMA_PATH . $value[1]), $value[0]);
            }
        }
    }

    /**
     * Disable cloning
     */
    private function __clone() {
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
     */
    public static function createInstance() {
        if (!(self::$_instance instanceof self)) {
            // error_log('new Validator instance');
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * reload singleton instance
     */
    public static function reloadInstance() {
        self::$_instance = new self();
        return self::$_instance;
    }

    /**
     * Validate data
     * @param string $key name of schema
     * @param mixed $data data to be validated
     * @throws Exception If failed to find Validator instance or schema
     */
    public static function validate(string $key, $data): array {
        if (is_string($data)) {
            $data = json_decode($data, false);
        } else {
            $data = json_decode(json_encode($data), false);
        }

        if (self::$validator !== null) {
            if (isset(self::SCHEMA_LIST[$key])) {
                try {
                    /** @var ValidationResult $result */
                    $result = self::$validator->validate($data, self::SCHEMA_LIST[$key][0]);
                    if ($result->isValid()) {
                        return [true, ''];
                    } else {
                        $formatter = new ErrorFormatter();

                        $custom = function (ValidationError $error) use ($formatter) {
                            return $formatter->formatErrorMessage($error);
                        };

                        $custom_key = function (ValidationError $error): string {
                            $path = implode('->', $error->data()->fullPath());
                            return empty($path) ? 'payload' : $path;
                        };
                        return [false, ($formatter->formatKeyed($result->error(), $custom, $custom_key))];
                    }
                } catch (SchemaException $e) {
                    Logger::log('error', "Validator exception: {$e}");
                    return [false, 'Validator exception'];
                }
            } else {
                throw new \Exception('Schema not found');
            }
        } else {
            throw new \Exception('Validator has not been initialized');
        }
    }
}
