# Laravel-OCI8 5.5 CHANGELOG

## [Unreleased]

## [v5.5.6] - 2018-01-04

- Update to Mockery 1 [#379], credits to [@gabriel-caruso](https://github.com/gabriel-caruso).
- Fix php72 compatibility. [#395], credits to [@joaorobertopb](https://github.com/joaorobertopb).
- Test against PHP 7.2 [#382]

## [v5.5.5] - 2017-10-21

- Fix undefined index options. [#375], credits to [@reyvhernandez](https://github.com/reyvhernandez).

## [v5.5.4] - 2017-10-21

- Add Reconnect Logic. [#368], credits to [@Stoffo](https://github.com/Stoffo).

## [v5.5.3] - 2017-09-21

- Use uppercase for reserved words on schema builder. [#351]
- Fix [#350].

## [v5.5.2] - 2017-09-19

- Do not wrap numeric values. Fix [#346].
- PR [#348], credits to [@ircop](https://github.com/ircop).

## [v5.5.1] - 2017-09-13

- Apply clean code concepts [#339], credits to [@joaorobertopb](https://github.com/joaorobertopb).
- Merge changes from 5.4 from PR [#340] & [#342].
- Extended configuration for parameter bindings [#340], credits to [@mstaack](https://github.com/mstaack).

## [v5.5.0] - 2017-08-31

### Added

- Added support for Laravel 5.5
- Added automatic package discovery for Laravel 5.5 [#305], credits to [@mazedlx](https://github.com/mazedlx).

### Changed

- Use upper case column name for reserved words. [#262], credits to [@aleister999](https://github.com/aleister999).

[Unreleased]: https://github.com/yajra/laravel-oci8/compare/v5.5.6...5.5
[v5.5.5]: https://github.com/yajra/laravel-oci8/compare/v5.5.5...v5.5.6
[v5.5.5]: https://github.com/yajra/laravel-oci8/compare/v5.5.4...v5.5.5
[v5.5.4]: https://github.com/yajra/laravel-oci8/compare/v5.5.3...v5.5.4
[v5.5.3]: https://github.com/yajra/laravel-oci8/compare/v5.5.2...v5.5.3
[v5.5.2]: https://github.com/yajra/laravel-oci8/compare/v5.5.1...v5.5.2
[v5.5.1]: https://github.com/yajra/laravel-oci8/compare/v5.5.0...v5.5.1
[v5.5.0]: https://github.com/yajra/laravel-oci8/compare/v5.4.18...v5.5.0

[#395]: https://github.com/yajra/laravel-oci8/pull/395
[#382]: https://github.com/yajra/laravel-oci8/pull/382
[#375]: https://github.com/yajra/laravel-oci8/pull/375
[#368]: https://github.com/yajra/laravel-oci8/pull/368
[#351]: https://github.com/yajra/laravel-oci8/pull/351
[#350]: https://github.com/yajra/laravel-oci8/issue/350
[#348]: https://github.com/yajra/laravel-oci8/pull/348
[#346]: https://github.com/yajra/laravel-oci8/pull/346
[#342]: https://github.com/yajra/laravel-oci8/pull/342
[#340]: https://github.com/yajra/laravel-oci8/pull/340
[#339]: https://github.com/yajra/laravel-oci8/pull/339
[#305]: https://github.com/yajra/laravel-oci8/pull/305
[#262]: https://github.com/yajra/laravel-oci8/pull/262
