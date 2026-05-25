# Getting Started

This guide walks you from `composer require` to your first measurement and explains the small handful of concepts the rest of the documentation builds on. It should take five minutes end to end.

## Install

```bash
composer require initphp/performance-meter
```

The package has no runtime dependencies and adds a single class (`InitPHP\PerformanceMeter\PerformanceMeter`) plus one exception (`InitPHP\PerformanceMeter\Exception\PointerNotFoundException`) to your autoloader. Composer is not strictly required — you can include the two source files directly if you need to.

PHP 8.1 or higher is required.

## Your first measurement

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use InitPHP\PerformanceMeter\PerformanceMeter;

PerformanceMeter::setPointer('start');

usleep(50_000); // simulate 50ms of work

PerformanceMeter::setPointer('end');

echo PerformanceMeter::elapsedTime('start', 'end', 4), " seconds\n";
echo PerformanceMeter::memoryUsage('start', 'end'),     " of memory delta\n";
```

A typical run prints something like:

```
0.0507 seconds
0.13KB of memory delta
```

That is the entire model: name checkpoints with `setPointer()`, then ask the class for the time or memory delta between any two of them.

## Concepts

### Checkpoints live in a single static registry

Every call to `setPointer($name)` writes into one process-wide registry on the `PerformanceMeter` class. There is no instance to construct, no scope to manage. This is intentional — it keeps probe code to a single line — but it also means **all callers in the same process share the same namespace of pointer names**.

If you are running a long-lived worker or test suite where measurements must not bleed across runs, call `PerformanceMeter::reset()` between them.

### Names are case-insensitive

```php
PerformanceMeter::setPointer('Boot');
PerformanceMeter::has('boot'); // true
PerformanceMeter::has('BOOT'); // true
```

Internally, names are lowercased before being stored or looked up. Setting `'Foo'` and then `'foo'` overwrites the same entry.

### Open-ended measurements default to "now"

`elapsedTime()` and `memoryUsage()` both accept `null` as the end-point argument. When the end-point is `null`, the current moment is captured and used:

```php
PerformanceMeter::setPointer('boot');

// ... whole request lifecycle ...

echo PerformanceMeter::elapsedTime('boot'); // seconds from 'boot' until now
```

You only need to mark an explicit end-point when you want to *stop* measuring before the script ends.

### Missing checkpoints throw

If you reference a checkpoint that has not been recorded, you get a `PointerNotFoundException` (which extends `InvalidArgumentException`). This is a deliberate fail-fast: silent fallback to "now" — the v1 behaviour — turned typos into silent zero-duration measurements that were painful to diagnose.

```php
PerformanceMeter::setPointer('start');

try {
    PerformanceMeter::elapsedTime('strat'); // typo
} catch (\InitPHP\PerformanceMeter\Exception\PointerNotFoundException $e) {
    // handle or rethrow
}
```

If you genuinely want a "measure if it exists" pattern, gate the call with `PerformanceMeter::has()`.

### Memory delta formatting

`memoryUsage()` returns a string ending in `KB` or `MB`, picked from the *absolute* size of the delta. Negative deltas — memory that was freed between two checkpoints — are reported with a leading `-`:

```php
$payload = str_repeat('x', 3 * 1024 * 1024); // ~3MB
PerformanceMeter::setPointer('before');
unset($payload);
PerformanceMeter::setPointer('after');

echo PerformanceMeter::memoryUsage('before', 'after'); // e.g. "-3.00MB"
```

### Real vs emalloc memory

PHP exposes two memory readings: the emalloc-tracked figure (default) and the system-allocated figure (`memory_get_usage(true)`). `setPointer()` captures **both**, and `memoryUsage()` / `peakMemoryUsage()` accept a `bool $realUsage` flag to choose which one to report. Default is the emalloc figure, which is what you almost always want for application-level measurements.

## Next steps

- The full method reference, including every parameter and error condition, lives in [`api-reference.md`](api-reference.md).
- Patterns for benchmarking, CLI timing logs, A/B comparison, peak memory tracking, and migrating from v1 are collected in [`cookbook.md`](cookbook.md).
