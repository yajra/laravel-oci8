# Laravel-OCI8 5.6 CHANGELOG

## [Unreleased]

## [v5.6.5] - 2019-02-19

- Fix [#485] - Preventing ORA-00933 when using fromSub method. [#486], credits to [@renanwilliam].

## [v5.6.4] - 2018-09-29

- Added Support for Oracle Edition Based Redefinition [#439], credits to [@Adam2Marsh]

## [v5.6.3] - 2018-05-07

- Add support for migrate:fresh command. [#437]
- Fix [#435].

## [v5.6.2] - 2018-05-05

- Escape ENUM column name to avoid problems with reserved words [#432], credits to [@Stolz].
- Fixes issue [#431].

## [v5.6.1] - 2018-04-17

- Fix [#413], binding issue.

## [v5.6.0] - 2018-02-16

- Add support for Laravel 5.6.
- Fix compatbility with PHP 7.2.
- Fix Declaration of causedByLostConnection [#407], credits to [@FabioSmeriglio].
- Fix [#406], [#404].
- Added more options to Sequence Create Method [#355], credits to [@nikklass].

[Unreleased]: https://github.com/yajra/laravel-oci8/compare/v5.6.5...5.6
[v5.6.5]: https://github.com/yajra/laravel-oci8/compare/v5.6.4...v5.6.5
[v5.6.4]: https://github.com/yajra/laravel-oci8/compare/v5.6.3...v5.6.4
[v5.6.3]: https://github.com/yajra/laravel-oci8/compare/v5.6.2...v5.6.3
[v5.6.2]: https://github.com/yajra/laravel-oci8/compare/v5.6.1...v5.6.2
[v5.6.1]: https://github.com/yajra/laravel-oci8/compare/v5.6.0...v5.6.1
[v5.6.0]: https://github.com/yajra/laravel-oci8/compare/v5.5.7...v5.6.0

[#355]: https://github.com/yajra/laravel-oci8/pull/355
[#407]: https://github.com/yajra/laravel-oci8/pull/407
[#432]: https://github.com/yajra/laravel-oci8/pull/432
[#437]: https://github.com/yajra/laravel-oci8/pull/437
[#439]: https://github.com/yajra/laravel-oci8/pull/439
[#486]: https://github.com/yajra/laravel-oci8/pull/486

[#413]: https://github.com/yajra/laravel-oci8/issue/413
[#406]: https://github.com/yajra/laravel-oci8/issue/406
[#404]: https://github.com/yajra/laravel-oci8/issue/404
[#431]: https://github.com/yajra/laravel-oci8/issue/431
[#435]: https://github.com/yajra/laravel-oci8/issue/435
[#485]: https://github.com/yajra/laravel-oci8/issue/485

[@FabioSmeriglio]: https://github.com/FabioSmeriglio
[@nikklass]: https://github.com/nikklass
[@Stolz]: https://github.com/Stolz
[@Adam2Marsh]: https://github.com/Adam2Marsh
[@renanwilliam]: https://github.com/renanwilliam
