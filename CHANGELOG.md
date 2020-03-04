# Laravel-OCI8 CHANGELOG

## [Unreleased]

## [v7.0.0] - 2020-03-04

- Add support for Laravel 7 [#565].
- Fix [#564].

## [v6.1.0] - 2020-02-11

- Add support for joinSub. [#551], credits to [@mozgovoyandrey].
- Add jobSub tests [#560].
- Apply StyleCI laravel preset changes.

## [v6.0.4] - 2019-11-26

- Wrap sequence name with schema prefix if set. [#535]
- Fix [#523].

## [v6.0.3] - 2019-11-26

- `saveLob` - Parameter should start at 1. [#543], credits to [@jeidison].

## [v6.0.2] - 2019-10-18

- Fix bug from pull request [#532] [#538], credits to [@dantesCode].

## [v6.0.1] - 2019-10-11

- Fix whereInRaw and whereNotInRaw Grammar. [#532], credits to [@dantesCode].
- Fix [#464], [#405], [#73].

## [v6.0.0] - 2019-09-05

- Laravel 6 support. [#505]
- Allow custom sequence name on model nextValue. [#511]

## [v5.8.2] - 2019-06-25

- Added illuminate/auth as dependency in composer.json [#508], credits to [@tumainimosha]

## [v5.8.1] - 2019-04-24

- Fix stripping of AS from table name. [#504]
- Facilitate wallet support. [#474]
- Fix changelog dates & Update license to 2019 [#498]

## [v5.7.3] - 2019-02-19

- Fix [#485] - Preventing ORA-00933 when using fromSub method. [#486], credits to [@renanwilliam].

## [v5.7.2] - 2018-09-29

- Added Support for Oracle Edition Based Redefinition [#439][#465], credits to [@Adam2Marsh]

## [v5.7.1] - 2018-09-20

- Fix paginate(1) SQL. [#461]
- Fix [#458].

## [v5.7.0] - 2018-09-05

- Add support for Laravel 5.7 [#457], credits to [@gredimano]


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

[Unreleased]: https://github.com/yajra/laravel-oci8/compare/v7.0.0...7.x
[v7.0.0]: https://github.com/yajra/laravel-oci8/compare/v6.1.0...v7.0.0
[v6.1.0]: https://github.com/yajra/laravel-oci8/compare/v6.0.4...v6.1.0
[v6.0.4]: https://github.com/yajra/laravel-oci8/compare/v6.0.3...v6.0.4
[v6.0.3]: https://github.com/yajra/laravel-oci8/compare/v6.0.2...v6.0.3
[v6.0.2]: https://github.com/yajra/laravel-oci8/compare/v6.0.1...v6.0.2
[v6.0.1]: https://github.com/yajra/laravel-oci8/compare/v6.0.0...v6.0.1
[v6.0.0]: https://github.com/yajra/laravel-oci8/compare/v5.8.2...v6.0.0
[v5.8.2]: https://github.com/yajra/laravel-oci8/compare/v5.8.1...v5.8.2
[v5.8.1]: https://github.com/yajra/laravel-oci8/compare/v5.8.0...v5.8.1
[v5.8.0]: https://github.com/yajra/laravel-oci8/compare/v5.7.3...v5.8.0
[v5.7.3]: https://github.com/yajra/laravel-oci8/compare/v5.7.2...v5.7.3
[v5.7.2]: https://github.com/yajra/laravel-oci8/compare/v5.7.1...v5.7.2
[v5.7.1]: https://github.com/yajra/laravel-oci8/compare/v5.7.0...v5.7.1
[v5.7.0]: https://github.com/yajra/laravel-oci8/compare/v5.6.2...v5.7.0
[v5.6.3]: https://github.com/yajra/laravel-oci8/compare/v5.6.2...v5.6.3
[v5.6.2]: https://github.com/yajra/laravel-oci8/compare/v5.6.1...v5.6.2
[v5.6.1]: https://github.com/yajra/laravel-oci8/compare/v5.6.0...v5.6.1
[v5.6.0]: https://github.com/yajra/laravel-oci8/compare/v5.5.7...v5.6.0

[#355]: https://github.com/yajra/laravel-oci8/pull/355
[#407]: https://github.com/yajra/laravel-oci8/pull/407
[#432]: https://github.com/yajra/laravel-oci8/pull/432
[#437]: https://github.com/yajra/laravel-oci8/pull/437
[#457]: https://github.com/yajra/laravel-oci8/pull/457
[#461]: https://github.com/yajra/laravel-oci8/pull/461
[#439]: https://github.com/yajra/laravel-oci8/pull/439
[#465]: https://github.com/yajra/laravel-oci8/pull/465
[#486]: https://github.com/yajra/laravel-oci8/pull/486
[#491]: https://github.com/yajra/laravel-oci8/pull/491
[#504]: https://github.com/yajra/laravel-oci8/pull/504
[#474]: https://github.com/yajra/laravel-oci8/pull/474
[#498]: https://github.com/yajra/laravel-oci8/pull/498
[#508]: https://github.com/yajra/laravel-oci8/pull/508
[#505]: https://github.com/yajra/laravel-oci8/pull/505
[#511]: https://github.com/yajra/laravel-oci8/pull/511
[#532]: https://github.com/yajra/laravel-oci8/pull/532
[#538]: https://github.com/yajra/laravel-oci8/pull/538
[#543]: https://github.com/yajra/laravel-oci8/pull/543
[#535]: https://github.com/yajra/laravel-oci8/pull/535
[#551]: https://github.com/yajra/laravel-oci8/pull/551
[#560]: https://github.com/yajra/laravel-oci8/pull/560
[#565]: https://github.com/yajra/laravel-oci8/pull/565

[#564]: https://github.com/yajra/laravel-oci8/issue/564
[#523]: https://github.com/yajra/laravel-oci8/issue/523
[#413]: https://github.com/yajra/laravel-oci8/issue/413
[#406]: https://github.com/yajra/laravel-oci8/issue/406
[#404]: https://github.com/yajra/laravel-oci8/issue/404
[#431]: https://github.com/yajra/laravel-oci8/issue/431
[#435]: https://github.com/yajra/laravel-oci8/issue/435
[#458]: https://github.com/yajra/laravel-oci8/issue/458
[#485]: https://github.com/yajra/laravel-oci8/issue/485
[#464]: https://github.com/yajra/laravel-oci8/issue/464
[#405]: https://github.com/yajra/laravel-oci8/issue/405
[#73]: https://github.com/yajra/laravel-oci8/issue/73

[@FabioSmeriglio]: https://github.com/FabioSmeriglio
[@nikklass]: https://github.com/nikklass
[@Stolz]: https://github.com/Stolz
[@gredimano]: https://github.com/gredimano
[@Adam2Marsh]: https://github.com/Adam2Marsh
[@renanwilliam]: https://github.com/renanwilliam
[@tumainimosha]: https://github.com/tumainimosha
[@dantesCode]: https://github.com/dantesCode
[@jeidison]: https://github.com/jeidison
[@mozgovoyandrey]: https://github.com/mozgovoyandrey
