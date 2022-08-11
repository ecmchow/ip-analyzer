<p align="center"><img src="../../docs/reader.svg?raw=true" width="128"></p>

<h3 align="center">IP Analyzer</h3>

<p align="center">
    Env documentation
    <br />
    <a href="../../README.md"><strong>Back to Home »</strong></a>
    <br />
</p>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li><a href="#introduction">Introduction</a></li>
    <li>
      <a href="#env-parameters">Env parameters</a>
      <ul>
        <li><a href="#basic-service-configuration">Basic service configuration</a></li>
        <li><a href="#service-authentication">Service authentication</a></li>
        <li><a href="#redis-settings">Redis settings</a></li>
        <li><a href="#mmdb-database-settings">MMDB database settings</a></li>
        <li><a href="#ipsum-settings">IPsum settings</a></li>
      </ul>
    </li>
  </ol>
</details>

<br/>

## Introduction

IP Analyzer service require an env file in INI format. An example env file is included:
* [.env.example](.env.example)

<br/>

## Env parameters

### Basic service configuration

| Parameter | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `ANALYZER_PROTO` | `string` | "tcp" | **Required**. Service listening protocol. (`tcp` or `ssl`)  |
| `ANALYZER_ADDR` | `string` | "127.0.0.1" | **Required**. Service listening address.  |
| `ANALYZER_PORT` | `int` | 3000 | **Required**. Service listening port number. |
| `ANALYZER_SSL_CERT` | `string` | "" | **Optional**. SSL certificate filepath. E.g. "/path/to/selfsigned.crt"  |
| `ANALYZER_SSL_KEY` | `string` | "" | **Optional**. SSL private key filepath. E.g. "/path/to/selfsigned.key"  |
| `ANALYZER_WORKERS` | `int` | 1 | **Required**. Number of spawned service workers. |
| `ANALYZER_MAX_MEMORY` | `int` | 64 (in MB) | **Optional**. Service will auto-restart if memory usage exceeded this value (in MB)  |
| `ANALYZER_MAX_REQUEST` | `int` | -1 | **Optional**. Service will auto-restart if number of processed request exceeded this value. -1 to disable limit  |
| `ANALYZER_RESTART_CRON` | `string` | "" | **Optional**. Service will auto-restart according to Cron pattern ("0 1 * * *" to auto-restart on every 01:00). empty to disable  |
| `ANALYZER_LOG` | `bool` | true | **Optional**. Enable service logging  |
| `ANALYZER_LOG_LEVEL` | `string` | "notice" | **Optional**. Service logging level. ("debug", "info", "notice", "warning" or "error") |
| `ANALYZER_LOG_OUTPUT` | `string` | "error_log" | **Optional**. Service logging output. "error_log" to use PHP default error_log settings or "syslog" to output log directly to system log  |

### Service authentication

| Parameter | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `ANALYZER_AUTH` | `bool` | false | **Required**. Enable password authentication on service API  |
| `ANALYZER_AUTH_HASH_METHOD` | `string` | "bcrypt" | **Optional**. Authentication password hash method ("bcrypt", "argon2i" or "sodium")  |
| `ANALYZER_AUTH_HASH` | `string` | "" | **Optional**. Authentication password hash  |

For compatibility, `bcrypt` should be supported on all PHP installation. Use `sodium` which use argon2id hash method if your PHP installation supports sodium extension or alternatively `argon2i` for better security.

To generate password hash, simply use PHP CLI with your selected method.

Sodium argon2id
```console
php -r "echo sodium_crypto_pwhash_str('yourPassword', SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);"
```

PHP argon2i
```console
php -r "echo password_hash('yourPassword', PASSWORD_ARGON2I);"
```

PHP bcrypt (use cost >= 10 as recommended by [OWASP](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html))
```console
php -r "echo password_hash('yourPassword', PASSWORD_BCRYPT, ['cost' => 11]);"
```

### Redis settings

