# Laravel-OCI8 CHANGELOG

## [Unreleased](https://github.com/yajra/laravel-oci8/compare/v9.0.0...9.x)

## [v9.5.4](https://github.com/yajra/laravel-oci8/compare/v9.5.3...v9.5.4) - 2024-06-18

- revert: #611 #861
- fix: pagination

## [v9.5.3](https://github.com/yajra/laravel-oci8/compare/v9.5.2...v9.5.3) - 2024-02-03

- fix: multiple blob update and add test #821
- fix: #820

## [v9.5.2](https://github.com/yajra/laravel-oci8/compare/v9.5.1...v9.5.2) - 2023-12-06

- fix: Handle options being passed as part of a binding array #814

## [v9.5.1](https://github.com/yajra/laravel-oci8/compare/v9.5.0...v9.5.1) - 2023-07-01

- fix: config structure #795
- fix: #792
- fix: ci/cd

## [v9.5.0](https://github.com/yajra/laravel-oci8/compare/v9.4.0...v9.5.0) - 2023-03-01

- feat: change limit character for 12c02 #762 (#774)
- fix: #770

## [v9.4.0](https://github.com/yajra/laravel-oci8/compare/v9.3.1...v9.4.0) - 2023-02-28

- feat: determine max object length based on DB version #767

## [v9.3.1](https://github.com/yajra/laravel-oci8/compare/v9.3.0...v9.3.1) - 2023-01-04

- fix: unique index #759

## [v9.3.0](https://github.com/yajra/laravel-oci8/compare/v9.2.1...v9.3.0) - 2023-01-04

- feat: add support for json query #755

## [v9.2.1](https://github.com/yajra/laravel-oci8/compare/v9.2.0...v9.2.1) - 2023-01-04

- fix: ORA-00969 when creating indexes

## [v9.2.0](https://github.com/yajra/laravel-oci8/compare/v9.1.0...v9.2.0) - 2022-12-11

- feat: add random order on grammar #740

## [v9.1.0](https://github.com/yajra/laravel-oci8/compare/v9.0.4...v9.1.0) - 2022-12-06

- feat: make session variables configurable #738

## [v9.0.4](https://github.com/yajra/laravel-oci8/compare/v9.0.3...v9.0.4) - 2022-11-11

- Allow for a large number of column names in compound indicies #735

## [v9.0.3](https://github.com/yajra/laravel-oci8/compare/v9.0.2...v9.0.3) - 2022-06-15

- Add default fetch mode #721

## [v9.0.2](https://github.com/yajra/laravel-oci8/compare/v9.0.1...v9.0.2) - 2022-05-12

- Fix delete query with join #715

## [v9.0.1](https://github.com/yajra/laravel-oci8/compare/v9.0.0...v9.0.1) - 2022-05-12

- Add Oci8Driver getName method #711
- Fix #710
- Fix Schema::getColumnType()
- Add test for Schema::getColumnType()

## [v9.0.0](https://github.com/yajra/laravel-oci8/compare/8.x...v9.0.0) - 2022-02-09

- Add support for Laravel 9 #698
- Breaking Change: Fix #696 rename DB_SERVICENAME to DB_SERVICE_NAME [db5037e](https://github.com/yajra/laravel-oci8/commit/db5037eb83bfadf3c1400d8c5780d3270e7c315f)
