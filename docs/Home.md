# InitPHP PerformanceMeter — Wiki

Welcome to the official documentation for **`initphp/performance-meter`** — a zero-dependency, single-class PHP profiler for measuring elapsed time and memory usage between named checkpoints.

The entire public surface is two types:

| Type | Purpose |
| --- | --- |
| [`PerformanceMeter`](API-Reference.md) | Static class. Records named checkpoints and computes time / memory deltas between them. |
| [`PointerNotFoundException`](Exceptions.md) | Thrown when you reference a checkpoint name that was never recorded. |

```bash
composer require initphp/performance-meter
```

```php
use InitPHP\PerformanceMeter\PerformanceMeter;

PerformanceMeter::setPointer('start');

usleep(50_000);

PerformanceMeter::setPointer('end');

echo PerformanceMeter::elapsedTime('start', 'end'), " seconds\n";
echo PerformanceMeter::memoryUsage('start', 'end'),  " delta\n";
```

## What this package is

A tool for **probe-style measurements** in small PHP programs. Drop a `setPointer()` call into your code, drop another later, ask for the delta. Nothing more.

It is **intentionally minimal**: one class, seven static methods (plus one alias), no configuration, no instances, no dependencies. The intended audience is people writing one-off benchmarks, CLI tools, cron jobs, library examples, and reproducer scripts — places where the friction of installing and learning a "real" profiler is the whole reason the measurement never gets added.

## What this package is not

It is **not** an application profiler. It does not draw flame graphs, doesn't integrate with APM vendors, doesn't track nested sections, periods, or categories, and doesn't isolate measurements per request or per scope. The single static registry that powers the API is a deliberate trade-off: it keeps probe code to a single line at the cost of giving every measurement the same global namespace.

See [Use Cases & Comparison](Use-Cases.md) for a side-by-side with `symfony/stopwatch`, Xdebug, Blackfire, Tideways, SPX, OpenTelemetry, and friends — and clear pointers on when this package is the wrong tool.

## Start here

- **New to the package?** Read [Installation](Installation.md), then [Quick Start](Quick-Start.md).
- **Want the mental model?** Read [Concepts](Concepts.md). It is short and explains every surprise you might hit.
- **Looking for a specific method's behaviour?** [API Reference](API-Reference.md) lists every method with parameters, return types, and edge cases.
- **Want copy-pasteable examples?** [Recipes](Recipes.md) collects real-world patterns.
- **Upgrading from v1.x?** [Migration Guide](Migration-Guide.md) walks through every behavioural change.

## Status

- Requires **PHP 8.2+**
- Zero runtime dependencies
- 100 % line / method / class test coverage
- CI runs on PHP 8.2 → 8.6 with the highest installable dependencies, plus a PHP 8.2 run with the lowest
- Static analysis: PHPStan `level: max`, clean
- Coding standard: PER-CS via PHP-CS-Fixer, enforced in CI

## Quick links

- [Source on GitHub](https://github.com/InitPHP/PerformanceMeter)
- [Packagist](https://packagist.org/packages/initphp/performance-meter)
- [Changelog](https://github.com/InitPHP/PerformanceMeter/blob/main/CHANGELOG.md)
- [Issues](https://github.com/InitPHP/PerformanceMeter/issues)
- [Discussions](https://github.com/orgs/InitPHP/discussions)
