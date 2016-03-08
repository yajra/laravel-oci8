# Laravel 4|5 Oracle (OCI8) DB Support

###Laravel-OCI8

[![Build Status](https://img.shields.io/travis/yajra/laravel-oci8.svg)](https://travis-ci.org/yajra/laravel-oci8)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-oci8/downloads.png)](https://packagist.org/packages/yajra/laravel-oci8)
[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-oci8/v/stable.png)](https://packagist.org/packages/yajra/laravel-oci8)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-oci8/v/unstable.svg)](https://packagist.org/packages/yajra/laravel-oci8)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yajra/laravel-oci8/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yajra/laravel-oci8/?branch=master)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/yajra/laravel-oci8/blob/master/LICENSE)

Laravel-OCI8 is an Oracle Database Driver package for [Laravel](http://laravel.com/). Laravel-OCI8 is an extension of [Illuminate/Database](https://github.com/illuminate/database) that uses [OCI8](http://php.net/oci8) extension to communicate with Oracle. Thanks to @taylorotwell.
###Documentations
- You will find user friendly and updated documentation in the wiki here: [Laravel-OCI8 Wiki](https://github.com/yajra/laravel-oci8/wiki)
- You will find updated API documentation here: [Laravel-OCI8 API](http://yajra.github.io/laravel-oci8/api/)

###Quick Installation [Laravel 5.2]
```
$ composer require yajra/laravel-oci8:"5.2.*"
```

###Quick Installation [Laravel 5.1]
```
$ composer require yajra/laravel-oci8:"5.1.*"
```

###Laravel 4.2 & 5.0 Users
Please use [2.4](https://github.com/yajra/laravel-oci8/tree/2.4) branch.

###Service Provider
Once Composer has installed or updated your packages you need to register Laravel-OCI8. Open up `config/app.php` and find the providers key and add:
```php
Yajra\Oci8\Oci8ServiceProvider::class,
```
> Important: Since v4.0, the package will now use `Yajra\Oci8` (capital Y) namespace from `yajra\Oci8` to follow the name standard for vendor name.

###Configuration (OPTIONAL)
Finally you can optionally publish a configuration file by running the following Artisan command.
If config file is not publish, the package will automatically use what is declared on your `.env` file database configuartion.

```
$ php artisan vendor:publish --tag=oracle
```

This will copy the configuration file to `config/oracle.php`.

> Note: For [Laravel Lumen configuration](http://lumen.laravel.com/docs/configuration#configuration-files), make sure you have a `config/database.php` file on your project and append the configuration below:

```php
'oracle' => array(
    'driver'   => 'oracle',
    'tns'      => env('DB_TNS', ''),
    'host'     => env('DB_HOST', ''),
    'port'     => env('DB_PORT', '1521'),
    'database' => env('DB_DATABASE', ''),
    'username' => env('DB_USERNAME', ''),
    'password' => env('DB_PASSWORD', ''),
    'charset'  => env('DB_CHARSET', 'AL32UTF8'),
    'prefix'   => env('DB_PREFIX', ''),
)
```

And run your laravel installation...

###License

Licensed under the [MIT License](https://github.com/yajra/laravel-oci8/blob/master/LICENSE).

###Buy me a beer
<a href='https://pledgie.com/campaigns/29516'><img alt='Click here to lend your support to: Laravel-OCI8 and make a donation at pledgie.com !' src='https://pledgie.com/campaigns/29516.png?skin_name=chrome' border='0' ></a>
