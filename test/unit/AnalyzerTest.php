<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Core\Config;
use Core\Analyzer;
use GeoIp2\Database\Reader as MmdbAnalyzer;

define('UNITTEST_MOCK_TIME', time());

final class AnalyzerTest extends TestCase {

    private static $config = null;
    private static $geoIP = null;
    private static $capture = null;
    private static $currentLogSetting = null;

    private static $ipTestData = [
        '128.101.101.101' => [
            'code' => 'NA',
            'continent' => 'North America',
            'iso' => 'US',
            'country' => 'United States',
            'isEU' => false,
            'city' => 'Minneapolis',
            'postal' => '55423',
            'div' => 'Minnesota',
            'divIso' => 'MN',
            'accuracy' => 20,
            'lat' => 44.8769,
            'long' => -93.2535,
            'timezone' => 'America/Chicago'
        ],
        '::ffff:8065:6565' => [
            'code' => 'NA',
            'continent' => 'North America',
            'iso' => 'US',
            'country' => 'United States',
            'isEU' => false,
            'city' => 'Minneapolis',
            'postal' => '55423',
            'div' => 'Minnesota',
            'divIso' => 'MN',
            'accuracy' => 20,
            'lat' => 44.8769,
            'long' => -93.2535,
            'timezone' => 'America/Chicago'
        ],
        '156.33.241.5' => [
            'code' => 'NA',
            'continent' => 'North America',
            'iso' => 'US',
            'country' => 'United States',
            'isEU' => false,
            'city' => 'Washington',
            'postal' => '20002',
            'div' => 'District of Columbia',
            'divIso' => 'DC',
            'accuracy' => 50,
            'lat' => 38.9034,
            'long' => -76.9882,
            'timezone' => 'America/New_York'
        ],
        '2601:481:8600:b3c0:bd63:8f82:1022:1c5b' => [
            'code' => 'NA',
            'continent' => 'North America',
            'iso' => 'US',
            'country' => 'United States',
            'isEU' => false,
            'city' => 'White House',
            'postal' => '37188',
            'div' => 'Tennessee',
            'divIso' => 'TN',
            'accuracy' => 20,
            'lat' => 36.4566,
            'long' => -86.6638,
            'timezone' => 'America/Chicago'
        ],
        '161.23.77.210' => [
            'code' => 'EU',
            'continent' => 'Europe',
            'iso' => 'GB',
            'country' => 'United Kingdom',
            'isEU' => false,
            'city' => 'London',
            'postal' => 'EC4N',
            'div' => 'England',
            'divIso' => 'ENG',
            'accuracy' => 5,
            'lat' => 51.5095,
            'long' => -0.0955,
            'timezone' => 'Europe/London'
        ],
        '133.11.93.255' => [
            'code' => 'AS',
            'continent' => 'Asia',
            'iso' => 'JP',
            'country' => 'Japan',
            'isEU' => false,
            'city' => 'Bunkyo-ku',
            'postal' => '112-8001',
            'div' => 'Tokyo',
            'divIso' => '13',
            'accuracy' => 5,
            'lat' => 35.7201,
            'long' => 139.7439,
            'timezone' => 'Asia/Tokyo'
        ],
        '203.198.7.66' => [
            'code' => 'AS',
            'continent' => 'Asia',
            'iso' => 'HK',
            'country' => 'Hong Kong',
            'isEU' => false,
            'city' => 'Central',
            'postal' => '',
            'div' => 'Central and Western District',
            'divIso' => 'HCW',
            'accuracy' => 5,
            'lat' => 22.2908,
            'long' => 114.1501,
            'timezone' => 'Asia/Hong_Kong'
        ]
    ];

    private static function expectedResponse(string $status, $data, $message): array {
        return [
            'status' => $status,
            'data' => $data,
            'message' => $message
        ];
    }

