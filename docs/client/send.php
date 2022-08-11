<?php

    /**
     * -------------------------------------------------------------------
     * default plaintext TCP protocol
     * -------------------------------------------------------------------
     */

    // email sending with send confirmation (plaintext TCP)
    function sendWithResponse(string $ip): bool {
        try {       
            $response = '';
            $fp = fsockopen("tcp://127.0.0.1", 3000, $errno, $errstr, 10);

            if (stream_set_timeout($fp, 10)) {
                if (!$fp) {
                    error_log("open TCP sock failed ({$errno}: {$errstr})");
                    return false;
                } else {
                    fwrite($fp, json_encode([
                        'ip' => $ip
                    ]));
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
                if (isset($result['status']) && $result['status'] === 'success') {
                    return $result['data'];
                } else {
                    return false;
                }
            } else {
                error_log("stream_set_timeout failed");
                return false;
            }
        } catch (\Throwable $e) {
            error_log("send exception: {$e->getMessage()})");
            return false;
        }
    }

    /**
     * -------------------------------------------------------------------
     * using SSL protocol
     * -------------------------------------------------------------------
     */

    // email sending with send confirmation (SSL)
    function sendWithResponseOverSSL(string $ip): bool {
        try {       
            $response = '';
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            $fp = stream_socket_client('ssl://127.0.0.1:3000', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

            if (stream_set_timeout($fp, 10)) {
                if (!$fp) {
                    error_log("open TCP sock failed ({$errno}: {$errstr})");
                    return false;
                } else {
                    fwrite($fp, json_encode([
                        'ip' => $ip
                    ]));
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
                if (isset($result['status']) && $result['status'] === 'success') {
                    return $result['data'];
                } else {
                    return false;
                }
            } else {
                error_log("stream_set_timeout failed");
                return false;
            }
        } catch (\Throwable $e) {
            error_log("send exception: {$e->getMessage()})");
            return false;
        }
    }

