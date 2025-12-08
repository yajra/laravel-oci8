# [12.2.0](https://github.com/yajra/laravel-oci8/compare/v12.1.10...v12.2.0) (2025-12-08)


### Bug Fixes

* when values are empty use ($sequence) values(DEFAULT) syntax in compileInsertGetId ([953bd88](https://github.com/yajra/laravel-oci8/commit/953bd88539e9ae5c4c102d1158ecd2c9de48e6bb))


### Features

* **ci:** run tests for 21c too ([fdea0ab](https://github.com/yajra/laravel-oci8/commit/fdea0ab38e9fc8bfdfe46a63c714735359aad5ae))

## [12.1.10](https://github.com/yajra/laravel-oci8/compare/v12.1.9...v12.1.10) (2025-10-15)


### Bug Fixes

* **#938:** use offsets only when no locks are used ([5bf8d40](https://github.com/yajra/laravel-oci8/commit/5bf8d40baf07b6f706821eb0325dcf91cc1fb028)), closes [#938](https://github.com/yajra/laravel-oci8/issues/938)
* Fix issue where db_prefix is not considered in max length ([ffceb87](https://github.com/yajra/laravel-oci8/commit/ffceb875500a305fd72f3b2e08c4a9220181846b))
* pint :robot: ([3826188](https://github.com/yajra/laravel-oci8/commit/3826188ad732c02debfa8555570f3406684a8f6d))

# Laravel-OCI8 CHANGELOG

## [Unreleased](https://github.com/yajra/laravel-oci8/compare/master...12.x)

## v12.1.9 - 2025-10-01

- fix: Fix issue where db_prefix is not considered in max length #934

## v12.1.8 - 2025-09-18

- Fix runPaginationCountQuery for Oracle: remove AS from subquery alias on version 12 #932
- Fix https://github.com/yajra/laravel-oci8/issues/927

## v12.1.7 - 2025-09-18

- Fix duplicate ORDER BY in Oracle lock queries #930

## v12.1.6 - 2025-09-06

- Fix Migration comment not working, ORA-00904 #929
- Fix https://github.com/yajra/laravel-oci8/issues/910

## v12.1.5 - 2025-08-08

- fix: Remove unnecessary wrapping of sequence name in drop method #926

## v12.1.4 - 2025-07-24

- fix: log query time to be compatible with laravel logging #925

## v12.1.3 - 2025-07-11

- fix: php artisan db:show #924

## v12.1.2 - 2025-07-02

- fix: ORA-00942 when try to drop any table #923
- fix: https://github.com/yajra/laravel-oci8/issues/916
- fix: https://github.com/yajra/laravel-oci8/issues/917

## v12.1.1 - 2025-06-15

- fix: pagination error "Undefined property: stdClass::$aggregate" #921
- fix: schema prefix implementation #920

## v12.1.0 - 2025-05-28

- feat: add support for dateTime with timezone #918

## v12.0.2 - 2025-03-19

- fix: ORA-00942, wrong trigger prefix #909
- fix: #905 
- fix: drop if exist when using schema prefix 498fb94

## v12.0.1 - 2025-03-12

- fix: compileDropIfExists #907

## v12.0.0 - 2025-02-28

- Laravel 12 support
- fix: [12.x] Fix accessing Connection property in Grammar classes laravel/framework#54487 
- fix: generated constraint name to include prefix 
- feat: sequence and trigger table wrapping 
- fix: Cannot use sequences not owned by DB user #780
- feat: added phpstan and rector workflow
- feat: FullText Search in laravel-oci8 #800
