# Laravel 4 Oracle (OCI8) DB Support

###Laravel-OCI8

[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-oci8/v/stable.png)](https://packagist.org/packages/yajra/laravel-oci8) [![Total Downloads](https://poser.pugx.org/yajra/laravel-oci8/downloads.png)](https://packagist.org/packages/yajra/laravel-oci8) [![Build Status](https://travis-ci.org/yajra/laravel-oci8.png)](https://travis-ci.org/yajra/laravel-oci8)

Laravel-OCI8 is an Oracle Database Driver package for [Laravel 4](http://laravel.com/). Laravel-OCI8 is an extension of [Illuminate/Database](https://github.com/illuminate/database) that uses [OCI8](http://php.net/oci8) extension to communicate with Oracle. Thanks to @taylorotwell.

The [yajra/laravel-pdo-via-oci8](https://github.com/yajra/laravel-pdo-via-oci8) package is a simple userspace driver for PDO that uses the tried and
tested [OCI8](http://php.net/oci8) functions instead of using the still experimental and not all that functionnal
[PDO_OCI](http://www.php.net/manual/en/ref.pdo-oci.php) library.

**Please report any bugs you may find.**

- [Installation](#installation)
- [Examples](#examples)
- [Support](#support)
- [Credits](#credits)

###Installation

Add `yajra/laravel-oci8` as a requirement to composer.json:

```json
{
    "require": {
        "yajra/laravel-oci8": "*"
    }
}
```
And then run `composer update`

Once Composer has installed or updated your packages you need to register the service provider. Open up `app/config/app.php` and find the `providers` key and add:

```php
'yajra\Oci8\Oci8ServiceProvider'
```

Finally you need to setup a valid database configuration using the driver "pdo-via-oci8". Configure your connection as usual with:

```php
'connection-name' => array(
    'host' => 'something',
    'port' => 'something',
    'username' => 'something',
    'password' => 'something',
    'charset' => 'something',
    'prefix' => 'something',
)
```
And run your laravel installation...

###Examples

Configuration (database.php)
```php
'default' => 'oracle',

'oracle' => array(
    'driver' => 'pdo-via-oci8',
    'host' => '127.0.0.1',
    'port' => '1521',
    'database' => 'xe', // Service ID
    'username' => 'schema',
    'password' => 'password',
    'charset' => '',
    'prefix' => '',
)
```

Basic query
```php
DB::select('select * from mylobs');
```

The lovely Oracle BLOB >.<

Querying a blob field will now load the value instead of the OCI-Lob object. See [yajra/laravel-pdo-via-oci8](https://github.com/yajra/laravel-pdo-via-oci8) for blob conversion details.
```php
$data = DB::table('mylobs')->get();
foreach ($data as $row) {
    echo $row->blobdata . '<br>';
}
```
Inserting a blob
```php
DB::transaction(function($conn){
    $pdo = $conn->getPdo();
    $sql = "INSERT INTO mylobs (id, blobdata)
        VALUES (mylobs_id_seq.nextval, EMPTY_BLOB())
        RETURNING blobdata INTO :blob";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':blob', $lob, PDO::PARAM_LOB);
    $stmt->execute();
    $lob->save('blob content');
});
```

Oracle Sequence Examples
```php
// creating a sequence
DB::createSequence('seq_name');

// deleting a sequence
DB::dropSequence('seq_name');

// get new id from sequence
$id = DB::nextSequenceValue('seq_name')

// get last inserted id
// Note: you must execute an insert statement using a sequence to be able to use this function
$id = DB::lastInsertId('seq_name');
// or
$id = DB::currentSequenceValue('seq_name');

```

Date Formatting (Note: Oracle's date format is set to ```YYYY-MM-DD HH24:MI:SS``` by default to match PHP's common date format)
```php
// set oracle session date format
DB::setDateFormat('MM/DD/YYYY');
```

###Support

Just like the built-in database drivers, you can use the connection method to access the oracle database(s) you setup in the database config file.

See [Laravel 4 Database Basic Docs](http://four.laravel.com/docs/database) for more information.

###Query Builder
You can use the Query Builder functionality exactly the same as you would with the default DB class in Laravel 4.
Every query on [Laravel 4 Database Query Builder Docs](http://four.laravel.com/docs/queries) has been tested to ensure that it works.

Offset & Limit
```php
$users = DB::table('users')->skip(10)->take(5)->get();
```

See [Laravel 4 Database Query Builder Docs](http://four.laravel.com/docs/queries) for more information.

###Eloquent

See [Laravel 4 Eloquent Docs](http://four.laravel.com/docs/eloquent) for more information.

###Schema (WIP)

See [Laravel 4 Schema Docs](http://four.laravel.com/docs/schema) for more information.

###Migrations (WIP)

See [Laravel 4 Migrations Docs](http://four.laravel.com/docs/migrations) for more information.

###License

Licensed under the [MIT License](http://cheeaun.mit-license.org/).

###Credits

- [crazycodr/laravel-oci8](https://github.com/crazycodr/laravel-oci8)
- [jfelder/Laravel-OracleDB](https://github.com/jfelder/Laravel-OracleDB)
