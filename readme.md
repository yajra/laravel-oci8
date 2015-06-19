# Laravel 4|5 Oracle (OCI8) DB Support

###Laravel-OCI8

[![Build Status](https://img.shields.io/travis/yajra/laravel-oci8.svg)](https://travis-ci.org/yajra/laravel-oci8)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-oci8/downloads.png)](https://packagist.org/packages/yajra/laravel-oci8)
[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-oci8/v/stable.png)](https://packagist.org/packages/yajra/laravel-oci8)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-oci8/v/unstable.svg)](https://packagist.org/packages/yajra/laravel-oci8)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yajra/laravel-oci8/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yajra/laravel-oci8/?branch=master)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/yajra/laravel-oci8/blob/master/LICENSE)

Laravel-OCI8 is an Oracle Database Driver package for [Laravel](http://laravel.com/). Laravel-OCI8 is an extension of [Illuminate/Database](https://github.com/illuminate/database) that uses [OCI8](http://php.net/oci8) extension to communicate with Oracle. Thanks to @taylorotwell.
##Documentations
- You will find user friendly and updated documentation in the wiki here: [Laravel-OCI8 Wiki](https://github.com/yajra/laravel-oci8/wiki)
- You will find updated API documentation here: [Laravel-OCI8 API](http://yajra.github.io/laravel-oci8/api/)

###Quick Installation
`composer require yajra/laravel-oci8:~2.0`

###Service Provider
`'yajra\Oci8\Oci8ServiceProvider',`

###Configuration
Then setup a valid database configuration using the driver `oracle`. Configure your connection as usual with:
> Note: For [Laravel Lumen configuration](http://lumen.laravel.com/docs/configuration#configuration-files), make sure you have a `config/database.php` file on your project and append the configuration below:

```php
'oracle' => array(
    'driver' => 'oracle',
    'host' => 'oracle.host',
    'port' => '1521',
    'database' => 'xe',
    'username' => 'hr',
    'password' => 'hr',
    'charset' => 'AL32UTF8',
    'prefix' => '',
)
```

And run your laravel installation...

###License

Licensed under the [MIT License](https://github.com/yajra/laravel-oci8/blob/master/LICENSE).

###Buy me a beer
<a href='https://pledgie.com/campaigns/29516'><img alt='Click here to lend your support to: Laravel-OCI8 and make a donation at pledgie.com !' src='https://pledgie.com/campaigns/29516.png?skin_name=chrome' border='0' ></a>
