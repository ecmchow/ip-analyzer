<p align="center"><img src="docs/reader.svg?raw=true" width="128"></p>

<h3 align="center">IP Analyzer</h3>

<p align="center">
    Basic IP intelligence service
    <br />
    <a href="./docs/api/README.md"><strong>API docs »</strong></a>
    /
    <a href="./docs/env/README.md"><strong>env docs »</strong></a>
    <br />
    <br />
    <a href="https://github.com/ecmchow/ip-analyzer/releases">View Releases</a>
    ·
    <a href="https://github.com/ecmchow/ip-analyzer/issues">Report Bug</a>
    ·
    <a href="https://github.com/ecmchow/ip-analyzer/issues">Request Feature</a>
</p>

<p align="center">
  <a href="https://github.com/ecmchow/ip-analyzer/actions/"><img src="https://img.shields.io/github/workflow/status/ecmchow/ip-analyzer/CI?style=flat-square&logo=GitHub" alt="GitHub Actions"></a>
  <a href="https://github.com/ecmchow/ip-analyzer/releases"><img src="https://img.shields.io/github/downloads/ecmchow/ip-analyzer/total?style=flat-square&logo=GitHub" alt="GitHub downloads"></a>
  <a href="https://www.php.net/releases/index.php"><img src="https://img.shields.io/badge/PHP-%3E%3D7.4-%23868eb8?style=flat-square&logo=PHP" alt="minimum PHP version"></a>
  <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square" alt="License"></a>
  <a href="https://github.com/ecmchow/ip-analyzer/CODE_OF_CONDUCT.md"><img src="https://img.shields.io/badge/Contributor%20Covenant-2.1-4baaaa.svg?style=flat-square" alt="Contributor Covenant"></a>
</p>

<br/>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li><a href="#introduction">Introduction</a></li>
    <li><a href="#features">Features</a></li>
    <li><a href="#dependencies">Dependencies</a></li>
    <li><a href="#quickstart-with-docker">Quickstart with Docker</a></li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
        <li><a href="#redis-integration">Redis Integration</a></li>
        <li><a href="#ipsum-integration">IPsum Integration</a></li>
      </ul>
    </li>
    <li>
      <a href="#usage">Usage</a>
      <ul>
        <li><a href="#managing-service">Managing service</a></li>
        <li><a href="#service-api">Service API</a></li>
        <li><a href="#connecting-to-service">Connecting to service</a></li>
        <li><a href="#systemd-service">Systemd Service</a></li>
        <li><a href="#log-rotation">Log Rotation</a></li>
      </ul>
    </li>
    <li><a href="#changelog">Changelog</a></li>
    <li><a href="#contribution">Contribution</a></li>
    <li><a href="#author">Author</a></li>
    <li><a href="#license">License</a></li>
  </ol>
</details>

<br/>

## Introduction

