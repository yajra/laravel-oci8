# Laravel 4 Oracle (OCI8) Database Support

Laravel-OCI8
============

Laravel-OCI8 is an Oracle Database Driver package for [Laravel 4](http://laravel.com/). Laravel-OCI8 is an extension of [Illuminate/Database](https://github.com/illuminate/database) that uses CrazyCodr/pdo-via-oci8 to communicate with Oracle.

The PDO-via-OCI8 package is a simple userspace driver for PDO that uses the tried and
tested OCI8 functions instead of using the still experimental and not all that functionnal
PDO_OCI library.

Also note that this package is highly dependant on jfelder/oracledb for all the grammar portion. No need to reinvent the wheel...

- [Installation](#installation)
- [Support](#support)

Installation
============

Add `crazycodr/laravel-oci8` as a requirement to composer.json:

```json
{
    "require": {
        "crazycodr/laravel-oci8": "*"
    }
}
```
And then run `composer update`

Once Composer has installed or updated your packages you need to register the service provider. Open up `app/config/app.php` and find the `providers` key and add:

```php
'CrazyCodr\Oci8\Oci8ServiceProvider'
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

Support
=======
Just like the built-in database drivers, you can use the connection method to access the oracle database(s) you setup in the database config file.

See [Laravel 4 Database Basic Docs](http://four.laravel.com/docs/database) for more information.

Also compatible with:

- Query Builder
- Eloquent
- Schema (WIP)
- Migrations (WIP)
