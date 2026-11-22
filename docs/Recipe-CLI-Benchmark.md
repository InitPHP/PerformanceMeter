# Recipe — CLI Benchmark Script

A drop-in `bench.php` you can place next to a workload and run with `php bench.php`. Prints elapsed time, memory delta, and peak memory in a one-shot summary at the end.

## The script

```php
<?php

declare(strict_types=1);

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
    "rows=%d elapsed=%.4fs memory=%s peak=%s\n",
    count($rows),
    PerformanceMeter::elapsedTime('boot', 'done'),
    PerformanceMeter::memoryUsage('boot', 'done'),
    PerformanceMeter::peakMemoryUsage(),
);
```

Sample output:

```
rows=100000 elapsed=0.1843s memory=19.84MB peak=21.12MB
```

## Why this shape

- **Two checkpoints, not three.** `'boot'` and `'done'` bracket the entire workload. If you want sub-phase breakdowns, add more checkpoints — see [Loop Profiling](#variant--phase-by-phase-breakdown) below.
- **`peakMemoryUsage()` does not need checkpoints.** It is a single, stateless call that reads PHP's high-water mark. Useful for spotting transient allocations that come and go before you can measure them with a delta.
- **One `printf` line.** Easy to `grep`, easy to feed into shell pipelines, easy to compare between runs.

## Variant — phase-by-phase breakdown

If the workload has distinct phases you want to measure separately:

```php
PerformanceMeter::setPointer('boot');

PerformanceMeter::setPointer('load:start');
$rows = load_rows();
PerformanceMeter::setPointer('load:end');

PerformanceMeter::setPointer('transform:start');
$transformed = array_map($transform, $rows);
PerformanceMeter::setPointer('transform:end');

PerformanceMeter::setPointer('write:start');
write_rows($transformed);
PerformanceMeter::setPointer('write:end');

PerformanceMeter::setPointer('done');

printf(
    "load=%ss transform=%ss write=%ss total=%ss peak=%s\n",
    PerformanceMeter::elapsedTime('load:start',      'load:end'),
    PerformanceMeter::elapsedTime('transform:start', 'transform:end'),
    PerformanceMeter::elapsedTime('write:start',     'write:end'),
    PerformanceMeter::elapsedTime('boot',            'done'),
    PerformanceMeter::peakMemoryUsage(),
);
```

The `total` line is not just `load + transform + write` — it also includes anything that happened *between* phases (boot overhead, `printf` calls, etc.). Both numbers are useful.

## Variant — multiple invocations averaged

A single-shot measurement is noisy. To get a more stable number, wrap the workload in an outer loop and divide:

```php
$iterations = 10;

PerformanceMeter::setPointer('s');
for ($i = 0; $i < $iterations; $i++) {
    workload();
}
PerformanceMeter::setPointer('e');

printf(
    "%d iterations: total=%ss per-iteration=%ss\n",
    $iterations,
    PerformanceMeter::elapsedTime('s', 'e', 6),
    PerformanceMeter::elapsedTime('s', 'e', 6) / $iterations,
);
```

For statistical rigour (warm-up runs, outlier rejection, confidence intervals), reach for [`phpbench/phpbench`](https://github.com/phpbench/phpbench) instead. PerformanceMeter is best for "is this twice as fast or twice as slow?", not "is this 3 % faster?".

## Variant — exit codes from a threshold

Useful as a coarse smoke test in CI: fail the build if the workload ever exceeds a budget.

```php
PerformanceMeter::setPointer('s');
workload();
PerformanceMeter::setPointer('e');

$elapsed = PerformanceMeter::elapsedTime('s', 'e');
$budget  = 1.0; // seconds

if ($elapsed > $budget) {
    fwrite(STDERR, sprintf("FAIL: workload took %.4fs, budget was %.2fs\n", $elapsed, $budget));
    exit(1);
}

fwrite(STDOUT, sprintf("OK: workload took %.4fs (budget %.2fs)\n", $elapsed, $budget));
exit(0);
```

Pin the budget loose enough that CI runner variability does not produce false positives — generally 2 × to 3 × the median local time is a reasonable starting point.

## See also

- [Cron Job Timing Log](Recipe-Cron-Timing.md) — the same idea applied to scheduled jobs.
- [A/B Comparison](Recipe-AB-Comparison.md) — comparing two candidate implementations.
- [Use Cases & Comparison](Use-Cases.md) — when to graduate from this script to `phpbench`.
