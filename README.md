# Oracle DB driver for Laravel 4|5|6|7 via OCI8

## Laravel-OCI8

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

Laravel-OCI8 is an Oracle Database Driver package for [Laravel](http://laravel.com/). Laravel-OCI8 is an extension of [Illuminate/Database](https://github.com/illuminate/database) that uses [OCI8](http://php.net/oci8) extension to communicate with Oracle. Thanks to @taylorotwell.

## Documentations

- You will find user friendly and updated documentation here: [Laravel-OCI8 Docs](https://yajrabox.com/docs/laravel-oci8)
- All about oracle and php:[The Underground PHPand Oracle Manual](http://www.oracle.com/technetwork/database/database-technologies/php/201212-ug-php-oracle-1884760.pdf)

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
 6.x.x    | 6.x.x
 7.x.x    | 7.x.x

## Quick Installation

```bash
composer require yajra/laravel-oci8:^7
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
    'driver'        => 'oracle',
    'tns'           => env('DB_TNS', ''),
    'host'          => env('DB_HOST', ''),
    'port'          => env('DB_PORT', '1521'),
    'database'      => env('DB_DATABASE', ''),
    'username'      => env('DB_USERNAME', ''),
    'password'      => env('DB_PASSWORD', ''),
    'charset'       => env('DB_CHARSET', 'AL32UTF8'),
    'prefix'        => env('DB_PREFIX', ''),
    'prefix_schema' => env('DB_SCHEMA_PREFIX', ''),
    'edition'       => env('DB_EDITION', 'ora$base'),
],
```

> If you need to connect with the service name instead of tns, you can use the configuration below:

```
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

And run your laravel installation...

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

[ico-version]: https://img.shields.io/packagist/v/yajra/laravel-oci8.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/yajra/laravel-oci8/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/yajra/laravel-oci8.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/yajra/laravel-oci8.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/yajra/laravel-oci8.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/yajra/laravel-oci8
[link-travis]: https://travis-ci.org/yajra/laravel-oci8
[link-scrutinizer]: https://scrutinizer-ci.com/g/yajra/laravel-oci8/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/yajra/laravel-oci8
[link-downloads]: https://packagist.org/packages/yajra/laravel-oci8
[link-author]: https://github.com/yajra
[link-contributors]: ../../contributors
