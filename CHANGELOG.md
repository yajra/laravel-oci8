# Laravel-OCI8 CHANGELOG

## [Unreleased](https://github.com/yajra/laravel-oci8/compare/v10.0.0...10.x)

## [v10.6.0](https://github.com/yajra/laravel-oci8/compare/v10.5.3...v10.6.0) - 2024-06-02

- feat: sync 11.x grammar and builder to 10.x #859
- feat: add support for Schema::getIndexes #856 
- fix: Schema::dropIfExists #854 
- fix: pagination when sorting by string with same values #850 
- fix: migration when column name has space #849 
- fix: retry on lost connection #846 
- feat: Improve schema grammar #842

## [v10.5.3](https://github.com/yajra/laravel-oci8/compare/v10.5.2...v10.5.3) - 2024-05-07

- fix: pagination when sorting by string with same values #851
- revert: #611 
- fix: #651

## [v10.5.2](https://github.com/yajra/laravel-oci8/compare/v10.5.1...v10.5.2) - 2024-03-16

- fix: missing auto_increment key #838
- fix: #837

## [v10.5.1](https://github.com/yajra/laravel-oci8/compare/v10.5.0...v10.5.1) - 2024-03-02

- fix: Schema::getColumns() from the current schema. [0b33a39](https://github.com/yajra/laravel-oci8/commit/0b33a392959f8259a91f64047c836a74413d8a16)

## [v10.5.0](https://github.com/yajra/laravel-oci8/compare/v10.4.4...v10.5.0) - 2024-03-02

- feat: add support for Schema::getColumns() #836
- fix: #831

## [v10.4.4](https://github.com/yajra/laravel-oci8/compare/v10.4.3...v10.4.4) - 2024-03-02

- fix: lock for update with orders #834
- fix: #832
- fix: #824

## [v10.4.3](https://github.com/yajra/laravel-oci8/compare/v10.4.2...v10.4.3) - 2024-02-17

- fix: lock for update implementation #824
- fix: #819
- fix: forPage SQL
- fix: compileSavepointRollBack

## [v10.4.2](https://github.com/yajra/laravel-oci8/compare/v10.4.1...v10.4.2) - 2024-02-03

- fix: multiple blob update and add test #821
- fix: #820

## [v10.4.1](https://github.com/yajra/laravel-oci8/compare/v10.4.0...v10.4.1) - 2024-01-24

- fix: ORA-38104: Columns referenced in the ON Clause cannot be updated #818

## [v10.4.0](https://github.com/yajra/laravel-oci8/compare/v10.3.4...v10.4.0) - 2024-01-24

- feat: add support for upsert sql #816
- Apply fixes from StyleCI #817

## [v10.3.4](https://github.com/yajra/laravel-oci8/compare/v10.3.3...v10.3.4) - 2023-12-02

- fix: Handle options being passed as part of a binding array #814
- fix: Addresses issue #813

## [v10.3.3](https://github.com/yajra/laravel-oci8/compare/v10.3.2...v10.3.3) - 2023-11-29

- fix: read sessionVars config read from current database connection #812
- fix #811 and improves handle of default values.

## [v10.3.2](https://github.com/yajra/laravel-oci8/compare/v10.3.1...v10.3.2) - 2023-07-14

- fix: toRawSql #798

## [v10.3.1](https://github.com/yajra/laravel-oci8/compare/v10.3.0...v10.3.1) - 2023-06-29

- fix: config structure #793
- fix: #792
- fix: ci/cd - tests workflow #786

## [v10.3.0](https://github.com/yajra/laravel-oci8/compare/v10.2.0...v10.3.0) - 2023-03-03

- feat: Add compatibility with startingValue #778

## [v10.2.0](https://github.com/yajra/laravel-oci8/compare/v10.1.0...v10.2.0) - 2023-03-01

- feat: change limit character for 12c02 #777
- fix: #770, #422, #423, #430

## [v10.1.0](https://github.com/yajra/laravel-oci8/compare/v10.0.0...v10.1.0) - 2023-02-28

- feat: determine max object length based on DB version #768

## [v10.0.0](https://github.com/yajra/laravel-oci8/compare/v10.0.0...10.x) - 2023-02-19

- Laravel 10 support.
