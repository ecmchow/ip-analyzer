<p align="center"><img src="../../docs/reader.svg?raw=true" width="128"></p>

<h3 align="center">IP Analyzer</h3>

<p align="center">
    API documentation
    <br />
    <a href="../../README.md"><strong>Back to Home »</strong></a>
    <br />
</p>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li><a href="#introduction">Introduction</a></li>
    <li><a href="#authentication">Authentication</a></li>
    <li><a href="#service-status-api">Service Status</a></li>
    <li><a href="#ip-info-api">IP info API</a></li>
  </ol>
</details>

<br/>

## Introduction

IP Analyzer accepts TCP request with JSON encoded payload. All payload will pass through basic validation.

<br/>

## Authentication

If you have enabled the optional API password authentication in env, you should append the password with `auth` as key in the payload.

```json
{
    "ip": "1.1.1.1",
    "auth": "password"
}
```

## Service Status API

| Name | Type | Description |
| :--- | :--- | :--- |
| `ping` | `any` | Ping service  |

```json
{
    "ping": null
}
```

Success Response
```json
{
   "status": "success",
   "data": null,
   "message": "pong"
}
```

| Name | Type | Description |
| :--- | :--- | :--- |
| `status` | `any` | Get service status |

Status API is only available when any of the following conditions are met:
* IP Analyzer is running in single worker mode
* Redis integration is enabled

```json
{
    "status": null
}
```

Success Response
```json
{
   "status": "success",
   "data": {
       "analyzed": 10,
       "failed": 1
   },
   "message": null
}
```

Failed Response
```json
{
   "status": "error",
   "data": null,
   "message": "Redis store or running in single worker is required"
}
```

<br/>

## IP info API

### Get info on a single IP address

| Name | Type | Description |
| :--- | :--- | :--- |
| `ip` | `string` | an IPv4/IPv6 string  |

Get geo info on an IP address
```json
{
    "ip": "128.101.101.101"
}
```

Success Response
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

Failed Response
```json
{
   "status": "error",
   "data": null,
   "message": "invalid ip"
}
```

### Get info on a list of IP addresses

| Name | Type | Description |
| :--- | :--- | :--- |
| `iplist` | `array` | an array of IPv4/IPv6 string(s) (max. 100 items) |

Get geo info on an IP address
```json
{
    "iplist": [
        "128.101.101.101",
        "128.101.101.102",
        "128.101.101.103"
    ]
}
```

Success Response
```json
{
    "status": "success",
    "data": {
        "128.101.101.101": {
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
        "128.101.101.102": {
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
        "128.101.101.103": {
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
        }
    },
    "message": null
}
```

Please see [Maxmind API docs](https://maxmind.github.io/GeoIP2-php/doc/v2.12.2/) for details on these returned data
