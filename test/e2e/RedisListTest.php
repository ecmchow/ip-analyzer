<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ServiceBasicTest extends TestCase {

    private static $redis = null;

    private static function clearTestData() {
        $it = null;
        while ($keys = static::$redis->scan($it)) {
            foreach ($keys as $key) {
                static::$redis->del(substr($key, 17));
            }
        }
    }

    private static function scanTestData() {
        $keyList = [];
        $it = null;
        while ($keys = static::$redis->scan($it, 'IP_ANALYZER_TEST:result:*')) {
            foreach ($keys as $key) {
                $keyList[] = substr($key, 24);
            }
        }
        sort($keyList);
        return $keyList;
    }

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
        if (array_key_exists('status', $expected)) {
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
        } else {
            $this->assertIpDetails($input);
        }
    }

    public static function setUpBeforeClass(): void {
        static::$redis = new \Redis();
        static::$redis->connect('tcp://127.0.0.1', 6379, 0, null, 100, 10);
        static::$redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
        static::$redis->setOption(\Redis::OPT_PREFIX, 'IP_ANALYZER_TEST:');
        static::$redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        static::$redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_NOPREFIX);
        self::clearTestData();
    }

    private static function connect($payload): array {
        try {
            $response = '';

            $context = stream_context_create();
            $fp = stream_socket_client('tcp://127.0.0.1:3333', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

            if (stream_set_timeout($fp, 3)) {
                if (!$fp) {
                    throw new \Exception("open TCP sock failed ({$errno}: {$errstr})");
                } else {
                    fwrite($fp, json_encode($payload));
                    while (!feof($fp)) {
                        $buffer = fread($fp, 4096);
                        $response .= $buffer;
                        if (strlen($buffer) < 4096) {
                            break;
                        }
                    }
                    fclose($fp);
                }
                $result = json_decode($response, true);
                return $result;
            } else {
                throw new \Exception("stream_set_timeout failed");
            }
        } catch (\Throwable $e) {
            throw new \Exception("send exception: {$e->getMessage()})");
        }
    }
        
    public function testCanConnectToServiceAndReceiveResponse(): void {
        $this->assertSame(
            self::expectedResponse('error', null, 'payload cannot be empty'), // expected
            self::connect(null)
        );
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testCanConnectWithInvalidData($input, array $expected): void {
        $this->assertSame(
            $expected,
            self::connect($input)
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

    public function testPingResponseIsValid(): void {
        $this->assertSame(
            [
                'status' => 'success',
                'data' => null,
                'message' => 'pong'
            ],
            self::connect(['ping' => ''])
        );
    }

    /**
     * @dataProvider ipDataProvider
     */
    public function testCanConnectAndAnalyzeIp($input, array $expected): void {
        $this->assertIpData(self::connect($input), $expected);
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
     * @dataProvider cacheDataProvider
     */
    public function testCanCacheResult($input, array $expected): void {
        $this->assertIpData(static::$redis->get("result:{$input}"), $expected);
    }

    public function cacheDataProvider(): array {
        $ipData = [];

        foreach (self::getTestData() as $ip => $data) {
            // case
            $ipData[] = [
                $ip,
                $data // expected
            ];
        }

        return $ipData;
    }

    /**
     * @dataProvider ipListDataProvider
     */
    public function testCanConnectAndAnalyzeIpList($input, array $expected): void {
        $this->assertIpData(self::connect($input), $expected);
    }

    public function ipListDataProvider(): array {
        $testData = self::getTestData();
        return [
            [
                ['iplist' => array_keys($testData)],
                self::expectedResponse('success', $testData, null) // expected
            ]
        ];
    }

    public function testCacheItemsIsLimited(): void {
        $data = self::getTestData();
        $dataKeys = array_keys($data);
        $sortedKeys = array_keys($data);
        sort($sortedKeys);

        $keyList = self::scanTestData();

        $this->assertSame(
            array_reverse($dataKeys),
            static::$redis->lRange("index", 0, -1)
        );

        $this->assertSame(
            $sortedKeys,
            $keyList
        );

        self::connect(['ip' => '128.101.101.102']);
        self::connect(['ip' => '128.101.101.103']);

        $sortedKeys = \array_diff($sortedKeys, [$dataKeys[0], $dataKeys[1]]);
        \array_shift($dataKeys);
        \array_shift($dataKeys);

        $dataKeys[] = '128.101.101.102';
        $dataKeys[] = '128.101.101.103';

        $sortedKeys[] = '128.101.101.102';
        $sortedKeys[] = '128.101.101.103';
        
        sort($sortedKeys);

        $keyList = self::scanTestData();

        $this->assertSame(
            array_reverse($dataKeys),
            static::$redis->lRange("index", 0, -1)
        );

        $this->assertSame(
            $sortedKeys,
            $keyList
        );
    }

    public function testStatusResponseIsValid(): void {
        $dataCount = count(self::ipDataProvider());

        $this->assertSame(
            [
                'status' => 'success',
                'data' => [
                    'analyzed' => $dataCount + $dataCount * count(self::ipListDataProvider()) + 2,
                    'failed' => 0
                ],
                'message' => null
            ],
            self::connect(['status' => ''])
        );
    }

    public static function tearDownAfterClass(): void {
        self::clearTestData();
    }
}