IP Analyzer is a basic **IP address intelligence service** powered by [Workerman](https://github.com/walkor/workerman) and [Maxmind GeoLite2 database](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data?lang=en).

It is designed to be a standalone and portable microservice to provide IP address intelligence across multiple applications/services, eliminating the need to initialize GeoIP2 database reader inside applications or in every API requests. While it runs on PHP CLI, it can be integrated with any backend stack that supports internal JSON/TCP communication. It also include a optional threat level indication provided by [IPsum](https://github.com/stamparm/ipsum).

**Disclaimer:** IP Analyzer is intended to be an internal service to provide basic IP intelligence for your applications, you should not disclose data from IP Analyzer/MaxMind database to the public unless you have acquired commercial license from MaxMind. You **should NOT** rely solely on this service to get an accurate user location/origin or as a web application firewall (WAF)/Intrusion Prevention System (IPS).

<br/>

## Features

* IPv4/IPv6 geographic intelligence by [Maxmind GeoIP2](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data?lang=en)
* Auto-reload MMDB database with a scheduled Cron pattern
* Fallback MMDB if primary MMDB is not readable/available (Optional)
* IPv4 threat level intelligence by [IPsum](https://github.com/stamparm/ipsum) (Optional)
* Redis integration/caching (Optional)

<br/>

## Dependencies

* PHP-CLI >= 7.4
* [Composer](https://getcomposer.org/) >= 2.0
* [Workerman](https://github.com/walkor/workerman) >= 4.0
* [Maxmind GeoIP2 PHP](https://github.com/maxmind/GeoIP2-php) >= 2.12

## Quickstart with Docker

Copy the example Docker compose file:
* [docker-compose.yml](docker-compose.yml)

Before starting the service, make sure you have a valid account with [Maxmind GeoIP2](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data?lang=en). We will use the [maxmindinc/geoipupdate](https://hub.docker.com/r/maxmindinc/geoipupdate) image to automatically download and update MMDB database. Create an `.env` file for [maxmindinc/geoipupdate](https://hub.docker.com/r/maxmindinc/geoipupdate) docker:
```txt
GEOIPUPDATE_ACCOUNT_ID=<YOUR_ACCOUNT_ID>
GEOIPUPDATE_LICENSE_KEY=<YOUR_ACCOUNT_ID>
```

You may comment out the **ip_analyzer** service part first, use `docker compose up -d` to start **maxmindinc/geoipupdate** and download the MMDB database. To avoid confusing the env file for docker compose and the IP Analyzer service, copy/rename the [.env.example](.env.example) to `.env.service` and change the listening address to `0.0.0.0`:
```ini
ANALYZER_ADDR = "0.0.0.0"
...
REDIS_ADDR = "0.0.0.0" ; if you are using Redis
```

Pass the service env file through the Docker volumes
```txt
  ip_analyzer:
  ...
    volumes:
      - /usr/share/GeoIP:/usr/share/GeoIP # MMDB directory
      - /path/to/your/.env.service:/var/www/ip-analyzer/.env # service env file
```

Un-comment the **ip_analyzer** service part and run `docker compose up -d` again to start the IP Analyzer service. The service will be available at `tcp://localhost:3000/` by default.

To test the IP Analyzer service, simple run `echo '{"ip":"128.101.101.101"}' | nc localhost 3000`, you should see the following response:
```json
{"status":"success","data":{"code":"NA","continent":"North America","iso":"US","country":"United States","isEU":false,"city":"Minneapolis","postal":"55414","div":"Minnesota","divIso":"MN","accuracy":10,"lat":44.9764,"long":-93.224,"timezone":"America\/Chicago"},"message":null}
```


## Getting Started

### Prerequisites

PHP >= 7.4 is required, with optional Sodium extension/OpenSSL support. Linux environment is recommended for production deployment. You may run the service locally on your MacOS/Windows machine for development, Windows with WSL is required to run the service and development tests. Please also note that this is a PHP CLI application, which does not require a web server or process manager such as PHP-FPM to function.

The free version of Maxmind GeoIP2 database is named `GeoLite2` which you can download manually or through Maxmind API. To install and update the Maxmind GeoIP2 database automatically, you should request an API key with your [Maxmind](https://www.maxmind.com/en/account) account. After you have acquired an API license key, follow the below steps to configure GeoIP download/update on your server ([Maxmind instructions](https://dev.maxmind.com/geoip/updating-databases?lang=en) for reference)

The following steps are for **Ubuntu** server:
```console
sudo add-apt-repository ppa:maxmind/ppa
sudo apt update
sudo apt install geoipupdate
```

After you have installed the official Maxmind updater, you should found a default conf file at `/etc/GeoIP.conf`. Replace lines above the `OPTIONAL` line with your own account ID and license key.
```bash
# GeoIP.conf file for `geoipupdate` program, for versions >= 3.1.1.
# Used to update GeoIP databases from https://www.maxmind.com.
# For more information about this config file, visit the docs at
# https://dev.maxmind.com/geoip/updating-databases?lang=en.

# `AccountID` is from your MaxMind account.
AccountID <YOUR_ACCOUNT_ID>

# `LicenseKey` is from your MaxMind account
LicenseKey <YOUR_KEY>

# `EditionIDs` is from your MaxMind account.
EditionIDs GeoLite2-ASN GeoLite2-City GeoLite2-Country

# The remaining settings are OPTIONAL.
...
```

Set a Cron job to update automatically
```console
sudo crontab -e
```
For example:
```bash
# Run GeoIP database update at 02:15 every Sunday and Wednesday
15 2 * * 0,3 /usr/bin/geoipupdate
```

Run `geoipupdate` to download the database for the first time
```console
sudo geoipupdate
```

After finishing Maxmind database setup, you may now install `IP Analyzer` service.

### Installation

There are several ways to install IP Analyzer
* [PHAR package](https://github.com/ecmchow/ip-analyzer/releases) (recommended)
* [ZIP release](https://github.com/ecmchow/ip-analyzer/releases) (without vendor and development files)
* Clone the project

If you are using the PHAR package, you might need to ensure the package binary is executable:
```console
chmod +x /path/to/ip-analyzer.phar
```

If you are using the ZIP release or cloning the project, you will need to install vendor packages with [Composer](https://getcomposer.org/):
```console
composer install
```

After downloading, create your own *.env* file by copying the example file:
* [.env.example](.env.example) -> .env

Recommended file structure as follow:

    .
    ├── ...
    ├── ip-analyzer             # Service root folder
    │   ├── blacklist             # IPsum txt save directory (Optional)
    │   ├── mmdb                  # Fallback mmdb directory
    │   ├── ...
    │   ├── .env                  # env configuration
    │   └── ip-analyzer.phar    # service executable / entry PHP script
    └── ...

Make sure MMDB and fallback directory is readable/writeable by PHP with appropriate permission. For security reasons, you should ensure the env is only readable by the service. (e.g. service started by root on a linux server)
```console
chown root:root /path/to/.env
chmod 600 /path/to/.env
```

While IP Analyzer is intended to be an internal service inside a trusted network, you should ensure the IP Analyzer files is placed under a publicly inaccessible directory on your server. For additional security, you may provide a password hash and SSL cert/key to enable password authentication and SSL on TCP connection respectively.

For detailed environment configuration, please view the [env documentation](docs/env/README.md)

After setting up the environment variables, you may start the service on your server terminal.

If you are using PHAR package, navigate to the folder containing the PHAR package
```console
php ip-analyzer.phar start -d
```

If you install with ZIP release or by cloning the project, navigate to the project folder:
```console
php start-analyzer.php start -d
```

### Redis Integration

IP Analyzer supports [Redis](https://redis.io/) out-of-the-box (Tested with Redis >= 5.0). Make sure you have Redis extension enabled in your PHP config, and you may provide Redis connection details in the env.

IP Analyzer will store the following JSON-serialized data with a key prefix defined in env (REDIS_KEY_PREFIX):
* Service status (IP analyze success/failed count)
* Result caching (optional with `REDIS_CACHE_RESULT` in env)

### IPsum Integration

[IPsum](https://github.com/stamparm/ipsum) is a daily updated list of suspicious/malicious IPv4 addresses with a score indicating how many blacklists it's on. IP Analyzer supports parsing the [IPsum list](https://raw.githubusercontent.com/stamparm/ipsum/master/ipsum.txt) out-of-the-box and use the score to enrich IP analysis result as a threat level number.

To use IPsum list as a simple IP threat intelligence provider, you may run `composer run ipsum` to download and save the txt file to `./blacklist/ipsum.txt`. Enable IPsum integration in env (`IPSUM_ENABLE`) and specify a scheduled automatic download/reload of updated IPsum list with a Cron expression (`IPSUM_UPDATE_CRON`). You should also save the IPsum list on installation/first use and ensure IP Analyzer has read/write access to it.

<br/>

## Usage

### Managing service

Add *sudo* to the following command if the service started/managed by root. To automate and better manage your service on a linux server, please view the [Systemd Service](#systemd-service) section.

Replace `ip-analyzer.phar` with `start-analyzer.php` if you are not using PHAR package

Start, stop or restart service (-d to daemonize service, i.e. keep running service after you quit terminal)
```console
php ip-analyzer.phar start -d
php ip-analyzer.phar restart -d
php ip-analyzer.phar stop
```

Start, restart service with custom env path
```console
php ip-analyzer.phar start -d --env /path/to/env
php ip-analyzer.phar restart -d --env /path/to/env
```

Reloading service (*see notes*)
```console
php ip-analyzer.phar reload
```

Monitoring service
```console
php ip-analyzer.phar status
php ip-analyzer.phar connections
```

**Notes:** Use `reload` instead of `restart` for a smooth service reload. However only files loaded dynamically after `onWorkerStart` event will be reloaded. If you change anything that is declared before `onWorkerStart` such as service address/port or SSL connection settings, you must restart the service to apply changes.

### Service API

The service accepts TCP request with JSON encoded payload. All payload will pass through basic validation. You can view all available request and required schema in the [API documentation](docs/api/README.md).

This is a basic request for getting IP address info

```json
{
    "ip": "128.101.101.101"
}
```
Service response
```json
{
    "status": "success",
    "data": {
        "code": "NA",
        "continent": "North America",
        "iso": "US",
        "country": "United States",
        "isEU": false,
        "city": "Minneapolis",
        "postal": "55423",
        "div": "Minnesota",
        "divIso": "MN",
        "accuracy": 20,
        "lat": 44.8769,
        "long": -93.2535,
        "timezone": "America\/Chicago"
    },
    "message": null
}
```
Please see [Maxmind API docs](https://maxmind.github.io/GeoIP2-php/doc/v2.12.2/) for details on these returned data

If you have enabled the IPsum integration, a `threat` number will be included in analysis result. The greater the number, the higher chance it might be a credible threat.

```json
{
    "status": "success",
    "data": {
        ...
        "threat": 5
    },
    "message": null
}
```

### Connecting to service

You can use any client that can manage TCP connection. The following examples assumed your service is running on 127.0.0.1:3000

- [PHP Client](docs/client/send.php)
- [NodeJS Client](docs/client/send.js)
- [Shell script](docs/client/send.sh)

## Systemd Service

**(Linux system only)**: You can create a service script to let systemd manage and start IP Analyzer automatically on boot.

E.g. /etc/systemd/system/ip-analyzer.service
```ini
[Unit]
Description=IPAnalyzer Service
After=network.target
StartLimitBurst=5
StartLimitIntervalSec=20

[Service]
Type=forking
ExecStart=/usr/bin/php /path/to/ip-analyzer.phar start -d
ExecReload=/usr/bin/php /path/to/ip-analyzer.phar reload
ExecStop=/usr/bin/php /path/to/ip-analyzer.phar stop
Restart=always
RestartSec=5
PrivateTmp=true
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
```

You should reduce and adjust the RestartSec, StartLimitBurst, StartLimitIntervalSec value accordingly if the mail sending service is mission critical.

Reload the service files and starting the service.
```console
sudo systemctl daemon-reload
sudo systemctl start ip-analyzer
sudo systemctl status ip-analyzer
```

To enable or disable the service on system reboot
```console
sudo systemctl enable ip-analyzer
sudo systemctl disable ip-analyzer
```

To restart or reload the service
```console
sudo systemctl restart ip-analyzer
sudo systemctl reload ip-analyzer
```

### Log Rotation

**(Linux system only)**: Since Workerman generated log does not rotate automatically, it is a good idea to use [logrotate](https://linux.die.net/man/8/logrotate) to prevent it from eating up your server storage.

E.g. /etc/logrotate.d/ip-analyzer
```text
/path/to/ip-analyzer/ip-analyzer-workerman.log {
  rotate 14
  daily
  compress
  missingok
  notifempty
  create 0644 root root
}
```

<br/>

## Changelog

Detailed changes for each release are documented in the [release notes](https://github.com/ecmchow/ip-analyzer/releases).

<br/>

## Contribution

Please make sure to read the [Contributing Guide](CONTRIBUTING.md) before making a pull request.

<br/>

## Author

[Eric Chow](https://cmchow.com)

<br/>

## License

This project is licensed under the [MIT](./LICENSE) License

Copyright (c) 2022, Eric Chow
