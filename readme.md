# Laravel 4 Oracle (OCI8) DB Support

###Laravel-OCI8

[![Build Status](https://travis-ci.org/yajra/laravel-oci8.png?branch=master)](https://travis-ci.org/yajra/laravel-oci8)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-oci8/downloads.png)](https://packagist.org/packages/yajra/laravel-oci8)
[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-oci8/v/stable.png)](https://packagist.org/packages/yajra/laravel-oci8)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-oci8/v/unstable.svg)](https://packagist.org/packages/yajra/laravel-oci8)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yajra/laravel-oci8/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yajra/laravel-oci8/?branch=master)
[![License](https://poser.pugx.org/yajra/laravel-oci8/license.svg)](https://packagist.org/packages/yajra/laravel-oci8)

Laravel-OCI8 is an Oracle Database Driver package for [Laravel 4](http://laravel.com/). Laravel-OCI8 is an extension of [Illuminate/Database](https://github.com/illuminate/database) that uses [OCI8](http://php.net/oci8) extension to communicate with Oracle. Thanks to @taylorotwell.

The [yajra/laravel-pdo-via-oci8](https://github.com/yajra/laravel-pdo-via-oci8) package is a simple userspace driver for PDO that uses the tried and
tested [OCI8](http://php.net/oci8) functions instead of using the still experimental and not all that functional
[PDO_OCI](http://www.php.net/manual/en/ref.pdo-oci.php) library.

**Please report any bugs you may find.**

- [Requirements](#requirements)
- [Installation](#installation)
- [Starter Kit](#starter-kit)
- [Auto-Increment Support](#auto-increment-support)
- [Examples](#examples)
- [Support](#support)
- [Using the package outside of Laravel](#using-the-package-outside-of-laravel)
- [Credits](#credits)

###Requirements
- PHP >= 5.3
- PHP [OCI8](http://php.net/oci8) extension

###Installation

Add `yajra/laravel-oci8` as a requirement to composer.json:

```json
{
    "require": {
        "yajra/laravel-oci8": "1.*"
    }
}
```
And then run `composer update`

Once Composer has installed or updated your packages you need to register the service provider. Open up `app/config/app.php` and find the `providers` key and add:

```php
'yajra\Oci8\Oci8ServiceProvider'
```

Then setup a valid database configuration using the driver "oracle". Configure your connection as usual with:

```php
'oracle' => array(
    'driver' => 'oracle',
    'host' => 'oracle.host',
    'port' => '1521',
    'database' => 'xe',
    'username' => 'hr',
    'password' => 'hr',
    'charset' => '',
    'prefix' => '',
)
```
>If your database uses SERVICE NAME alias, use the config below:
```php
'oracle' => array(
    'driver' => 'oracle',
    'host' => 'oracle.host',
    'port' => '1521',
    'database' => 'xe',
    'service_name' => 'sid_alias',
    'username' => 'hr',
    'password' => 'hr',
    'charset' => '',
    'prefix' => '',
)
```


And run your laravel installation...

###Starter-Kit
To help you kickstart with Laravel, you may want to use the starter kit package below:
- [Laravel 4 Starter Kit](https://github.com/yajra/laravel4-starter-kit)
- [Laravel 4.1 Starter Kit](https://github.com/yajra/laravel-4.1-starter-kit)
- [Laravel 4.2 Starter Kit](https://github.com/yajra/laravel-admin-template)

Starter kit package above were forked from [brunogaspar/laravel4-starter-kit](https://github.com/brunogaspar/laravel4-starter-kit). No need to re-invent the wheel.

###Auto-Increment Support
To support auto-increment in Laravel-OCI8, you must meet the following requirements:
- Table must have a corresponding sequence with this format ```{$table}_{$column}_seq```
- Sequence next value are executed before the insert query. ```DB::nextSequenceValue("{$table}_{$column}_seq")```

***********

> **Note:** If you will use [Laravel Migration](http://laravel.com/docs/migrations) feature, the required sequence and a trigger will automatically be created. Please also note that **trigger, sequence and indexes name will be truncated to 30 chars** when created via [Schema Builder](http://laravel.com/docs/schema) hence there might be cases where the naming convention would not be followed. I suggest that you limit your object name not to exceed 20 chars as the builder added some naming convention on it like _seq, _trg, _unique, etc...

***********

```php
Schema::create('posts', function($table)
{
    $table->increments('id');
    $table->integer('user_id')->unsigned();
    $table->string('title');
    $table->string('slug');
    $table->text('content');
    $table->string('meta_title')->nullable();
    $table->string('meta_description')->nullable();
    $table->string('meta_keywords')->nullable();
    $table->timestamps();
});
```

This script will trigger Laravel-OCI8 to create the following DB objects
- posts (table)
- posts_id_seq (sequence)
- posts_id_trg (trigger)

> Check the starter kit provided to see how it works.

###Auto-Increment Start With Option
- You can now set the auto-increment starting value by setting the `start` attribute.
```php
Schema::create('posts', function($table)
{
    $table->increments('id')->start(10000);
    $table->string('title');
}
```

####Inserting Records Into A Table With An Auto-Incrementing ID

```php
  $id = DB::connection('oracle')->table('users')->insertGetId(
      array('email' => 'john@example.com', 'votes' => 0), 'userid'
  );
```

> **Note:** When using the insertGetId method, you can specify the auto-incrementing column name as the second parameter in insertGetId function. It will default to "id" if not specified.

###Eloquent
**Eloquent (multiple insert)**
```php
// Initialize empty array
$posts = array();

$common = array(
    'user_id' => 1,
);

// Blog post 1
$date = new DateTime;
$posts[] = array_merge($common, array(
    'title'      => 'Lorem ipsum dolor sit amet',
    'slug'       => 'lorem-ipsum-dolor-sit-amet',
    'content'    => 'lorem-ipsum-dolor-sit-amet',
    'created_at' => $date->modify('-10 day'),
    'updated_at' => $date->modify('-10 day'),
));

// Blog post 2
$date = new DateTime;
$posts[] = array_merge($common, array(
    'title'      => 'Vivendo suscipiantur vim te vix',
    'slug'       => 'vivendo-suscipiantur-vim-te-vix',
    'content'    => 'vivendo-suscipiantur-vim-te-vix',
    'created_at' => $date->modify('-4 day'),
    'updated_at' => $date->modify('-4 day'),
));

// Insert the blog posts
Post::insert($posts);
```

**Eloquent getting auto-increment inserted id**
```php
$post = new Post;
$post->user_id = 1;
$post->title = 'title';
$post->slug = 'slug';
$post->content = 'content';
$post->save();

// to get the inserted id
$id = $post->id;
```
###Oracle Blob
Querying a blob field will now load the value instead of the OCI-Lob object.
```php
$data = DB::table('mylobs')->get();
foreach ($data as $row) {
    echo $row->blobdata . '<br>';
}
```
**Inserting a blob via transaction**
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

**Inserting Records Into A Table With Blob And Auto-Incrementing ID**
```php
$id = DB::table('mylobs')->insertLob(
    array('name' => 'Insert Binary Test'),
    array('blobfield'=>'Lorem ipsum Minim occaecat in velit.')
    );
```
> **Note:** When using the insertLob method, you can specify the auto-incrementing column name as the third parameter in insertLob function. It will default to "id" if not specified.

**Updating Records With A Blob**
```php
$id = DB::table('mylobs')->whereId(1)->updateLob(
    array('name'=>'demo update blob'),
    array('blobfield'=>'blob content here')
    );
```
> **Note:** When using the insertLob method, you can specify the auto-incrementing column name as the third parameter in insertLob function. It will default to "id" if not specified.

###Updating Blob directly using OracleEloquent
On your model, just add `use yajra\Oci8\Eloquent\OracleEloquent as Eloquent;` and define the fields that are blob via `protected $binaries = [];`

*Example Model:*

```php
use yajra\Oci8\Eloquent\OracleEloquent as Eloquent;

class Post extends Eloquent {

    // define binary/blob fields
    protected $binaries = ['content'];

    // define the sequence name used for incrementing
    // default value would be {table}_{primaryKey}_seq if not set
    protected $sequence = null;

}
```

*Usage:*
```php
Route::post('save-post', function()
{
    $post = new Post;
    $post->title            = Input::get('title');
    $post->company_id       = Auth::user()->company->id;
    $post->slug             = Str::slug(Input::get('title'));
    // set binary field (content) value directly using model attribute
    $post->content          = Input::get('content');
    $post->meta_title       = Input::get('meta-title');
    $post->meta_description = Input::get('meta-description');
    $post->meta_keywords    = Input::get('meta-keywords');
    $post->save();
});
```

> Limitation: Saving multiple records with a blob field like `Post::insert($posts)` is not yet supported!

###Oracle Sequence
```php
// creating a sequence
DB::createSequence('seq_name');
// deleting a sequence
DB::dropSequence('seq_name');
// check if a sequence
DB::checkSequence('seq_name');
// get new id from sequence
$id = DB::nextSequenceValue('seq_name')
// get last inserted id
// Note: you must execute an insert statement using a sequence to be able to use this function
$id = DB::lastInsertId('seq_name');
// or
$id = DB::currentSequenceValue('seq_name');
```

###Date Formatting
> (Note: Oracle's `DATE` & `TIMESTAMP` format is set to `YYYY-MM-DD HH24:MI:SS` by default to match PHP's common date format)

```php
// set oracle session date format
DB::setDateFormat('MM/DD/YYYY');
```

###Support

Just like the built-in database drivers, you can use the connection method to access the oracle database(s) you setup in the database config file.

See [Laravel 4 Database Basic Docs](http://laravel.com/docs/database) for more information.


###Using the package outside of Laravel

- add `"yajra/laravel-oci8": "1.*"` on your composer then run `composer install`
- create `database.php` and add the code below
```php
require 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use yajra\Oci8\Connectors\OracleConnector;
use yajra\Oci8\Oci8Connection;

$capsule = new Capsule;

$manager = $capsule->getDatabaseManager();
$manager->extend('oracle', function($config)
{
    $connector = new OracleConnector();
    $connection = $connector->connect($config);
    $db = new Oci8Connection($connection, $config["database"], $config["prefix"]);
    // set oracle date format to match PHP's date
    $db->setDateFormat('YYYY-MM-DD HH24:MI:SS');
    return $db;
});

$capsule->addConnection(array(
    'driver'   => 'oracle',
    'host'     => 'oracle.host',
    'database' => 'xe',
    'username' => 'user',
    'password' => 'password',
    'prefix'   => '',
    'port'  => 1521
));

$capsule->bootEloquent();
```

- Now we can start working with database tables just like we would if we were using Laravel!
```php
include 'database.php';

// Create the User model
class User extends Illuminate\Database\Eloquent\Model {
    public $timestamps = false;
}

// Grab a user with an id of 1
$user = User::find(1);

echo $user->toJson(); die();
```


###License

Licensed under the [MIT License](http://cheeaun.mit-license.org/).

###Credits

- [crazycodr/laravel-oci8](https://github.com/crazycodr/laravel-oci8)
- [jfelder/Laravel-OracleDB](https://github.com/jfelder/Laravel-OracleDB)
