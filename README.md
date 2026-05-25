# InitPHP PerformanceMeter

A zero-dependency, single-class PHP profiler for measuring elapsed time and memory usage between named checkpoints.

[![CI](https://github.com/InitPHP/PerformanceMeter/actions/workflows/ci.yml/badge.svg)](https://github.com/InitPHP/PerformanceMeter/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/initphp/performance-meter/v)](https://packagist.org/packages/initphp/performance-meter)
[![Total Downloads](https://poser.pugx.org/initphp/performance-meter/downloads)](https://packagist.org/packages/initphp/performance-meter)
[![License](https://poser.pugx.org/initphp/performance-meter/license)](https://packagist.org/packages/initphp/performance-meter)
[![PHP Version Require](https://poser.pugx.org/initphp/performance-meter/require/php)](https://packagist.org/packages/initphp/performance-meter)

## Positioning

PerformanceMeter is intentionally minimal — a single `final` class with a handful of static methods that works without any other dependency. It exists to fill a specific niche:

> Quick, single-file timing checks where pulling in a full profiling library would be overkill.

It is **not** a replacement for full-featured profilers; it is the cheapest possible thing that lets you answer *"how long did this block take and how much memory did it use?"*.

### When to use this

- One-off benchmarking scripts and microbenchmarks
- CLI tools and cron jobs where you want a quick elapsed-time print at the end
- Tutorial / educational code where introducing a heavier dependency would obscure the lesson
- Library examples and reproduction scripts in bug reports
- Hot-path probes during local development, when adding a `composer require` round-trip is friction

### When NOT to use this

| Need | Use instead |
|---|---|
| Application-level profiling with nested sections, periods, categories | [`symfony/stopwatch`](https://github.com/symfony/stopwatch) |
| Production profiling, flame graphs, call tree analysis | [Xdebug profiler](https://xdebug.org/docs/profiler), [Blackfire](https://blackfire.io), [Tideways](https://tideways.com), [SPX](https://github.com/NoiseByNorthwest/php-spx) |
| Web request profiling with timeline UI | Symfony's WebProfilerBundle, Laravel Telescope, Clockwork |
| Memory leak hunting with object retention graphs | Xdebug + `xdebug_debug_zval`, or `memprof` |
| OpenTelemetry / APM integration | [`open-telemetry/sdk`](https://github.com/open-telemetry/opentelemetry-php) and an APM vendor SDK |

If you are reaching for any of the above, this package is not the right tool — and that is by design.

## Requirements

- PHP **8.1** or higher
- No runtime dependencies

## Installation

```bash
composer require initphp/performance-meter
```

You can also include `src/PerformanceMeter.php` (and `src/Exception/PointerNotFoundException.php`) manually if you cannot use Composer — the package has no transitive dependencies.

## Quick start

```php
require_once 'vendor/autoload.php';

use InitPHP\PerformanceMeter\PerformanceMeter;

PerformanceMeter::setPointer('main');

for ($i = 0; $i <= 1000; $i++) {
    usleep(10);
}

PerformanceMeter::setPointer('mainEnd');

echo PerformanceMeter::elapsedTime('main', 'mainEnd', 3) . ' seconds elapsed' . PHP_EOL;
echo PerformanceMeter::memoryUsage('main', 'mainEnd', 2) . ' memory used' . PHP_EOL;

// Example output:
// 0.015 seconds elapsed
// 0.77KB memory used
```

### Open-ended measurement

When you only pass a starting checkpoint, the second argument defaults to "now":

```php
PerformanceMeter::setPointer('boot');

// ... do work ...

echo PerformanceMeter::elapsedTime('boot') . ' seconds since boot' . PHP_EOL;
```

### `mark()` alias

`mark($name)` is a one-to-one alias of `setPointer($name)` for readers who prefer stopwatch-style vocabulary:

```php
PerformanceMeter::mark('before');
heavy_work();
PerformanceMeter::mark('after');

echo PerformanceMeter::elapsedTime('before', 'after');
```

More usage patterns — peak memory, comparing two implementations, resetting between runs — live in [`docs/cookbook.md`](docs/cookbook.md).

## API at a glance

| Method | Purpose |
|---|---|
| `setPointer(string $name): void` | Record a checkpoint with the current time + memory. Case-insensitive. |
| `mark(string $name): void` | Alias of `setPointer()`. |
| `elapsedTime(string $start, ?string $end = null, int $decimal = 4): float` | Seconds between two checkpoints. `$end = null` ⇒ "now". |
| `memoryUsage(string $start, ?string $end = null, int $decimal = 2, bool $realUsage = false): string` | Memory delta, formatted as `"x.xxKB"` or `"x.xxMB"`. |
| `peakMemoryUsage(int $decimal = 2, bool $realUsage = false): string` | Peak memory used so far by the process. |
| `has(string $name): bool` | Whether a checkpoint with that name has been recorded. |
| `getPointers(): array` | Snapshot copy of every recorded checkpoint. |
| `reset(): void` | Clear all checkpoints. |

`elapsedTime()` and `memoryUsage()` **throw** `InitPHP\PerformanceMeter\Exception\PointerNotFoundException` when `$start` (or a non-null `$end`) does not match a recorded checkpoint.

Full reference with parameter notes, error conditions and runnable examples: [`docs/api-reference.md`](docs/api-reference.md).

## Documentation

- [`docs/getting-started.md`](docs/getting-started.md) — install, first measurement, conceptual model
- [`docs/api-reference.md`](docs/api-reference.md) — every public method, parameter by parameter
- [`docs/cookbook.md`](docs/cookbook.md) — real-world recipes (CLI benchmarks, cron timing, A/B comparisons, peak memory tracking, v1 → v2 migration)

## Migrating from v1.x to v2.0

v2.0 is a clean break that fixes real bugs and tightens the API. Most callers only need to upgrade PHP.

| Area | v1 behaviour | v2 behaviour | Action |
|---|---|---|---|
| PHP requirement | `>=7.4` | `^8.1` | Upgrade your runtime. |
| Missing `$startPoint` | Silently returned ~0 (`"now" – "now"`) | Throws `PointerNotFoundException` | Wrap in `try/catch` or call `PerformanceMeter::has()` first. |
| Missing non-null `$endPoint` | Silently fell back to "now" | Throws `PointerNotFoundException` | Same — fix the typo or check with `has()`. |
| `memoryUsage()` with a freed-memory delta ≥ 1 MB | Reported in `KB` (broken) | Reports correctly in `MB` with sign | No code change; output now matches expectations. |
| `decimal < 0` | Accepted, produced odd output | Throws `InvalidArgumentException` | Pass `decimal >= 0`. |
| Subclassing `PerformanceMeter` | Allowed (pointless — all-static) | Blocked (`final`) | Compose, do not inherit. |
| `protected static $pointers` | Visible to subclasses | `private` | Use `getPointers()` / `has()` / `reset()`. |
| New: `reset()`, `has()`, `peakMemoryUsage()`, `getPointers()` | — | Added | Opt-in. |

A migration cookbook entry with side-by-side diffs lives in [`docs/cookbook.md`](docs/cookbook.md#v1--v2-migration).

## Contributing

This package follows the org-wide [InitPHP contribution guide](https://github.com/InitPHP/.github/blob/main/CONTRIBUTING.md) — PSR-12, `declare(strict_types=1);`, PHPStan at the configured level, PHPUnit-tested behaviour changes, Conventional Commits.

Locally:

```bash
composer install
composer test         # PHPUnit
composer phpstan      # static analysis
composer cs-check     # coding standards (use cs-fix to apply)
composer qa           # all of the above
```

CI runs on PHP 8.1 → 8.4 against both highest and lowest installable dependencies.

## Security

Please report security issues privately — see the org-wide [SECURITY.md](https://github.com/InitPHP/.github/blob/main/SECURITY.md). Do not open public issues for vulnerabilities.

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) — &lt;info@muhammetsafak.com.tr&gt;

## License

Released under the [MIT License](./LICENSE). Copyright © 2022-2026 InitPHP.
