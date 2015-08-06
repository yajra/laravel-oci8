#Laravel-OCI8 Change Log

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
