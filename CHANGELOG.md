#Laravel-OCI8 Change Log

#v5.3.5 - 2016-10-12
- Implement oracle user provider.

#v5.3.4 - 2016-10-11
- Add support for wrapping of schema changes in transaction.
- https://github.com/laravel/framework/pull/15780

#v5.3.3 - 2016-10-10
- Fix table wrapper that uses as keyword. Fix #211.

#v5.3.2 - 2016-08-30
- Implement executeProcedure method. Credits to @mstaack.
- Fix ORA-01790: expression must have same datatype as corresponding expression.

#v5.3.1 - 2016-08-24
- Apply patch for best practices as suggested by scrutinizer.

#v5.3.0 - 2016-08-24
- Laravel 5.3 support.

#v5.2.11 - 2016-07-12
- Added option for skipping setSessionVars. #185
- Update OCi8Connection->setSessionVars(). #184

#v5.2.10 - 2016-06-24
- Fix 'user' param when creating a Doctrine connection. PR #182
- Function to execute PL/SQL functions at one shoot. PR #183

#v5.2.9 - 2016-06-14
- OracleEloquent switch QueryBuilder implementation depending grammar.
- PR #178, credits to @MTon.

#v5.2.8 - 2016-06-12
- Fix hasColumn function. Fix #175
- PR #176, credits to @azrael-sub7.

#v5.2.7 - 2016-05-18
- Fix fetching of primary key.
- Patch docblocks.

#v5.2.6 - 2016-05-18
- Fix/Add wrapper when creating auto-increment trigger that contains reserved words.
- Update Trigger class docblocks.

#v5.2.5 - 2016-04-30
- Add nvarchar2 support for schema builder.
- PR #168, credits to @pawel-damasiewicz.

#v5.2.4 - 2016-04-05
- Refactor add prefix and fix join prefix.
- Replace user_tables with all_tables
- Replace user_tab_columns with all_tab_columns.
- Update unit tests.

#v5.2.3 - 2016-03-19
- Fix prefix schema on update, insert, delete query.
- PR #158. Credits to @mfrancois.

#v5.2.2 - 2016-03-18
- Auto increment primary key using custom sequence without trigger.
- PR #156. Credits to @ChaosPower.

#v5.2.1 - 2016-03-09
- Implement schema prefix option.
- PR #154, credits to @mfrancois.

#v5.2.0 - 2016-03-08
- Dedicated branch/tag for Laravel 5.2 support.

#v5.1.0 - 2016-03-08
- Dedicated branch/tag for Laravel 5.1 support.

#v4.2.6 - 2015-02-09
- Return empty string instead of throwing lock shared mode exception.
- Cast all object values to string when binding.

#v4.2.5
- Remove PDO typehint to allow closure.
- Use getPdo() when using doctrine connection.
- Fix #143.

#v4.2.4
- Fix new instance of Oci8Connection with config on last parameter. PR #142

#v4.2.3
- Convert DateTime instance to string.
- Fix issue #134.

#v4.2.2
- Fix compileColumnExists method. PR #136

#v4.2.1
- Drop sequence and trigger if table is dropped through Blueprint. Fix #106.

#v4.2.0
- Use shorter index name. PR #132, Issue #131.

#v4.1.2
- Wrap reserved words when commenting on table or columns. PR #128

#v4.1.1
- Fix update method compatibility with L5.2. Fix #127.

#v4.1.0
- Fix pluck unit tests to passed Laravel 5.2.
- New feature to add comments on columns and table. #124 - Credits to @rafael-renan-pacheco

    When creating a table:
    ```php
    Schema::create('flights', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name')->comment('Flight name'); /* Column comment */
        $table->string('airline')->comment('Airline name'); /* Column comment */

        $table->comment = 'Flights table'; /* Table comment */
    });
    ```

    When modifying a table:
    ```php
    Schema::table('flights', function ($table) {
        $table->comment = 'A flights table'; /* Table comment */

        $table->commentColumns = [
            'name' => 'This is the flight name', /* Column comment */
            'airline' => 'This is the airline name' /* Column comment */
        ];
    });
    ```

#v4.0.3
- Scrutinizer code refactoring.

#v4.0.2
- Add config_path for Lumen. Fix #123

#v4.0.1
- Fix PDO Type detection when binding values. Fix #122

#v4.0.0
- Change vendor namespace from yajra to Yajra.
- Remove own pluck implementation. Frameworks implementation works out of the box.
- Publishing of config file is now optional.
- Improve query when expecting first row as result.
- Removes unwanted "rn" column being returned when executing first() queries.
- Improve exists query. #107
- Add support for date based queries.
- Implement quoting of Oracle reserved words. #93
- Enhance auto-increment trigger and remove unnecessary updating sql. #112
- Add more tests.
- Fix compatibility issues with PHP 7.

#v3.0.0
- Drop support for Laravel 4.2 & 5.0.
- Drop Support for PHP 5.4.
- Use PSR-4 auto loading.
- Add oracle config file.
- Update CS style using Laravel 5.1 php cs config.

#v2.4.4
- Add timestampTz support #101.

#v2.4.3
- Add checker if pdo is in transaction. Fix #83
- Use ~0.14 as default pdo-via-oci8 version.

#v2.4.2
- Add support for model using DB Link.
- Fix #79. Credits to @jbaron30.

#v2.4.1
- Reverted. Fix UnexpectedValueException when returning response using first().
- Minor code clean-up and updated doc blocks.

#v2.4.0
- Converted source code to PSR1/2 coding standard.
- Fix ORA-01002 when usng lockForUpdate.
- Fix ORA-00907 issue #76.
- Fix UnexpectedValueException when returning response using first().
- Throws Oci8Exception when using sharedLock. Not supported atm.
- Will now use git flow process when releasing changes.

#v2.3.1
- Fix OracleEloquent Blob insert/update function when updating only the blob field
- Fix Issue #70

#v2.3.0
- added support for Oracle Cursor to be returned via Query Builder
- requires `yajra/laravel-pdo-via-oci8:~0.12`

#v2.2.0
- added support for Laravel Lumen

#v2.1.4
- refactor alter session functions
- enable query log when app.debug = true
	- temporary solution for https://github.com/laravel/framework/issues/7085

#v2.1.3
- fix set schema alter session query
- enhance oracle alter session variables query

#v2.1.2
- improve oracle alter session query

#v2.1.1
- remove boot/package function on Oci8ServiceProvider to fix compatibly with Laravel5

#v2.1.0
- Added support for CHAR column data type. Fix #51
- Fix failing/todo unit tests

#v2.0.8
- Enhance support for TNSNAMES.ORA connection via config[tns]
- Fix set schema function

#v2.0.7
- Rollback auto create constraint name
- As per Laravel Docs, constraint full name should be passed (my bad >.<)

#v2.0.6
- Refactor drop constraints grammar
- Fix drop constraint name exceeding 30 chars

#v2.0.5
- Fix drop primary grammar
- Fix drop foreign grammar
- Fix drop unique grammar
- Fix drop index grammar

#v2.0.4
- Fix undefined charset

#v2.0.3
- Code clean up and refactoring

#v2.0.2
- Refactor OracleAutoIncrementHelper
- Added Sequence Class
- Added Trigger Class

#v2.0.1
- Bug fixes and refactoring
- Added OracleAutoIncrementHelper

#v2.0.0
- Added support for Laravel 5
- Drop support for Laravel 4.0 and 4.1

#v1.15.0
- Stable version for Laravel 4.0, 4.1 and 4.2
