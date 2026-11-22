# Installation

PerformanceMeter is distributed on [Packagist](https://packagist.org/packages/initphp/performance-meter) as `initphp/performance-meter` and follows [Semantic Versioning](https://semver.org/).

## Requirements

| Requirement | Version |
| --- | --- |
| PHP | **8.2** or higher |
| Composer | Optional (manual include is supported) |
| Extensions | None |

The package has **no runtime dependencies** — installing it adds exactly two PHP files to your autoloader.

## With Composer (recommended)

```bash
composer require initphp/performance-meter
```

Then in your application:

```php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\PerformanceMeter\PerformanceMeter;
```

To pin the major version explicitly:

```bash
composer require initphp/performance-meter:^2.0
```

## Without Composer

Because the package is two files in one namespace, manual inclusion works:

```php
require __DIR__ . '/path/to/src/Exception/PointerNotFoundException.php';
require __DIR__ . '/path/to/src/PerformanceMeter.php';

use InitPHP\PerformanceMeter\PerformanceMeter;
```

Order matters only if you intend to catch the exception — `PerformanceMeter.php` references the exception class but does not eagerly load it.

## Verifying the installation

A one-line smoke test:

```php
echo InitPHP\PerformanceMeter\PerformanceMeter::peakMemoryUsage();
```

If you see something like `2.50MB`, the package is wired up correctly.

## Updating

```bash
composer update initphp/performance-meter
```

Read the [Changelog](https://github.com/InitPHP/PerformanceMeter/blob/main/CHANGELOG.md) before crossing a major version — v1 → v2 has [intentional breaking changes](Migration-Guide.md).

## Removing

```bash
composer remove initphp/performance-meter
```

There is no global state, configuration file, cache, or DB schema to clean up. The package is purely in-process.

## Next

- [Quick Start](Quick-Start.md) — your first measurement in two minutes.
- [Concepts](Concepts.md) — the small handful of ideas the rest of the wiki builds on.
