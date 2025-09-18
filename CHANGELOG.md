# Laravel-OCI8 CHANGELOG

## [Unreleased](https://github.com/yajra/laravel-oci8/compare/master...12.x)

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
