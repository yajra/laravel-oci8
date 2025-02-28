# Oracle DB driver for Laravel via OCI8

[![Continuous Integration](https://github.com/yajra/laravel-oci8/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/yajra/laravel-oci8/actions/workflows/continuous-integration.yml)
[![Static Analysis](https://github.com/yajra/laravel-oci8/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/yajra/laravel-oci8/actions/workflows/static-analysis.yml)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-oci8/d/total.svg)](https://packagist.org/packages/yajra/laravel-oci8)
[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-oci8/v/stable.svg)](https://packagist.org/packages/yajra/laravel-oci8)
[![License](https://poser.pugx.org/yajra/laravel-oci8/license.svg)](https://packagist.org/packages/yajra/laravel-oci8)

## Laravel-OCI8

Laravel-OCI8 is an Oracle Database Driver package for [Laravel](http://laravel.com/). Laravel-OCI8 is an extension of [Illuminate/Database](https://github.com/illuminate/database) that uses [OCI8](http://php.net/oci8) extension to communicate with Oracle. Thanks to @taylorotwell.

## Documentations

- You will find user-friendly and updated documentation here: [Laravel-OCI8 Docs](https://yajrabox.com/docs/laravel-oci8)
- All about oracle and php:[The Underground PHP and Oracle Manual](http://www.oracle.com/technetwork/database/database-technologies/php/201212-ug-php-oracle-1884760.pdf)

## Laravel Version Compatibility

 Laravel  | Package
:---------|:----------
 5.1.x    | 5.1.x
 5.2.x    | 5.2.x
 5.3.x    | 5.3.x
 5.4.x    | 5.4.x
 5.5.x    | 5.5.x
 5.6.x    | 5.6.x
 5.7.x    | 5.7.x
 5.8.x    | 5.8.x
 6.x      | 6.x
 7.x      | 7.x
 8.x      | 8.x
 9.x      | 9.x
 10.x     | 10.x
 11.x     | 11.x
 12.x     | 12.x

## Quick Installation

```bash
composer require yajra/laravel-oci8:^12
```

## Service Provider (Optional on Laravel 5.5+)

Once Composer has installed or updated your packages you need to register Laravel-OCI8. Open up `config/app.php` and find the providers key and add:

```php
Yajra\Oci8\Oci8ServiceProvider::class,
```

## Configuration (OPTIONAL)

Finally you can optionally publish a configuration file by running the following Artisan command.
If config file is not publish, the package will automatically use what is declared on your `.env` file database configuration.

```bash
php artisan vendor:publish --tag=oracle
```

This will copy the configuration file to `config/oracle.php`.

> Note: For [Laravel Lumen configuration](http://lumen.laravel.com/docs/configuration#configuration-files), make sure you have a `config/database.php` file on your project and append the configuration below:

```php
'oracle' => [
    'driver'         => 'oracle',
    'tns'            => env('DB_TNS', ''),
    'host'           => env('DB_HOST', ''),
    'port'           => env('DB_PORT', '1521'),
    'database'       => env('DB_DATABASE', ''),
    'service_name'   => env('DB_SERVICE_NAME', ''),
    'username'       => env('DB_USERNAME', ''),
    'password'       => env('DB_PASSWORD', ''),
    'charset'        => env('DB_CHARSET', 'AL32UTF8'),
    'prefix'         => env('DB_PREFIX', ''),
    'prefix_schema'  => env('DB_SCHEMA_PREFIX', ''),
    'edition'        => env('DB_EDITION', 'ora$base'),
    'server_version' => env('DB_SERVER_VERSION', '11g'),
    'load_balance'   => env('DB_LOAD_BALANCE', 'yes'),
    'dynamic'        => [],
    'max_name_len'   => env('ORA_MAX_NAME_LEN', 30),
],
```

> Then, you can set connection data in your `.env` files:

```ini
DB_CONNECTION=oracle
DB_HOST=oracle.host
DB_PORT=1521
DB_SERVICE_NAME=orcl
DB_DATABASE=xe
DB_USERNAME=hr
DB_PASSWORD=hr
```

> If you want to connect to a cluster containing multiple hosts, you can either set `tns` manually or set host as a comma-separated array and configure other fields as you wish:

```ini
DB_CONNECTION=oracle
DB_HOST=oracle1.host, oracle2.host
DB_PORT=1521
DB_SERVICE_NAME=orcl
DB_LOAD_BALANCE=no
DB_DATABASE=xe
DB_USERNAME=hr
DB_PASSWORD=hr
```

> If you need to connect with the service name instead of tns, you can use the configuration below:

```php
'oracle' => [
    'driver' => 'oracle',
    'host' => 'oracle.host',
    'port' => '1521',
    'database' => 'xe',
    'service_name' => 'sid_alias',
    'username' => 'hr',
    'password' => 'hr',
    'charset' => '',
    'prefix' => '',
]
```

In some cases you may wish to set the connection parameters dynamically in your app.  For instance, you may access more than one database, or your users may already have their own accounts on the Oracle database:

```php
'oracle' => [
    'driver' => 'oracle',
    'host' => 'oracle.host',
    'port' => '1521',
    'service_name' => 'sid_alias',
    'prefix' => 'schemaowner',
    'dynamic' => [App\Models\Oracle\Config::class, 'dynamicConfig'],
]
```

The callback function in your app must be static and accept a reference to the `$config[]` array (which will already be populated with values set in the config file):

```php
namespace App\Models\Oracle;

class Config {

    public static function dynamicConfig(&$config) {

        if (Illuminate\Support\Facades\Auth::check()) {
            $config['username'] = App\Oracle\Config::getOraUser();
            $config['password'] = App\Oracle\Config::getOraPass();
        }

    }
}
```

Then run your laravel installation...

## Oracle Max Name Length

By default, DB object name are limited to 30 characters. To increase the limit, you can set the `ORA_MAX_NAME_LEN=128` in your `.env` file.

Note: this config requires **Oracle 12c02 or higher**.

## [Laravel 5.2++] Oracle User Provider

When using oracle, we may encounter a problem on authentication because oracle queries are case sensitive by default.
By using this oracle user provider, we will now be able to avoid user issues when logging in and doing a forgot password failure because of case sensitive search.

To use, just update `auth.php` config and set the driver to `oracle`

```php
'providers' => [
    'users' => [
        'driver' => 'oracle',
        'model' => App\User::class,
    ],
]
```

## Credits

- [Arjay Angeles][link-author]
- [Jimmy Felder](https://github.com/jfelder/Laravel-OracleDB)
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[link-author]: https://github.com/yajra
[link-contributors]: ../../contributors
