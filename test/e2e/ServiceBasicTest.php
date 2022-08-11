<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ServiceBasicTest extends TestCase {

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

    public static function setUpBeforeClass(): void {
        // TODO
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
        $this->assertSame(
            $expected,
            self::connect($input)
        );
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
    public function testCanConnectAndAnalyzeIpList($input, array $expected): void {
        $this->assertSame(
            $expected,
            self::connect($input)
        );
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

    public function testStatusResponseIsValid(): void {
        $dataCount = count(self::ipDataProvider());

        $this->assertSame(
            [
                'status' => 'success',
                'data' => [
                    'analyzed' => $dataCount + $dataCount * count(self::ipListDataProvider()),
                    'failed' => 0
                ],
                'message' => null
            ],
            self::connect(['status' => ''])
        );
    }

    public static function tearDownAfterClass(): void {
        // TODO
    }
}
