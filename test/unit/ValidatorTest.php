<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Core\Validator;

final class ValidatorTest extends TestCase {
    
    public function testInstanceCanBeCreatedFromValidJsonSchema(): void {
        $this->assertInstanceOf(
            Validator::class,
            Validator::createInstance()
        );
    }

    public function testInstanceCanBeReloaded(): void {
        $this->assertInstanceOf(
            Validator::class,
            Validator::reloadInstance()
        );
    }

    /**
     * @dataProvider validDataProvider
     */
    public function testCanValidateDataIsValid(array $input, array $expected): void {
        $this->assertSame(
            $expected,
            Validator::validate($input[0], $input[1])
        );
    }

    public function validDataProvider(): array {
        return [
            // case 0
            [
                [
                    'env',
                    [
                        'ANALYZER_PROTO' => 'tcp',
                        'ANALYZER_ADDR' => '127.0.0.1',
                        'ANALYZER_PORT' => 3000,
                        'ANALYZER_WORKERS' => 4,
                        'ANALYZER_MAX_MEMORY' => 64,
                        'ANALYZER_LOG' => true,
                        'ANALYZER_LOG_LEVEL' => 'notice',
                        'ANALYZER_LOG_OUTPUT' => 'error_log',
                        'ANALYZER_AUTH' => false,
                        'ANALYZER_AUTH_HASH_METHOD' => 'bcrypt',
                        'MMDB_DIR' => __DIR__ . '/../../mmdb/GeoLite2-City.mmdb',
                        'MMDB_FALLBACK' => '',
                        'MMDB_RELOAD_CRON' => ''
                    ]
                ],
                [true, ''] // expected
            ]
        ];
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testCanValidateDataIsInvalid(array $input, array $expected): void {
        $this->assertSame(
            $expected,
            Validator::validate($input[0], $input[1])
        );
    }

    public function invalidDataProvider(): array {
        return [
            // case 0
            [
                [
                    'env',
                    []
                ],
                [
                    false,
                    [
                        'payload' => ['The data (array) must match the type: object']
                    ]
                ] // expected
            ],
            // case 1
            [
                [
                    'env',
                    [
                        'ANALYZER_ADDR' => 'tcp://127.0.0.1:3000',
                        'ANALYZER_WORKERS' => 4,
                        'ANALYZER_MAX_MEMORY' => 64
                    ]
                ],
                [
                    false,
                    [
                        'payload' => ['The required properties (ANALYZER_AUTH, MMDB_DIR, MMDB_FALLBACK, MMDB_RELOAD_CRON) are missing']
                    ]
                ] // expected
            ],
            // case 2
            [
                [
                    'env',
                    [
                        'ANALYZER_ADDR' => 3000,
                        'ANALYZER_SSL' => 'false',
                        'ANALYZER_WORKERS' => '4',
                        'ANALYZER_MAX_MEMORY' => 0,
                        'ANALYZER_AUTH' => false,
                        'MMDB_DIR' => null,
                        'MMDB_FALLBACK' => false,
                        'MMDB_RELOAD_CRON' => 123
                    ]
                ],
                [
                    false,
                    [
                        'ANALYZER_ADDR' => ['The data (integer) must match the type: string'],
                        'ANALYZER_WORKERS' => ['The data (string) must match the type: integer'],
                        'ANALYZER_MAX_MEMORY' => ['Number must be greater than or equal to 16'],
                        'MMDB_DIR' => ['The data (null) must match the type: string'],
                        'MMDB_FALLBACK' => ['The data (boolean) must match the type: string'],
                        'MMDB_RELOAD_CRON' => [
                            'The data (integer) must match the type: string',
                            'The data (integer) must match the type: string'
                        ]
                    ]
                ] // expected
            ]
        ];
    }
}
