# Contributing Guide

Thank you for your interest in contributing. Be sure to read the following guidelines before submitting your contribution:

- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Issue Reporting Guidelines](#issue-reporting-guidelines)
- [Pull Request Guidelines](#pull-request-guidelines)
- [Development Setup](#development-setup)
- [Development Flow](#development-flow)
- [Project Structure](#project-structure)
- [Financial Contribution](#financial-contribution)

## Issue Reporting Guidelines

- Use [Github Issues](https://github.com/ecmchow/ip-analyzer/issues) to create new issues.

## Pull Request Guidelines

- All development should be done in dedicated branches. **Do not submit PRs against the `master` branch.**

- **DO NOT** checkin `dist` in the commits.

- Make sure Composer script `style-check`, `test-unit`, `test-e2e` and `test-build` passes. (see [development setup](#development-setup))

## Development Setup

PHP-CLI >= 7.4 (with OpenSSL and [sodium extension](https://www.php.net/manual/en/book.sodium.php) support) and [Composer](https://getcomposer.org/) **version >= 2.0** is are required for development. Linux/MacOS environment is recommended for smooth project development. It is possible to develop on Windows with WSL, please note that while some Composer scripts like unit tests and build script can run under Powershell, running the Mailer service or any e2e tests required a WSL environment to function properly.

After cloning the repo, run:
```console
composer install
```

This project relies on
- [Box](https://github.com/box-project/box) **version 3.16+** for building PHAR package.
- [PHP-CS-Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) **version 3.8+** for coding standards check.
- [PHPUnit](https://phpunit.readthedocs.io/en/9.5/) **version 9.5+** for unit and e2e testing.

Please download the PHAR package of above tools to `tools` folder with matching file name as specified in Composer script.

Coding standards of this project follows the [PSR-12: Extended Coding Style](https://www.php-fig.org/psr/psr-12/) standard with personal preferences as follow:
```json
{
  "@PSR12": true,
  "braces": {
    "position_after_functions_and_oop_constructs": "same"
  },
  "single_import_per_statement": false,
  "no_blank_lines_after_class_opening": false
}
```

In order to test SSL connection of service, you are required to generate SSL cert/key under `test` folder as `selfsigned.crt` and `selfsigned.key` file.
(E.g. generate via OpenSSL)
```console
openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout selfsigned.key -out selfsigned.crt
```

## Development Flow

A Maxmind MMDB file is required to run e2e/build test or in CI environment. You can host a mmdb file on Google Drive, and use its file ID as a secret to Drone CI/Github Actions.
```console
https://drive.google.com/file/d/{FILE_ID}/view?usp=sharing

wget -O GeoLite2-City.mmdb "https://drive.google.com/uc?export=download&confirm=yes&id={FILE_ID}"
```

After changing any core files, run:
- Coding standards/style check
- Update tests if needed
- Unit tests
- e2e tests (and Redis e2e tests if you have Redis installed)

If you think the project is ready for release, run:
- Coding standards/style check
- Update tests if needed
- Unit tests
- e2e tests (and Redis e2e tests if you have Redis installed)
- Build PHAR package
- Build tests (and Redis build tests if you have Redis installed)
- Pack ZIP with Composer archive
- Update documentation as needed

Coding standards/style check
```console
composer run style-check
```

Auto-fix coding standards/style
```console
composer run style-fix
```

Run unit tests
```console
composer run test-unit
```

Run e2e tests
```console
composer run test-e2e
```

Run e2e tests for Redis integration
```console
composer run test-e2e:redis
```

Build and output PHAR package
```console
composer run build
```

Run build tests
```console
composer run test-build
```

Run build tests for Redis integration
```console
composer run test-build:redis
```

Pack and output ZIP release
```console
composer run pack
```

To add/modify unit tests, simply create/edit test files for PHPUnit under `test/unit` folder

To add/modify e2e tests, create/edit PHPUnit test files and reference them in `test-service.sh` shell script under `test/e2e` folder


## Project Structure

    .
    ├── ...
    ├── blacklist                 # IPsum default directory
    ├── Core                      # Project src
    │   ├── schema                # JSON schema for env validation
    │   └── *.php
    ├── dist                      # Build/release output (PHAR/ZIP packages)
    ├── docs                      # Project documentation
    │   ├── api                   # API documentation
    │   ├── client                # Client code example
    │   └── env                   # Env documentation
    ├── mmdb                      # Local mmdb directory
    ├── test                      # Test script and files
    │   ├── e2e                   # e2e test scripts
    │   ├── env                   # env files for testing
    │   ├── unit                  # unit test scripts
    │   ├── ...
    │   ├── selfsigned.crt        # generated SSL signed cert
    │   └── selfsigned.key        # generated SSL private key
    ├── tools                     # Development tools (box, php-cs-fixer, phpunit)
    ├── vendor                    # Vendor files
    ├── ...
    ├── .env                      # Private env file
    ├── .env.example              # Example env file
    ├── .php-cs-fixer.dist.php    # PHP-CS-fixer config
    ├── ...
    ├── box.json                  # Build config for box
    ├── composer.json             # Composer config
    ├── phpunit.json              # PHPUnit test config
    ├── ...
    └── start-analyzer.php        # service entry file


## Financial Contribution

Financial contributions are welcomed via Patreon. Please visit the [Patreon Page](https://www.patreon.com/ecmchow) for more details.
