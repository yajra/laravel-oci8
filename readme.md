## Laravel 4 Oracle Database Package

###OracleDB (updated for 4.1)

[![Latest Stable Version](https://poser.pugx.org/jfelder/oracledb/v/stable.png)](https://packagist.org/packages/jfelder/oracledb) [![Total Downloads](https://poser.pugx.org/jfelder/oracledb/downloads.png)](https://packagist.org/packages/jfelder/oracledb) [![Build Status](https://travis-ci.org/jfelder/Laravel-OracleDB.png)](https://travis-ci.org/jfelder/Laravel-OracleDB)[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/jfelder/Laravel-OracleDB/badges/quality-score.png?s=fe9114220bc714cf188fdfa6a15be3a75cf86236)](https://scrutinizer-ci.com/g/jfelder/Laravel-OracleDB/)


OracleDB is an Oracle Database Driver package for [Laravel 4](http://laravel.com/). OracleDB is an extension of [Illuminate/Database](https://github.com/illuminate/database) that uses the [PDO_OCI] (http://www.php.net/manual/en/ref.pdo-oci.php) extension. Thanks to @taylorotwell.

**Please report any bugs you may find.**

- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Query Builder](#query-builder)
- [Eloquent](#eloquent)
- [Schema](#schema)
- [Migrations](#migrations)
- [License](#license)

###Installation

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


###Basic Usage
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

#### Inserting Records Into A Table With An Auto-Incrementing ID

```php
	$id = DB::connection('oracle')->table('users')->insertGetId(
		array('email' => 'john@example.com', 'votes' => 0), 'userid'
	);
```

> **Note:** When using the insertGetId method, you can specify the auto-incrementing column name as the second parameter in insertGetId function. It will default to "id" if not specified.

See [Laravel 4 Database Basic Docs](http://four.laravel.com/docs/database) for more information.

###License

Licensed under the [MIT License](http://cheeaun.mit-license.org/).