| Parameter | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `REDIS_ENABLE` | `bool` | false | **Required**. Enable Redis integration  |
| `REDIS_PROTO` | `string` | "tcp" | **Optional**. Redis listening protocol. (`tcp` or `tls`)  |
| `REDIS_ADDR` | `string` | "127.0.0.1" | **Optional**. Redis listening address.  |
| `REDIS_PORT` | `int` | 6379 | **Optional**. Redis listening port number. |
| `REDIS_KEY_PREFIX` | `string` | "IP_ANALYZER:" | **Optional**. Redis key prefix.  |
| `REDIS_TIMEOUT` | `int` | 0 (in secs) | **Optional**. Redis connection timeout. 0 for unlimited |
| `REDIS_RETRY_INTERVAL` | `int` | 100 (in ms) | **Optional**. Redis retry interval. |
| `REDIS_READ_TIMEOUT` | `int` | 10 (in secs) | **Optional**. Redis read timeout. 0 for unlimited |
| `REDIS_CACHE_RESULT` | `bool` | true | **Optional**. Cache IP analysis result in Redis  |
| `REDIS_CACHE_EXPIRE_MODE` | `string` | "ttl" | **Optional**. How cache will expire ("ttl" or "maxitem", see below notes) |
| `REDIS_CACHE_EXPIRE_TTL` | `int` | 3600 (in secs) | **Optional**. SETEX expire TTL (in secs, min. 1 sec)  |
| `REDIS_CACHE_RESET_TTL_ON_GET` | `bool` | true | **Optional**. Reset cache expire TTL on access  |
| `REDIS_CACHE_MAX_ITEM` | `int` | 100 | **Optional**. Max number of most recent items in Redis List |
| `REDIS_RESET_STATS_ON_START` | `bool` | true | **Optional**. Reset all service status on worker start  |
| `REDIS_USER` | `string` | "" | **Optional**. Redis ACL user name |
| `REDIS_PASSWORD` | `string` | "" | **Optional**. Redis ACL user password |

You can choose how the cached result will expire. "ttl" will use Redis's SETEX with `REDIS_CACHE_EXPIRE_TTL`, while "maxitem" will use a Redis List for indexing and delete items when it exceeds `REDIS_CACHE_MAX_ITEM` for limiting max memory consumption

### MMDB database settings

| Parameter | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `MMDB_DIR` | `string` | "/usr/share/GeoIP/GeoLite2-City.mmdb" | **Required**. Primary Maxmind MMDB database location. Absolute path or relative path (relative to start-analyzer.php or PHAR file directory)   |
| `MMDB_FALLBACK` | `string` | "" | **Required**. Fallback Maxmind MMDB database location which should be stored under service/project folder. Absolute path or relative path (relative to start-analyzer.php or PHAR file directory), pass empty string to disable fallback  |
| `MMDB_RELOAD_CRON` | `string` | "" | **Required**. Cron pattern string for scheduling auto MMDB reload (e.g. "30 2 * * 0,3"). Pass empty string to disable auto-reload |

### IPsum settings

| Parameter | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `IPSUM_ENABLE` | `bool` | false | **Required**. Enable IPsum integration (enabling this will increase memory usage)  |
| `IPSUM_URL` | `string` | "" | **Optional**. IPsum list txt file download URL (e.g. https://raw.githubusercontent.com/stamparm/ipsum/master/ipsum.txt) |
| `IPSUM_DIR` | `string` | "blacklist/ipsum.txt" | **Optional**. IPsum list file location. Absolute path or relative path (relative to start-analyzer.php or PHAR file directory) |
| `IPSUM_MAX_LINES` | `int` | 100000 | **Optional**. Max. number of lines to read/parse |
| `IPSUM_MIN_LEVEL` | `int` | 2 | **Optional**. Min. number of score/threat level of IP address to parse. (>= 2 is recommended for lowering memory usage, ~10MB if including all levels) |
| `IPSUM_UPDATE_CRON` | `string` | "15 1 * * *" | **Optional**. Cron pattern string for scheduling auto IPsum list update (IPsum usually updated at 01:00 GMT, you might want to adjust that to your timezone). Pass empty string to disable auto-update |
