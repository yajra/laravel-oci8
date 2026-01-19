# [12.6.0](https://github.com/yajra/laravel-oci8/compare/v12.5.0...v12.6.0) (2026-01-19)


### Bug Fixes

* withExists and add test for it ([d1cb941](https://github.com/yajra/laravel-oci8/commit/d1cb941c277502c54adcc45bd1c8a07f39db091f))


### Features

* clob binding in upsert when above 3999 char and add tests for it ([80fd461](https://github.com/yajra/laravel-oci8/commit/80fd461fcd73061c41f6a6c176857610196dafac))
* improve tests for withExists and add tests other similar with functions ([a13fa1e](https://github.com/yajra/laravel-oci8/commit/a13fa1e81d21a6c49d144a7039ccdf517e723c44))

# [12.5.0](https://github.com/yajra/laravel-oci8/compare/v12.4.0...v12.5.0) (2026-01-13)


### Bug Fixes

* pint :robot: ([2b84ee9](https://github.com/yajra/laravel-oci8/commit/2b84ee9cf24a41b26937b9e5d01cbf0a01e1fb37))
* preserve nullable constraint when modifying Oracle columns ([b6dd9be](https://github.com/yajra/laravel-oci8/commit/b6dd9be7bc9cf5a3d25fb8a2ecc594517e72f516)), closes [#941](https://github.com/yajra/laravel-oci8/issues/941)


### Features

* add generatedAs function similar to pgsql ([1c5dbe8](https://github.com/yajra/laravel-oci8/commit/1c5dbe828c4b6683a64058eada2281ea3b045725))
* add onNull to match trigger style logic, add tests for it, mark 12c tests as skipped instead of empty ([c0a650c](https://github.com/yajra/laravel-oci8/commit/c0a650cf2d2cb74fd4c5171f0b765b783d58fcc2))
* override Blueprint id() function based on oracle version ([d800b3d](https://github.com/yajra/laravel-oci8/commit/d800b3d5144875b1a4c16398bdc8f4a1197c7a16))

# [12.4.0](https://github.com/yajra/laravel-oci8/compare/v12.3.2...v12.4.0) (2026-01-06)


### Bug Fixes

* json tests ([6e63048](https://github.com/yajra/laravel-oci8/commit/6e630483c7424f6f71e468a32f86b39ec9050dbc))
* wrong comment ([a1354a3](https://github.com/yajra/laravel-oci8/commit/a1354a3081d2ec76447a356e7ea1465b5377d6b5))


### Features

* add more json tests ([bc85c02](https://github.com/yajra/laravel-oci8/commit/bc85c02af886e2c893ca37fefb642185f562b9aa))
* add support for whereJsonContains ([638cfec](https://github.com/yajra/laravel-oci8/commit/638cfec0658238495e9dab52305feed2e464875f))
* implement whereJsonBoolean, whereJsonContainsKey, whereJsonLength, add tests for them and further improve tests ([1e3cfc6](https://github.com/yajra/laravel-oci8/commit/1e3cfc6e5b07ce42bff74b9056f9f41d84f43833))
* scaffold compatibility tests for pgsql ([c756b2c](https://github.com/yajra/laravel-oci8/commit/c756b2cb8bbc945198d437c6af1a10f8c0ed0c05))
* update readme with json support ([2c14876](https://github.com/yajra/laravel-oci8/commit/2c14876669e800b056b5d1c8f397923eaf16dd22))

## [12.3.2](https://github.com/yajra/laravel-oci8/compare/v12.3.1...v12.3.2) (2025-12-31)


### Bug Fixes

* raw query in multiple insert ([8526b40](https://github.com/yajra/laravel-oci8/commit/8526b40b7d4bc69226c564a20166adcdd27f8c4d))

## [12.3.1](https://github.com/yajra/laravel-oci8/compare/v12.3.0...v12.3.1) (2025-12-20)


### Bug Fixes

* use limit syntax for 12c only when limit is set ([1e4586e](https://github.com/yajra/laravel-oci8/commit/1e4586e86c6977f2247f1cfd1138ae8f87987234))

# [12.3.0](https://github.com/yajra/laravel-oci8/compare/v12.2.0...v12.3.0) (2025-12-11)


### Bug Fixes

* pint :robot: ([e76225d](https://github.com/yajra/laravel-oci8/commit/e76225d52879d04e50cea27c723f011e5a6eb49d))


### Features

* use COLLATE BINARY_CI in where like for 12c+ ([c23c671](https://github.com/yajra/laravel-oci8/commit/c23c6715a7f5b7ed1ae28e2bfe33ba41343332f3))

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
