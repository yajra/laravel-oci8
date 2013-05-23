# Laravel 4 Oracle Database Package

OracleDB [![Build Status](https://travis-ci.org/jfelder/Laravel-OracleDB.png?branch=master)](https://travis-ci.org/jfelder/Laravel-OracleDB)
========

OracleDB is an Oracle Database Driver package for [Laravel 4](http://laravel.com/). OracleDB is an extension of [Illuminate/Database](https://github.com/illuminate/database) that uses the  (Thanks to @taylorotwell)

**Please report any bugs you may find.**

- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Query Builder](#query-builder)
- [Eloquent](#eloquent)
- [Schema](#schema)
- [Migrations](#migrations)
- [License](#license)

Installation
============

Add `jfelder/oracledb` as a requirement to composer.json:

```json
{
    "require": {
        "jfelder/oracledb": "*"
    }
}
```
And then run `composer update`

Once Composer has installed or updated your packages you need to register OracleDB. Open up `app/config/app.php` and find the `providers` key and add:

```php
'Jfelder\OracleDB\OracleDBServiceProvider'
```

Finally you need to publish a configuration file by running the following Artisan command.

```terminal
$ php artisan config:publish jfelder/oracledb
```
This will copy the configuration file to app/config/packages/jfelder/oracledb/database.php


Basic Usage
===========
The configuration file for this package is located at 'app/config/packages/jfelder/oracledb/database.php'. 
In this file you may define all of your oracle database connections. If you want to make one of these connections the
default connection, enter the name you gave the connection into the "Default Database Connection Name" section in 'app/config/database.php'.

Once you have configured the OracleDB database connection(s), you may run queries using the 'DB' class as normal.

```php
$results = DB::select('select * from users where id = ?', array(1));
```

The above statement assumes you have set the default connection to be the oracle connection you setup in OracleDB's config file and will always return an 'array' of results.

```php
$results = DB::connection('oracle')->select('select * from users where id = ?', array(1));
```

Just like the built-in database drivers, you can use the connection method to access the oracle database(s) you setup in OracleDB's config file.

See [Laravel 4 Database Basic Docs](http://four.laravel.com/docs/database) for more information.

Query Builder
=============
You can use the Query Builder functionality exactly the same as you would with the default DB class in Laravel 4. 
Every query on [Laravel 4 Database Query Builder Docs](http://four.laravel.com/docs/queries) has been tested to ensure that it works.

Offset & Limit
```php
$users = DB::table('users')->skip(10)->take(5)->get();
```

See [Laravel 4 Database Query Builder Docs](http://four.laravel.com/docs/queries) for more information.

Eloquent
========

See [Laravel 4 Eloquent Docs](http://four.laravel.com/docs/eloquent) for more information.

Schema (WIP)
============

See [Laravel 4 Schema Docs](http://four.laravel.com/docs/schema) for more information.

Migrations (WIP)
================

See [Laravel 4 Migrations Docs](http://four.laravel.com/docs/migrations) for more information.

License
=======

Licensed under the [MIT License](http://cheeaun.mit-license.org/).

