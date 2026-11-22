# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed (breaking)

- **Requires PHP `^8.2`.** Support for PHP 8.1 is dropped; CI now runs PHP 8.2, 8.3, 8.4 and 8.5.
- **Coding standard moved to PER-CS** through the shared `.php-cs-fixer.dist.php`; the whole code base was reformatted.

## [2.0.0]

### Added

- `PerformanceMeter::reset()` — clear every recorded checkpoint.
- `PerformanceMeter::has(string $name): bool` — check whether a checkpoint exists.
- `PerformanceMeter::peakMemoryUsage(int $decimal = 2, bool $realUsage = false): string` — report the process-wide peak memory.
- `PerformanceMeter::getPointers(): array` — snapshot copy of the registry.
- `memoryUsage()` gained an optional `bool $realUsage = false` parameter to switch between the emalloc-tracked and the system-allocated reading.
- `setPointer()` now captures both memory readings (`memory_get_usage(false)` and `memory_get_usage(true)`) so historical pointers can be queried either way.
- Dedicated exception type `InitPHP\PerformanceMeter\Exception\PointerNotFoundException` (extends `\InvalidArgumentException`).
- Comprehensive English documentation in `docs/`: `getting-started.md`, `api-reference.md`, `cookbook.md`.
- CI workflow (`.github/workflows/ci.yml`) covering PHP 8.1 → 8.4, PHPStan at `level: max`, and PHP-CS-Fixer enforcement.
- PHPUnit 10 test suite with 100% line, method, and class coverage.

### Changed

- **BREAKING:** Minimum PHP version raised from `>=7.4` to `^8.1`.
- **BREAKING:** Referencing a checkpoint that does not exist now throws `PointerNotFoundException` instead of silently falling back to "now". Affects both the `$startPoint` and a non-`null` `$endPoint` arguments of `elapsedTime()` and `memoryUsage()`.
- **BREAKING:** `memoryUsage()` no longer mis-reports freed memory ≥ 1 MB in KB. Negative deltas are now formatted in the correct unit with a leading `-`.
- **BREAKING:** Negative `$decimal` arguments now throw `\InvalidArgumentException`.
- **BREAKING:** The class is now `final`; `$pointers` is now `private`.
- All PHPDoc rewritten in English (org-wide convention).
- `composer.json` switched from `files` autoload to PSR-4; added `require-dev`, scripts (`test`, `phpstan`, `cs-check`, `cs-fix`, `qa`), keywords and support metadata.

### Fixed

- `memoryUsage()` returned a KB-formatted figure for deltas with `|delta| ≥ 1 MB` whenever the delta was negative (memory freed). The unit selection now uses `abs($delta)` and produces, e.g., `-3.00MB` instead of `-3072KB`.
- PHPDoc `@see PerformansMeter::setPointer()` typo on `mark()` corrected to a valid reference.
- `microtime()` parsing replaced with `microtime(true)`, removing an unnecessary string round-trip.

## [1.0]

Initial public release.

[Unreleased]: https://github.com/InitPHP/PerformanceMeter/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/InitPHP/PerformanceMeter/releases/tag/v2.0.0
[1.0]: https://github.com/InitPHP/PerformanceMeter/releases/tag/v1.0
