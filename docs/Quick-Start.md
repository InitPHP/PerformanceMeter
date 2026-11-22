# Quick Start

This page takes you from a working install to a useful measurement in under five minutes.

## Step 1 — Mark two checkpoints

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use InitPHP\PerformanceMeter\PerformanceMeter;

PerformanceMeter::setPointer('start');

// the work you want to measure
usleep(50_000);

PerformanceMeter::setPointer('end');
```

`setPointer($name)` captures three things at the moment of the call:

- The current wall-clock time, via `microtime(true)`
- The emalloc-tracked memory usage, via `memory_get_usage(false)`
- The system-allocated memory usage, via `memory_get_usage(true)`

All three are stored in a single class-level registry, keyed by the lowercased name.

## Step 2 — Ask for the delta

```php
echo PerformanceMeter::elapsedTime('start', 'end'), " seconds\n";
echo PerformanceMeter::memoryUsage('start', 'end'),  " memory delta\n";
```

Output looks like:

```
0.0507 seconds
0.13KB memory delta
```

## Step 3 — Skip the "end" checkpoint when you do not need it

`elapsedTime()` and `memoryUsage()` both default their second argument to `null`. When it is `null`, the current moment is captured and used as the end of the interval:

```php
PerformanceMeter::setPointer('boot');

// ... whole script runs ...

echo PerformanceMeter::elapsedTime('boot'); // seconds since 'boot'
```

You only need an explicit end-checkpoint when you want to *stop* measuring before the script ends.

## Step 4 — Use `mark()` if you prefer stopwatch vocabulary

`mark($name)` is a one-to-one alias of `setPointer($name)`. Same state, same effect:

```php
PerformanceMeter::mark('before');
heavy_work();
PerformanceMeter::mark('after');

echo PerformanceMeter::elapsedTime('before', 'after');
```

## Step 5 — Be aware of the global registry

Every checkpoint lives in the same process-wide registry on the `PerformanceMeter` class. Two callers using the same name will overwrite each other:

```php
PerformanceMeter::setPointer('x');
sleep(1);
PerformanceMeter::setPointer('x'); // overwrites — the first capture is gone
```

When you run independent measurements one after another (e.g. a benchmark loop comparing two implementations), call `reset()` between them:

```php
foreach ($candidates as $name => $fn) {
    PerformanceMeter::reset();
    PerformanceMeter::setPointer('s');
    $fn();
    PerformanceMeter::setPointer('e');
    printf("%s: %ss\n", $name, PerformanceMeter::elapsedTime('s', 'e'));
}
```

## Step 6 — Know when an unknown name throws

If you ask for a checkpoint that was never recorded, you get a `PointerNotFoundException`:

```php
PerformanceMeter::elapsedTime('typo'); // throws PointerNotFoundException
```

This is deliberate — see [Concepts → Fail-fast on unknown names](Concepts.md#fail-fast-on-unknown-names) for the reasoning. If you legitimately want a "measure if present" pattern, gate the call with `has()`:

```php
if (PerformanceMeter::has('boot')) {
    echo PerformanceMeter::elapsedTime('boot');
}
```

## You now know enough

That is the entire model. The remaining wiki pages drill into specific cases:

- [Concepts](Concepts.md) — the mental model in one page (recommended next read).
- [API Reference](API-Reference.md) — every method's parameters, return value, throws.
- [Recipes](Recipes.md) — practical patterns: CLI benchmarks, cron timing, A/B comparison, peak memory tracking.
- [Use Cases & Comparison](Use-Cases.md) — when this package is and is not the right tool.
