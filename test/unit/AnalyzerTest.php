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

    private static function expectedResponse(string $status, $data, $message): array {
        return [
            'status' => $status,
            'data' => $data,
            'message' => $message
        ];
    }

    private static function getTestData() {
        return json_decode(file_get_contents(__DIR__ . '/../ip-test-data.json'), true);
    }

    private function assertIpDetails($data) {
        $this->assertArrayHasKey('country', $data);
        $this->assertArrayHasKey('isEU', $data);
        $this->assertArrayHasKey('city', $data);
        $this->assertArrayHasKey('postal', $data);
        $this->assertArrayHasKey('div', $data);
        $this->assertArrayHasKey('divIso', $data);
        $this->assertArrayHasKey('accuracy', $data);
        $this->assertArrayHasKey('lat', $data);
        $this->assertArrayHasKey('long', $data);
        $this->assertArrayHasKey('timezone', $data);

        $this->assertStringMatchesFormat('%s', $data['country']);
        $this->assertIsBool($data['isEU']);
        $this->assertStringMatchesFormat('%s', $data['city']);
        $this->assertStringMatchesFormat('%S', $data['postal']);
        $this->assertStringMatchesFormat('%s', $data['div']);
        $this->assertStringMatchesFormat('%s', $data['divIso']);
        $this->assertIsNumeric($data['accuracy']);
        $this->assertIsFloat($data['lat']);
        $this->assertIsFloat($data['long']);
        $this->assertMatchesRegularExpression('/[a-zA-Z]+\/[a-zA-Z]+/', $data['timezone']);
    }

    private function assertIpData($input, $expected) {
        $this->assertArrayHasKey('status', $input);
        $this->assertArrayHasKey('data', $input);
        $this->assertArrayHasKey('message', $input);

        $this->assertEquals($expected['status'], $input['status']);
        $this->assertIsArray($input['data']);
        $this->assertEquals($expected['message'], $input['message']);

        if (array_key_exists('country', $expected['data'])) {
            $data = $input['data'];
            $this->assertIpDetails($data);
        } else {
            foreach ($input['data'] as $ip => $data) {
                $this->assertIpDetails($data);
            }
        }
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
        $this->assertIpData(Analyzer::authenticateRequest($input, self::$geoIP, null, []), $expected);
    }

    public function ipDataProvider(): array {
        $ipData = [];

        foreach (self::getTestData() as $ip => $data) {
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
        $this->assertIpData(Analyzer::authenticateRequest($input, self::$geoIP, null, []), $expected);
    }

    public function ipListDataProvider(): array {
        $ipTestData = self::getTestData();

        return [
            [
                ['iplist' => array_keys($ipTestData)],
                self::expectedResponse('success', $ipTestData, null) // expected
            ],
            [
                ['iplist' => array_reverse(array_keys($ipTestData))],
                self::expectedResponse('success', array_reverse($ipTestData), null) // expected
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
