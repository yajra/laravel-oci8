# Laravel-OCI8 5.5 CHANGELOG

## [Unreleased]

## [v5.6.3] - 2018-05-07

- Add support for migrate:fresh command. [#437]
- Fix [#435].

## [v5.6.2] - 2018-04-17

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

[Unreleased]: https://github.com/yajra/laravel-oci8/compare/v5.6.3...5.6
[v5.6.3]: https://github.com/yajra/laravel-oci8/compare/v5.6.2...v5.6.3
[v5.6.2]: https://github.com/yajra/laravel-oci8/compare/v5.6.1...v5.6.2
[v5.6.1]: https://github.com/yajra/laravel-oci8/compare/v5.6.0...v5.6.1
[v5.6.0]: https://github.com/yajra/laravel-oci8/compare/v5.5.7...v5.6.0

[#407]: https://github.com/yajra/laravel-oci8/pull/407
[#432]: https://github.com/yajra/laravel-oci8/pull/432
[#437]: https://github.com/yajra/laravel-oci8/pull/437

[#413]: https://github.com/yajra/laravel-oci8/issue/413
[#406]: https://github.com/yajra/laravel-oci8/issue/406
[#404]: https://github.com/yajra/laravel-oci8/issue/404
[#431]: https://github.com/yajra/laravel-oci8/issue/431
[#435]: https://github.com/yajra/laravel-oci8/issue/435

[@FabioSmeriglio]: https://github.com/FabioSmeriglio
[@nikklass]: https://github.com/nikklass
[@Stolz]: https://github.com/Stolz
