# Cookbook

Practical recipes for using `PerformanceMeter` in real codebases. Each recipe is self-contained and copy-pasteable.

## Index

- [CLI benchmark script with summary at the end](#cli-benchmark-script-with-summary-at-the-end)
- [Cron job timing log](#cron-job-timing-log)
- [A/B comparison of two implementations](#ab-comparison-of-two-implementations)
- [Peak memory tracking](#peak-memory-tracking)
- [Nested measurements inside a loop](#nested-measurements-inside-a-loop)
- [Conditional measurement (probe-or-skip)](#conditional-measurement-probe-or-skip)
- [v1 → v2 migration](#v1--v2-migration)

---

## CLI benchmark script with summary at the end

A small `bench.php` you can drop next to a workload and run with `php bench.php`.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use InitPHP\PerformanceMeter\PerformanceMeter;

PerformanceMeter::setPointer('boot');

// --- workload -----------------------------------------------------------
$rows = [];
for ($i = 0; $i < 100_000; $i++) {
    $rows[] = ['id' => $i, 'hash' => hash('sha256', (string) $i)];
}
// ------------------------------------------------------------------------

PerformanceMeter::setPointer('done');

printf(
    "rows: %d\nelapsed: %.4fs\nmemory: %s\npeak: %s\n",
    count($rows),
    PerformanceMeter::elapsedTime('boot', 'done'),
    PerformanceMeter::memoryUsage('boot', 'done'),
    PerformanceMeter::peakMemoryUsage(),
);
```

Sample output:

```
rows: 100000
elapsed: 0.1843s
memory: 19.84MB
peak: 21.12MB
```

---

## Cron job timing log

Useful when you want a single line per run, appended to a log file, telling you how long the job took and how much memory it touched. Pair with `logrotate` and you have a low-fi performance trend.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use InitPHP\PerformanceMeter\PerformanceMeter;

PerformanceMeter::setPointer('boot');

$processed = run_nightly_export();

file_put_contents(
    __DIR__ . '/var/log/nightly-export.log',
    sprintf(
        "[%s] rows=%d elapsed=%ss peak=%s\n",
        date(DATE_ATOM),
        $processed,
        PerformanceMeter::elapsedTime('boot'),
        PerformanceMeter::peakMemoryUsage(),
    ),
    FILE_APPEND,
);
```

---

## A/B comparison of two implementations

Compare two functions on the same input, reset between runs so they do not pollute each other's measurements.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use InitPHP\PerformanceMeter\PerformanceMeter;

$payload = range(1, 1_000_000);

$candidates = [
    'array_sum'  => static fn () => array_sum($payload),
    'foreach'    => static function () use ($payload) {
        $total = 0;
        foreach ($payload as $n) {
            $total += $n;
        }
        return $total;
    },
];

foreach ($candidates as $name => $fn) {
    PerformanceMeter::reset();
    PerformanceMeter::setPointer('start');
    $fn();
    PerformanceMeter::setPointer('end');

    printf(
        "%-10s elapsed=%ss memory=%s\n",
        $name,
        PerformanceMeter::elapsedTime('start', 'end', 6),
        PerformanceMeter::memoryUsage('start', 'end'),
    );
}
```

> **Microbenchmark caveat.** A single-shot measurement is noisy. Wrap each scenario in a `for ($i = 0; $i < $iterations; $i++)` loop and divide if you need stable numbers. For statistical rigour (warm-up, outliers, confidence intervals), reach for [`phpbench/phpbench`](https://github.com/phpbench/phpbench) instead.

---

## Peak memory tracking

`peakMemoryUsage()` does not need start/end checkpoints — it asks PHP for the watermark since the process began (or since the last `memory_reset_peak_usage()` call, on PHP 8.2+).

```php
<?php

use InitPHP\PerformanceMeter\PerformanceMeter;

import_large_dataset();
echo 'After import:    ', PerformanceMeter::peakMemoryUsage(), PHP_EOL;

run_transformations();
echo 'After transform: ', PerformanceMeter::peakMemoryUsage(), PHP_EOL;

flush_to_disk();
echo 'After flush:     ', PerformanceMeter::peakMemoryUsage(), PHP_EOL;
```

Use the `realUsage` flag if you care about what the OS thinks PHP is holding, not just what the engine tracks:

```php
echo PerformanceMeter::peakMemoryUsage(2, true);
```

---

## Nested measurements inside a loop

Because every name lives in the same global registry, you typically want one checkpoint per iteration with a name that includes the index — otherwise iteration N overwrites iteration N-1.

```php
<?php

use InitPHP\PerformanceMeter\PerformanceMeter;

foreach ($batches as $index => $batch) {
    PerformanceMeter::setPointer("batch:$index:start");
    process($batch);
    PerformanceMeter::setPointer("batch:$index:end");
}

foreach (array_keys($batches) as $index) {
    printf(
        "batch %d: %ss\n",
        $index,
        PerformanceMeter::elapsedTime("batch:$index:start", "batch:$index:end"),
    );
}
```

If you do not need per-iteration breakdowns and just want a running total, collect the deltas as you go and discard the checkpoints:

```php
$total = 0.0;
foreach ($batches as $batch) {
    PerformanceMeter::setPointer('it-start');
    process($batch);
    PerformanceMeter::setPointer('it-end');

    $total += PerformanceMeter::elapsedTime('it-start', 'it-end', 6);
}
PerformanceMeter::reset(); // optional, to free the two slots
printf("Total processing time: %.6fs\n", $total);
```

---

## Conditional measurement (probe-or-skip)

Sometimes the first call site does not know whether an earlier code path already recorded a checkpoint. Guard with `has()` instead of catching the exception:

```php
<?php

use InitPHP\PerformanceMeter\PerformanceMeter;

if (!PerformanceMeter::has('boot')) {
    PerformanceMeter::setPointer('boot');
}

// later, anywhere in the request:
echo PerformanceMeter::elapsedTime('boot');
```

This pattern is especially useful for instrumenting library code that may be loaded multiple times in different orders.

---

## v1 → v2 migration

v2.0 keeps the surface area familiar but tightens behaviour. Most upgrades are a no-op once you are on PHP 8.1+.

### What still works unchanged

```php
PerformanceMeter::setPointer('start');
work();
PerformanceMeter::setPointer('end');

echo PerformanceMeter::elapsedTime('start', 'end', 3);
echo PerformanceMeter::memoryUsage('start', 'end');
echo PerformanceMeter::elapsedTime('start');           // open-ended, still works
PerformanceMeter::mark('checkpoint');                  // alias, still works
```

### What needs attention

**1. Typos in checkpoint names now throw.**

```diff
- // v1: silently returned ~0 and you spent an hour wondering why
- echo PerformanceMeter::elapsedTime('strat');
+ // v2: catch the typo at the call site
+ try {
+     echo PerformanceMeter::elapsedTime('start');
+ } catch (\InitPHP\PerformanceMeter\Exception\PointerNotFoundException $e) {
+     // fix the typo or fall back deliberately
+ }
```

If you intentionally want a "since first probe in this process" pattern, use `has()`:

```php
if (!PerformanceMeter::has('boot')) {
    PerformanceMeter::setPointer('boot');
}
```

**2. `memoryUsage()` formatting was buggy for freed memory.**

If your code asserts on the textual output:

```diff
- // v1: a 3 MB freed delta produced "-3072KB" (broken)
- assertSame('-3072KB', PerformanceMeter::memoryUsage('a', 'b'));
+ // v2: same input correctly produces "-3.00MB"
+ assertSame('-3.00MB', PerformanceMeter::memoryUsage('a', 'b'));
```

**3. Subclassing is no longer allowed.**

The v1 class was implicitly extensible; the v2 class is `final`. If you had a subclass purely to access `protected static $pointers`, switch to the public accessors:

```diff
- class MyMeter extends PerformanceMeter
- {
-     public static function dump(): array
-     {
-         return self::$pointers;
-     }
- }
- $snapshot = MyMeter::dump();
+ $snapshot = PerformanceMeter::getPointers();
```

**4. PHP 8.1 minimum.**

Bump your `composer.json` and CI matrix; the package's signatures and dependencies require it.

That is the entire migration. Run your test suite, fix the call sites the test suite flags, and you are done.
