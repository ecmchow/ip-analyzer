<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Core\Config;
use Core\Validator;

final class ConfigTest extends TestCase {

    public function testCanThrowEnvDoesNotExistException(): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('env file does not exist');
        Config::createInstance(__DIR__ . '/../env/notexist.env');
    }

    public function testInstanceCanBeCreatedFromValidEnv(): void {
        $this->assertInstanceOf(
            Config::class,
            Config::createInstance(__DIR__ . '/../env/sodium.env')
        );
    }

    public function testInstanceCanBeReloaded(): void {
        $this->assertInstanceOf(
            Config::class,
            Config::reloadInstance(__DIR__ . '/../env/sodium.env')
        );
    }
        
    public function testPrivateDataCannotBeReadFromUnauthorizedClass(): void {
        $this->assertSame(
            '',
            Config::getAuthHash()
        );
    }
}