    public static function setUpBeforeClass(): void {
        self::$currentLogSetting = ini_get('error_log');

        self::$capture = tmpfile();
        ini_set('error_log', stream_get_meta_data(self::$capture)['uri']);
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testCanValidateRequestIsInvalid($input, array $expected): void {
        self::$config = Config::reloadInstance(__DIR__ . '/../env/auth-disabled.env');
        self::$geoIP = new MmdbAnalyzer(__DIR__ . '/../../mmdb/GeoLite2-City.mmdb');
        $this->assertSame(
            $expected,
            Analyzer::authenticateRequest($input, self::$geoIP, null, [])
        );
    }

    public function invalidDataProvider(): array {
        return [
            // case 0
            [
                [],
                self::expectedResponse('error', null, 'payload cannot be empty') // expected
            ],
            // case 1
            [
                null,
                self::expectedResponse('error', null, 'payload cannot be empty') // expected
            ],
            // case 2
            [
                [
                    'auth' => 'abc12345'
                ],
                self::expectedResponse('error', null, 'invalid request') // expected
            ],
            // case 3
            [
                [
                    'ip' => '',
                    'auth' => 'abc12345'
                ],
                self::expectedResponse('error', null, 'invalid ip') // expected
            ]
        ];
    }

    /**
     * @dataProvider unauthorizedDataProvider
     */
    public function testCanAuthenticateRequestIsUnauthorized($input, array $expected): void {
        self::$config = Config::reloadInstance(__DIR__ . '/../env/sodium.env');
        $this->assertSame(
            $expected,
            Analyzer::authenticateRequest($input, self::$geoIP, null, [])
        );
    }

    public function unauthorizedDataProvider(): array {
        return [
            // case 0
            [
                [
                    'ip' => '',
                    'auth' => ''
                ],
                self::expectedResponse('error', null, 'unauthorized request') // expected
            ],
            // case 1
            [
                [
                    'ip' => 'abc',
                ],
                self::expectedResponse('error', null, 'unauthorized request') // expected
            ],
            // case 2
            [
                [
                    'ip' => '',
                    'auth' => 'wrong-password'
                ],
                self::expectedResponse('error', null, 'unauthorized request') // expected
            ]
        ];
    }

    /**
     * @dataProvider disableAuthDataProvider
     */
    public function testCanDisableAuthentication($input, array $expected): void {
        self::$config = Config::reloadInstance(__DIR__ . '/../env/auth-disabled.env');
        $this->assertSame(
            $expected,
            Analyzer::authenticateRequest($input, self::$geoIP, null, [])
        );
    }

    public function disableAuthDataProvider(): array {
        return [
            // case 0
            [
                [
                    'ip' => ''
                ],
                self::expectedResponse('error', null, 'invalid ip') // expected
            ],
            // case 1
            [
                [
                    'ip' => ''
                ],
                self::expectedResponse('error', null, 'invalid ip') // expected
            ]
        ];
    }

    public function testPingResponseIsValid(): void {
        $this->assertSame(
            [
                'status' => 'success',
                'data' => null,
                'message' => 'pong'
            ],
            Analyzer::authenticateRequest(['ping' => ''], self::$geoIP, null, [])
        );
    }

    /**
     * @dataProvider ipDataProvider
     */
    public function testCanAnalyzeIp($input, array $expected): void {
        $this->assertSame(
            $expected,
            Analyzer::authenticateRequest($input, self::$geoIP, null, [])
        );
    }

    public function ipDataProvider(): array {
        $ipData = [];

        foreach (static::$ipTestData as $ip => $data) {
            // case
            $ipData[] = [
                ['ip' => $ip],
                self::expectedResponse('success', $data, null) // expected
            ];
        }

        return $ipData;
    }

    /**
     * @dataProvider ipListDataProvider
     */
    public function testCanAnalyzeIpList($input, array $expected): void {
        $this->assertSame(
            $expected,
            Analyzer::authenticateRequest($input, self::$geoIP, null, [])
        );
    }

    public function ipListDataProvider(): array {
        return [
            [
                ['iplist' => array_keys(static::$ipTestData)],
                self::expectedResponse('success', static::$ipTestData, null) // expected
            ],
            [
                ['iplist' => array_reverse(array_keys(static::$ipTestData))],
                self::expectedResponse('success', array_reverse(static::$ipTestData), null) // expected
            ]
        ];
    }

    public function testCanThrowErrorWithNullGeoip(): void {
        $this->assertSame(
            self::expectedResponse('error', null, 'Call to a member function city() on null'), // expected
            Analyzer::authenticateRequest(self::ipDataProvider()[0][0], null, null, [])
        );
    }

    public static function tearDownAfterClass(): void {
        ini_set('error_log', self::$currentLogSetting);
    }
}
