# Oracle DB driver for Laravel via OCI8

[![Continuous Integration](https://github.com/yajra/laravel-oci8/actions/workflows/continuous-integration.yml/badge.svg?branch=master)](https://github.com/yajra/laravel-oci8/actions/workflows/continuous-integration.yml?query=branch%3Amaster)
[![Static Analysis](https://github.com/yajra/laravel-oci8/actions/workflows/static-analysis.yml/badge.svg?branch=master)](https://github.com/yajra/laravel-oci8/actions/workflows/static-analysis.yml?query=branch%3Amaster)
[![Coverage](https://raw.githubusercontent.com/yajra/laravel-oci8/coverage-report/badges/coverage.svg)](https://github.com/yajra/laravel-oci8/actions/workflows/continuous-integration.yml?query=branch%3Amaster)
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
 13.x     | 13.x

## Quick Installation

```bash
composer require yajra/laravel-oci8:^13
```

## Larastan / PHPStan

This package includes an optional PHPStan/Larastan extension for OCI8-specific `DB` methods.
Include it in your `phpstan.neon` if you want those methods recognized during static analysis.

```neon
includes:
    - vendor/yajra/laravel-oci8/extension.neon
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
    'connect_timeout' => env('DB_CONNECT_TIMEOUT', ''),
    'retry_count'    => env('DB_RETRY_COUNT', '3'), // 12c and above only
    'retry_delay'    => env('DB_RETRY_DELAY', '1'), // 12c and above only
    'transport_connect_timeout' => env('DB_TRANSPORT_CONNECT_TIMEOUT', '60'), // 12c and above only
    'expire_time'    => env('DB_EXPIRE_TIME', '0'), // 19c and above only
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
DB_CONNECT_TIMEOUT=10
DB_RETRY_COUNT=3
DB_RETRY_DELAY=1
DB_TRANSPORT_CONNECT_TIMEOUT=60
DB_EXPIRE_TIME=0
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

## Oracle versions

To set this version use `DB_SERVER_VERSION` env variable or change `server_version` variable in the oracle.php config.

### 11g
This is the baseline, if no version is set, this version is assumed.
### 12c
- Use fetch/offset where possible instead of rownumber.
- Use identity instead of seqvence/trigger for ids when using laravel's scheme builder. ([#944](https://github.com/yajra/laravel-oci8/pull/944))
- JoinLateral support ([#989](https://github.com/yajra/laravel-oci8/pull/989))
### 12cR2
- In whereLike use `binary ci` for case insensitivity. ([#945](https://github.com/yajra/laravel-oci8/pull/945))
### 19c
- JSON path updates with query builder `update(['options->path' => $value])`. ([#1007](https://github.com/yajra/laravel-oci8/pull/1007))
### 21c
- Use native json type instead of clob in schema builder. ([#983](https://github.com/yajra/laravel-oci8/pull/983))

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
## JSON support
Laravel-OCI8 provides JSON query support for Oracle databases.

>⚠️This requires Oracle 12c02 or higher.

JSON path updates using Laravel's `update(['json->key' => ...])` syntax are supported on Oracle 19c or higher:

```php
DB::table('users')
    ->where('id', $id)
    ->update(['options->profile->settings->theme' => 'dark']);
```

Only one JSON path may be updated for the same JSON column in a single `update()` call. Use separate `update()` calls for multiple paths, matching Laravel's PostgreSQL and MariaDB grammar behavior.

On Oracle versions below 19c, JSON path updates are not supported. Modify the full JSON document in the application and save the column value instead.

## Laravel Builder Compatibility

The tables below cover Laravel 13 builder operations whose behavior depends on the database driver. General fluent helpers inherited from Laravel follow their normal behavior. **Unsupported** operations throw an exception (or have no Oracle compiler); **No-op** options are accepted but do not change the generated Oracle SQL.

### Schema Builder

| Status | Functions / features | Notes |
|:--|:--|:--|
| Supported | Schema inspection: `getSchemas()`, `getCurrentSchemaListing()`, `getCurrentSchemaName()`, `hasTable()`, `hasView()`, `getTables()`, `getTableListing()`, `getViews()`, and `getTypes()` | Schema-qualified names are supported. |
| Supported | Column, index, and foreign-key inspection: `hasColumn()`, `hasColumns()`, `getColumnType()`, `getColumnListing()`, `getColumns()`, `hasIndex()`, `getIndexes()`, `getIndexListing()`, `hasForeignKey()`, and `getForeignKeys()` | The related `whenTableHas...` and `whenTableDoesntHave...` helpers are also supported. |
| Supported | Table operations: `create()`, `table()`, `rename()`, `drop()`, `dropIfExists()`, `dropColumns()`, `dropAllTables()`, `dropAllViews()`, and `dropAllTypes()` | `dropIfExists()` uses native syntax on Oracle 23ai and a PL/SQL fallback on older versions. |
| Supported | `temporary()` | Creates an Oracle global temporary table with `ON COMMIT PRESERVE ROWS`. |
| Supported | Character and text columns: `char()`, `string()`, `tinyText()`, `text()`, `mediumText()`, `longText()`, `uuid()`, `ulid()`, `ipAddress()`, `macAddress()`, and Oracle-specific `nvarchar2()` | Text types that exceed `VARCHAR2` limits are mapped to `CLOB`. |
| Supported | Numeric columns: integer and increment variants, `float()`, `real()`, `double()`, `decimal()`, and `boolean()` | Incrementing columns use sequences and triggers before Oracle 12c; identity columns are available on Oracle 12c+. |
| Supported | Date and time columns: `year()`, `date()`, `dateTime()`, `dateTimeTz()`, `time()`, `timestamp()`, `timestampTz()`, `timestamps()`, and their nullable/soft-delete helpers | Timestamp precision and `useCurrent()` are supported. |
| Supported | `enum()`, `json()`, `jsonb()`, `binary()`, and `rawColumn()` | `enum()` uses a check constraint. JSON uses native `JSON` on Oracle 21c+ and a constrained `CLOB` on Oracle 12c through 19c; Oracle 11g uses an unconstrained `CLOB`. |
| Supported | Column modifiers: `nullable()`, `default()`, `comment()`, `collation()`, `invisible()`, `virtualAs()`, `change()`, `generatedAs()`, `always()`, `onNull()`, `startingValue()`, and `from()` | Column collation requires Oracle 12cR2+; invisible and identity columns require Oracle 12c+. Changing a generated column expression is unsupported. |
| Supported | Indexes and constraints: `primary()`, `unique()`, `index()`, `fullText()`, `foreign()`, `renameIndex()`, and all corresponding drop methods, including `dropSpatialIndex()` | `online()`, `deferrable()`, `initiallyImmediate()`, `notValid()`, and foreign-key delete actions are supported where Oracle provides matching syntax. Full-text indexes require Oracle Text. |
| Supported | `enableForeignKeyConstraints()`, `disableForeignKeyConstraints()`, and `withoutForeignKeyConstraints()` | These operations affect the current user's foreign keys. |
| Supported | Schema dump and load | `schema:dump` uses the Oracle `exp` and `imp` command-line tools, which must be installed and available on `PATH`. |
| Unsupported | `createDatabase()` and `dropDatabaseIfExists()` | Oracle databases cannot be created or dropped through Laravel's schema builder. |
| Unsupported | `ensureExtensionExists()` and `ensureVectorExtensionExists()` | Laravel implements extensions only for PostgreSQL. |
| Unsupported | `set()`, `geometry()`, `geography()`, `computed()`, `vector()`, `tsvector()`, `spatialIndex()`, and `vectorIndex()` | `dropSpatialIndex()` is supported for an existing spatial index. |
| Unsupported | `timeTz()` | Oracle has no standalone `TIME WITH TIME ZONE` column mapping in this driver. |
| Unsupported | Changing a `virtualAs()` expression | Oracle generated expressions can be created, but this driver does not modify them in place. |
| No-op | Table options: `engine()`, `innoDb()`, `charset()`, and table-level `collation()` | These options do not affect Oracle `CREATE TABLE` SQL. Column-level `collation()` is supported. |
| No-op | Column options: `unsigned()`, `first()`, `after()`, `storedAs()`, `useCurrentOnUpdate()`, and column-level `charset()` | Oracle SQL generated by this driver does not include these attributes. The length and fixed-width arguments to `binary()` and precision on `dateTime()` / `time()` are also ignored. |
| No-op | Index algorithms | The algorithm argument accepted by `primary()`, `unique()`, `index()`, and `fullText()` is ignored. |
| No-op | Foreign-key update actions such as `cascadeOnUpdate()`, `restrictOnUpdate()`, `nullOnUpdate()`, and `noActionOnUpdate()` | Oracle foreign keys do not provide Laravel's `ON UPDATE` actions. |

### Query Builder

| Status | Functions / features | Notes |
|:--|:--|:--|
| Supported | Selects, aliases, expressions, subqueries, `distinct()`, unions, aggregates, grouping, having, ordering, and random ordering | Uses Oracle's regular row-level `DISTINCT`. |
| Supported | Standard `where` clauses, column comparisons, null-safe equality, `whereIn()` / `whereNotIn()`, ranges, date/time parts, row values, existence clauses, dynamic where clauses, and raw predicates | `whereIn()` automatically splits lists larger than Oracle's 1,000-expression limit. |
| Supported | `whereLike()` and `whereNotLike()` with case-sensitive or case-insensitive matching | Case-insensitive matching uses `COLLATE BINARY_CI` on Oracle 12cR2+ and an `UPPER(...)` fallback on older versions. |
| Supported | `whereFullText()` and `orWhereFullText()` | Uses Oracle Text `CONTAINS`; the required Oracle Text indexes and preferences must exist. |
| Supported | JSON path selection, boolean comparisons, `whereJsonContains()`, `whereJsonContainsKey()`, and `whereJsonLength()` | Requires Oracle 12c+. |
| Supported | Inner, left, right, cross, and subquery joins; `joinLateral()` and `leftJoinLateral()` | Lateral joins require Oracle 12c+. |
| Supported | Limits, offsets, unions with limits/offsets, pagination, cursor pagination, chunking, and `groupLimit()` | Oracle 12c+ uses `OFFSET` / `FETCH`; older versions use row-number wrapping. |
| Supported | `lockForUpdate()` and custom lock strings | `sharedLock()` is compiled as Oracle `FOR UPDATE`, so it takes an update lock rather than a shared lock. |
| Supported | `insert()`, batch insert, `insertUsing()`, `insertGetId()`, and `insertOrIgnore()` | `insertOrIgnore()` is implemented with `MERGE`; it matches all inserted columns, except Laravel's cache table, which matches `key`. |
| Supported | `update()`, joined updates, `updateFrom()`, `updateOrInsert()`, `increment()`, `decrement()`, and `upsert()` | Joined updates use Oracle `ROWID`; `upsert()` uses `MERGE`. |
| Supported | JSON path updates | Requires Oracle 19c+. Only one path per JSON column may be updated in one call. |
| Supported | `delete()` and joined deletes | Joined deletes depend on Oracle's key-preserved join-view rules. |
| Supported | `truncate()` and `OracleGrammar::cascadeOnTruncate()` | `TRUNCATE ... CASCADE` requires Oracle 12c+; the cascade flag is ignored on older versions. |
| Supported | Oracle-specific `insertLob()` and `updateLob()` | Handles `BLOB` / `CLOB` values through OCI8 LOB bindings. |
| Unsupported | `insertOrIgnoreReturning()` and `insertOrIgnoreUsing()` | No Oracle compiler is provided for these Laravel operations. |
| Unsupported | `whereJsonOverlaps()`, `whereJsonDoesntOverlap()`, and their `orWhere...` variants | JSON overlap predicates are not implemented. |
| Unsupported | `straightJoin()`, `straightJoinWhere()`, and `straightJoinSub()` | Straight joins are a MySQL-specific query hint. |
| Unsupported | `useIndex()`, `forceIndex()`, and `ignoreIndex()` | Laravel's portable index-hint API has no Oracle compiler. Use an Oracle hint in a raw expression when needed. |
| Unsupported | Vector helpers: `selectVectorDistance()`, `whereVectorSimilarTo()`, `whereVectorDistanceLessThan()`, and `orderByVectorDistance()` | Laravel currently restricts these helpers to PostgreSQL connections. |
| No-op | `timeout()` | The timeout value is stored on the builder but is not compiled into Oracle SQL. |
| No-op | The column list passed to `distinct(...)` | Oracle SQL applies `DISTINCT` to the complete selected row; the distinct-on column list is ignored. |
| No-op | `orderBy()`, `limit()`, and `offset()` on non-joined `update()`, on `updateFrom()`, or on `delete()` | These clauses are not included in those Oracle mutation statements. A joined `update()` applies them inside its `ROWID` subquery. |
| No-op | Options passed to `whereFullText()` | Oracle Text compilation currently ignores Laravel's language, mode, and expansion options. |

## Credits

- [Arjay Angeles][link-author]
- [Jimmy Felder](https://github.com/jfelder/Laravel-OracleDB)
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[link-author]: https://github.com/yajra
[link-contributors]: ../../contributors
