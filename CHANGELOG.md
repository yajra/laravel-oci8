# Laravel-OCI8 CHANGELOG

## [Unreleased]

## [v8.2.3] - 2021-01-06

- Quote column name "id" so as to not affected by PDO::ATTR_CASE [#623]

## [v8.2.2] - 2020-12-08

- Query builder fixes and tests. [#615]

## [v8.2.1] - 2020-12-07

- Fix query builder bulk insert. [#612]
- Fix [#558].

## [v8.2.0] - 2020-12-06

- Improve pagination performance. [#611]
- Fixes [#563]

## [v8.1.3] - 2020-12-06

- Fix Model::create() with guarded property. [#609]
- Fix [#596]

## [v8.1.2] - 2020-12-06

- Fix database presence verifier. [#607]
- Revert [#598]
- Fixes [#601], [#602]
- Use orchestra testbench for tests

## [v8.1.1] - 2020-11-21

- Implement case insensitive function-based unique index. [#599]

## [v8.1.0] - 2020-11-20

- Enable oracle case insensitive searches. [#598]
- Fix database presence validation issue (unique, exists, etc).
- Removes the dependency on OracleUserProvider.

## [v8.0.1] - 2020-09-23

- Fix [#590] WhereIn query with more than 2k++ records. [#591], credits to [@bioleyl].

## [v8.0.0] - 2020-09-09

- Add support for Laravel 8.

## [v7.0.1] - 2020-06-18

- Fix pagination aggregate count. [#570]

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

[Unreleased]: https://github.com/yajra/laravel-oci8/compare/v8.2.3...8.x
[v8.2.3]: https://github.com/yajra/laravel-oci8/compare/v8.2.2...v8.2.3
[v8.2.2]: https://github.com/yajra/laravel-oci8/compare/v8.2.1...v8.2.2
[v8.2.1]: https://github.com/yajra/laravel-oci8/compare/v8.2.0...v8.2.1
[v8.2.0]: https://github.com/yajra/laravel-oci8/compare/v8.1.3...v8.2.0
[v8.1.3]: https://github.com/yajra/laravel-oci8/compare/v8.1.2...v8.1.3
[v8.1.2]: https://github.com/yajra/laravel-oci8/compare/v8.1.1...v8.1.2
[v8.1.1]: https://github.com/yajra/laravel-oci8/compare/v8.1.0...v8.1.1
[v8.1.0]: https://github.com/yajra/laravel-oci8/compare/v8.0.0...v8.1.0
[v8.0.1]: https://github.com/yajra/laravel-oci8/compare/v8.0.0...v8.0.1
[v8.0.0]: https://github.com/yajra/laravel-oci8/compare/v7.0.1...v8.0.0
[v7.0.1]: https://github.com/yajra/laravel-oci8/compare/v7.0.0...v7.0.1
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
[#570]: https://github.com/yajra/laravel-oci8/pull/570
[#591]: https://github.com/yajra/laravel-oci8/pull/591
[#598]: https://github.com/yajra/laravel-oci8/pull/598
[#599]: https://github.com/yajra/laravel-oci8/pull/599
[#607]: https://github.com/yajra/laravel-oci8/pull/607
[#609]: https://github.com/yajra/laravel-oci8/pull/609
[#611]: https://github.com/yajra/laravel-oci8/pull/611
[#612]: https://github.com/yajra/laravel-oci8/pull/612
[#615]: https://github.com/yajra/laravel-oci8/pull/615
[#623]: https://github.com/yajra/laravel-oci8/pull/623

[#558]: https://github.com/yajra/laravel-oci8/issue/558
[#563]: https://github.com/yajra/laravel-oci8/issue/563
[#596]: https://github.com/yajra/laravel-oci8/issue/596
[#602]: https://github.com/yajra/laravel-oci8/issue/602
[#601]: https://github.com/yajra/laravel-oci8/issue/601
[#590]: https://github.com/yajra/laravel-oci8/issue/590
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
[@bioleyl]: https://github.com/bioleyl
