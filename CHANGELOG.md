## [13.10.0](https://github.com/yajra/laravel-oci8/compare/v13.9.0...v13.10.0) (2026-07-17)

## [13.9.0](https://github.com/yajra/laravel-oci8/compare/v13.8.0...v13.9.0) (2026-07-17)

# [13.8.0](https://github.com/yajra/laravel-oci8/compare/v13.7.0...v13.8.0) (2026-07-12)


### Features

* support group limits in Oracle queries ([97c5cc3](https://github.com/yajra/laravel-oci8/commit/97c5cc3c9785b376caf18735496fd4ed7c7872b8))

# [13.7.0](https://github.com/yajra/laravel-oci8/compare/v13.6.0...v13.7.0) (2026-06-15)


### Bug Fixes

* strip qualifiers from Oracle update columns ([c9f0689](https://github.com/yajra/laravel-oci8/commit/c9f0689b2ee0f04fce9a88a136d513c200235810))


### Features

* implement whereNullSafeEquals ([843e8df](https://github.com/yajra/laravel-oci8/commit/843e8dfa3df69c103c58956f7e4920d47296f3b6))
* support wiping Oracle views and types ([4aa5397](https://github.com/yajra/laravel-oci8/commit/4aa5397048f9858ac9207b4443bd238becacf147))

# [13.6.0](https://github.com/yajra/laravel-oci8/compare/v13.5.1...v13.6.0) (2026-06-11)


### Bug Fixes

* union limit/offset ([9160c60](https://github.com/yajra/laravel-oci8/commit/9160c60d2e63f7ad7e9636fc36d0deee0c95fcda))


### Features

* add json update support for 19c and above ([416b731](https://github.com/yajra/laravel-oci8/commit/416b731c5483b141b4c2f7cc76d6055def2ad9a9))

## [13.5.1](https://github.com/yajra/laravel-oci8/compare/v13.5.0...v13.5.1) (2026-05-26)


### Bug Fixes

* whereIntegerNotInRaw not working above 1000, chunking uses "or" instead of "and" ([559ff19](https://github.com/yajra/laravel-oci8/commit/559ff19d1feab12f25e4d6ff03c454ada8f57eaa))

# [13.5.0](https://github.com/yajra/laravel-oci8/compare/v13.4.1...v13.5.0) (2026-05-26)


### Bug Fixes

* pint :robot: ([e1f6b2d](https://github.com/yajra/laravel-oci8/commit/e1f6b2da2a11b2717476aba30fe40ba3d655712b))


### Features

* improve column introspection ([f8d3743](https://github.com/yajra/laravel-oci8/commit/f8d3743a827aa3a4346b3d3a31fc76c77bb66fe8))

## [13.4.1](https://github.com/yajra/laravel-oci8/compare/v13.4.0...v13.4.1) (2026-05-16)


### Bug Fixes

* limit index length to 30 to prevent errors on oracle 11g and below ([25f3cea](https://github.com/yajra/laravel-oci8/commit/25f3ceae05d168b5debb83090d4bc504867c6beb))

# [13.4.0](https://github.com/yajra/laravel-oci8/compare/v13.3.0...v13.4.0) (2026-05-14)


### Features

* add timeout options for Oracle connection configuration ([d76d1bc](https://github.com/yajra/laravel-oci8/commit/d76d1bc3f07b03de18ab6ce96ed7618686abe091))

# [13.3.0](https://github.com/yajra/laravel-oci8/compare/v13.2.1...v13.3.0) (2026-04-27)


### Bug Fixes

* pint :robot: ([f9edd16](https://github.com/yajra/laravel-oci8/commit/f9edd16887f548f69141a0b9d8ff3af9fcb4141b))
* scope badges to master branch ([37b13af](https://github.com/yajra/laravel-oci8/commit/37b13afb73c9174916551c0719dca022684ee8ad))


### Features

* add support for joinLateral ([6b45a47](https://github.com/yajra/laravel-oci8/commit/6b45a47b251a712c32cb2ccd559028989734583b))

## [13.2.1](https://github.com/yajra/laravel-oci8/compare/v13.2.0...v13.2.1) (2026-04-13)


### Bug Fixes

* compileDropAllTables for identity columns ([239001b](https://github.com/yajra/laravel-oci8/commit/239001b2b4d0bc5a7b780aa78e6326d0b89793d7))
* pint :robot: ([abccdf3](https://github.com/yajra/laravel-oci8/commit/abccdf3f7b4ddad62b1ef924e7334b3c5b259c09))

# [13.2.0](https://github.com/yajra/laravel-oci8/compare/v13.1.0...v13.2.0) (2026-04-07)


### Features

* implement compileThreadCount ([9cb4f03](https://github.com/yajra/laravel-oci8/commit/9cb4f0320f83d1a1639dd3d046b5713c9e0c8786))
* use native json type for Oracle 21c and above ([eb039ce](https://github.com/yajra/laravel-oci8/commit/eb039cee9981962f9c969efc12507c6e8232e86f))

# [13.1.0](https://github.com/yajra/laravel-oci8/compare/v13.0.0...v13.1.0) (2026-04-07)


### Bug Fixes

* move binary_ci in whereLike to 12cR2 version ([8f41811](https://github.com/yajra/laravel-oci8/commit/8f41811ae6b6faa12dd6e3f4cef3d32d3110c0db))
* pint :robot: ([09a8d0a](https://github.com/yajra/laravel-oci8/commit/09a8d0a3846909daf58e344d603eca7ddb648230))
* table comment generation in SchemaBuilder ([f0057e1](https://github.com/yajra/laravel-oci8/commit/f0057e108518bffb5d2dd81ca376aaf82e808e99))
* typo in OracleGrammar comment retrieval ([13e6838](https://github.com/yajra/laravel-oci8/commit/13e683854bbd3d7b33a39814ac49ffd3e4763c56))


### Features

* add context7 configuration file ([73560ae](https://github.com/yajra/laravel-oci8/commit/73560ae48ebf7bb5418eb8317febbff039a4f787))
* dependency-aware join reorder ([e609a08](https://github.com/yajra/laravel-oci8/commit/e609a08302ccc467b7b4078d1dd51e6577e749f0))
* implement index renaming in OracleGrammar ([5f5d45d](https://github.com/yajra/laravel-oci8/commit/5f5d45d6a346affbd957bd36379b2fc9581cbcd5))
* update larastan extension ([b6c3d1f](https://github.com/yajra/laravel-oci8/commit/b6c3d1f033972b4738672d560423fa68ccb16932))

## v13.0.0 - 2026-03-18

- Laravel 13 support
