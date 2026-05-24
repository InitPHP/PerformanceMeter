# PerformanceMeter

A zero-dependency, single-file, single-class PHP profiler for measuring elapsed time and memory usage between named checkpoints.

[![Latest Stable Version](http://poser.pugx.org/initphp/performancemeter/v)](https://packagist.org/packages/initphp/performancemeter) [![Total Downloads](http://poser.pugx.org/initphp/performancemeter/downloads)](https://packagist.org/packages/initphp/performancemeter) [![Latest Unstable Version](http://poser.pugx.org/initphp/performancemeter/v/unstable)](https://packagist.org/packages/initphp/performancemeter) [![License](http://poser.pugx.org/initphp/performancemeter/license)](https://packagist.org/packages/initphp/performancemeter) [![PHP Version Require](http://poser.pugx.org/initphp/performancemeter/require/php)](https://packagist.org/packages/initphp/performancemeter)

## Positioning

This package is intentionally minimal — three static methods (`setPointer`, `elapsedTime`, `memoryUsage`) that work without any other dependency. It exists to fill a specific niche:

> Quick, single-file timing checks where pulling in a full profiling library would be overkill.

It is **not** a replacement for full-featured profilers; it is the cheapest possible thing that lets you answer *"how long did this block take and how much memory did it use?"*.

### When to use this

- One-off benchmarking scripts and microbenchmarks
- CLI tools and cron jobs where you want a quick elapsed-time print at the end
- Tutorial / educational code where introducing a heavier dependency would obscure the lesson
- Library examples and reproduction scripts in bug reports
- Hot-path probes during local development, when adding a `composer require` round-trip is friction

### When NOT to use this

For anything that fits the description below, prefer a purpose-built tool:

| Need | Use instead |
|---|---|
| Application-level profiling with nested sections, periods, categories | [`symfony/stopwatch`](https://github.com/symfony/stopwatch) |
| Production profiling, flame graphs, call tree analysis | [Xdebug profiler](https://xdebug.org/docs/profiler), [Blackfire](https://blackfire.io), [Tideways](https://tideways.com), [SPX](https://github.com/NoiseByNorthwest/php-spx) |
| Web request profiling with timeline UI | Symfony's WebProfilerBundle, Laravel Telescope, Clockwork |
| Memory leak hunting with object retention graphs | Xdebug + `xdebug_debug_zval`, or `memprof` |
| OpenTelemetry / APM integration | [`open-telemetry/sdk`](https://github.com/open-telemetry/opentelemetry-php) and an APM vendor SDK |

If you are reaching for any of the above, this package is not the right tool — and that is by design.

## Requirements

- PHP 7.4 or higher
- No other dependencies

## Installation

```
composer require initphp/performance-meter
```

You can also include `src/PerformanceMeter.php` manually if you cannot use Composer — the package is a single file with no transitive dependencies.

## Usage

### Basic — elapsed time and memory between two named points

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

### Open-ended measurement — measure from a point up to "right now"

If you only pass a single pointer name, the second argument defaults to the current moment:

```php
PerformanceMeter::setPointer('boot');

// ... do work ...

echo PerformanceMeter::elapsedTime('boot') . ' seconds since boot' . PHP_EOL;
```

### `mark()` alias

`mark($name)` is an alias for `setPointer($name)` for readers who prefer that vocabulary:

```php
PerformanceMeter::mark('before');
heavy_work();
PerformanceMeter::mark('after');

echo PerformanceMeter::elapsedTime('before', 'after');
```

## API Reference

| Method | Description |
|---|---|
| `setPointer(string $name): void` | Record a checkpoint with the current `microtime()` and `memory_get_usage()`. Names are case-insensitive (stored lowercased). |
| `mark(string $name): void` | Alias of `setPointer()`. |
| `elapsedTime(string $startPoint, ?string $endPoint = null, int $decimal = 4): float` | Seconds between two pointers. If `$endPoint` is `null`, "now" is used. |
| `memoryUsage(string $startPoint, ?string $endPoint = null, int $decimal = 2): string` | Memory delta between two pointers, formatted as `"x.xxKB"` or `"x.xxMB"`. If `$endPoint` is `null`, "now" is used. |

Pointers are stored statically on the class, so all measurements share the same global registry within a process. This is intentional — it keeps the API as terse as possible for the use cases above. If you need isolated, instance-scoped measurement scopes, use `symfony/stopwatch`.

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)
