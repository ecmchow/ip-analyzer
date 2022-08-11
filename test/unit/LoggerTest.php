<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Core\Logger;

final class LoggerTest extends TestCase {

    public function testCanOutputErrorLogs(): void {
        $currentLogSetting = ini_get('error_log');
        
        $capture = tmpfile();
        ini_set('error_log', stream_get_meta_data($capture)['uri']);

        $this->expectOutputRegex('/DEBUG: logger test/');
    
        Logger::log('debug', "logger test");
        var_dump(stream_get_contents($capture));

        $this->expectOutputRegex('/INFO: logger test/');
    
        Logger::log('info', "logger test");
        var_dump(stream_get_contents($capture));

        $this->expectOutputRegex('/NOTICE: logger test/');
    
        Logger::log('notice', "logger test");
        var_dump(stream_get_contents($capture));

        $this->expectOutputRegex('/WARN: logger test/');
    
        Logger::log('warning', "logger test");
        var_dump(stream_get_contents($capture));

        $this->expectOutputRegex('/ERROR: logger test/');
    
        Logger::log('error', "logger test");
        var_dump(stream_get_contents($capture));

        ini_set('error_log', $currentLogSetting);
    }
}
