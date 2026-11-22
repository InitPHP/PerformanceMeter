# Recipes

Real-world patterns for using PerformanceMeter. Each recipe is self-contained and copy-pasteable into a fresh script.

## Index

| Recipe | What it shows |
| --- | --- |
| [CLI Benchmark Script](Recipe-CLI-Benchmark.md) | A `bench.php` that prints elapsed / memory / peak in one summary line. |
| [Cron Job Timing Log](Recipe-Cron-Timing.md) | Append one line per scheduled run to a log file for low-fi trend analysis. |
| [A/B Comparison of Two Implementations](Recipe-AB-Comparison.md) | Reset-between-runs pattern for comparing candidate implementations. |
| [Peak Memory Tracking](Recipe-Peak-Memory.md) | Tracking the high-water mark through distinct phases of a script. |

Each recipe page calls out the relevant caveats — microbenchmark noise, formatter caveats, why `reset()` is necessary, etc.

## Cross-cutting tips

A few patterns that come up in more than one recipe.

### Namespace your checkpoint names

The registry is global. If your code might run alongside another library that uses PerformanceMeter, prefix your names with a stable token so you do not collide:

```php
PerformanceMeter::setPointer('mylib:request:start');
PerformanceMeter::setPointer('mylib:request:end');
```

A two- or three-part dotted/colon-separated name is enough — there is no built-in namespacing, this is a convention.

### Use `register_shutdown_function()` for "run-to-end" totals

If you want a single "total time" / "peak memory" line at the very end of a script, regardless of how many `exit` / `die` paths exist:

```php
PerformanceMeter::setPointer('boot');

register_shutdown_function(static function (): void {
    fwrite(STDERR, sprintf(
        "elapsed=%ss peak=%s\n",
        PerformanceMeter::elapsedTime('boot'),
        PerformanceMeter::peakMemoryUsage(),
    ));
});
```

The shutdown function runs after every code path, including fatal errors, so the measurement always lands.

### Defeat opcode-cache caching when benchmarking small snippets

If you are comparing two implementations and PHP's opcode cache (or your IDE's auto-formatter) reorders cold load behaviour, your first iteration of each will look slower than subsequent ones. The common fix is a warm-up loop you discard:

```php
// warm-up — measurements thrown away
for ($i = 0; $i < 3; $i++) {
    $fn();
}

// measured loop
PerformanceMeter::setPointer('s');
for ($i = 0; $i < 1_000; $i++) {
    $fn();
}
PerformanceMeter::setPointer('e');
```

For anything serious, use [`phpbench/phpbench`](https://github.com/phpbench/phpbench) — it handles warm-up, outlier rejection, and statistical confidence properly.

### Combine with `try`/`finally` for guaranteed measurement around exceptions

```php
PerformanceMeter::setPointer('handler:start');

try {
    $handler->run();
} finally {
    PerformanceMeter::setPointer('handler:end');
    log_metric(PerformanceMeter::elapsedTime('handler:start', 'handler:end'));
}
```

The measurement records and the metric is logged even if `$handler->run()` throws.

### One checkpoint pair per loop iteration

Every name lives in the same registry, so iteration N overwrites iteration N-1 unless the name carries the index:

```php
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

If you only want a running total, collect the delta on each pass and keep two reusable names:

```php
$total = 0.0;
foreach ($batches as $batch) {
    PerformanceMeter::setPointer('it-start');
    process($batch);
    PerformanceMeter::setPointer('it-end');

    $total += PerformanceMeter::elapsedTime('it-start', 'it-end', 6);
}
PerformanceMeter::reset(); // optional, frees the two slots
printf("Total processing time: %.6fs\n", $total);
```

### Probe-or-skip with `has()`

When a call site cannot know whether an earlier code path already recorded a checkpoint, guard with [`has()`](API-Reference.md#has) instead of catching the exception:

```php
if (!PerformanceMeter::has('boot')) {
    PerformanceMeter::setPointer('boot');
}

// later, anywhere in the request:
echo PerformanceMeter::elapsedTime('boot');
```

This is especially useful for instrumenting library code that may be loaded in different orders.
