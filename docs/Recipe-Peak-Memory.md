# Recipe — Peak Memory Tracking

Pair-based `memoryUsage()` shows you the *delta* between two checkpoints — useful when you want to know "did this block of code allocate anything?". But sometimes the more relevant question is "did this script ever, at any point, hold a lot of memory?" — even if the allocation was transient and freed before the next checkpoint.

That is what `peakMemoryUsage()` is for. It reads PHP's high-water mark via `memory_get_peak_usage()` and formats it the same way as `memoryUsage()`.

## Single peak reading

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use InitPHP\PerformanceMeter\PerformanceMeter;

import_large_dataset();

echo 'peak: ', PerformanceMeter::peakMemoryUsage(), "\n";
```

Output:

```
peak: 64.32MB
```

That single line tells you the largest memory footprint reached at any point since the script started — including momentary peaks during library initialisation, sorting, or temporary string buffers.

## Tracking peak across phases

When you want to see how the peak grows through distinct phases of a script:

```php
echo 'phase=boot      peak=', PerformanceMeter::peakMemoryUsage(), "\n";

import_large_dataset();
echo 'phase=import    peak=', PerformanceMeter::peakMemoryUsage(), "\n";

run_transformations();
echo 'phase=transform peak=', PerformanceMeter::peakMemoryUsage(), "\n";

flush_to_disk();
echo 'phase=flush     peak=', PerformanceMeter::peakMemoryUsage(), "\n";
```

Sample output:

```
phase=boot      peak=2.41MB
phase=import    peak=58.10MB
phase=transform peak=64.32MB
phase=flush     peak=64.32MB
```

This makes it obvious that the import phase pushed the peak to ~58MB, the transformation added another 6MB, and the flush phase did not allocate anything new beyond what was already held — useful for finding which phase to optimise first.

> The peak only ever grows — `peakMemoryUsage()` after a phase that frees memory will report the same value as before the phase, because PHP tracks the high-water mark, not the current usage. On PHP 8.2+, `memory_reset_peak_usage()` resets the high-water mark to the current usage; call it manually between phases if you want per-phase peaks rather than cumulative.

## Per-phase peaks with PHP 8.2+

```php
import_large_dataset();
printf("import:    peak=%s\n", PerformanceMeter::peakMemoryUsage());

memory_reset_peak_usage();

run_transformations();
printf("transform: peak=%s (since last reset)\n", PerformanceMeter::peakMemoryUsage());

memory_reset_peak_usage();

flush_to_disk();
printf("flush:     peak=%s (since last reset)\n", PerformanceMeter::peakMemoryUsage());
```

This gives you the peak *during* each phase rather than the cumulative maximum. Useful when phases are mostly independent — for example, three sequential migrations on different tables.

## Combining peak with checkpoint deltas

Peak and delta answer different questions; reporting both together is often the most informative:

```php
PerformanceMeter::setPointer('s');
run_workload();
PerformanceMeter::setPointer('e');

printf(
    "elapsed=%ss delta=%s peak=%s\n",
    PerformanceMeter::elapsedTime('s', 'e'),
    PerformanceMeter::memoryUsage('s', 'e'),
    PerformanceMeter::peakMemoryUsage(),
);
```

A workload that allocates and frees 50MB of temporary state has a delta near zero but a peak that includes the transient 50MB — without the peak figure you would never know.

## System-allocated peak (`realUsage = true`)

`peakMemoryUsage()` defaults to the emalloc-tracked figure (what your code is holding from PHP's allocator). Pass `true` for the system-allocated figure (what the OS has handed to PHP):

```php
echo 'emalloc peak: ', PerformanceMeter::peakMemoryUsage(),         "\n";
echo 'system  peak: ', PerformanceMeter::peakMemoryUsage(2, true), "\n";
```

```
emalloc peak: 64.32MB
system  peak: 68.00MB
```

The system figure is always at least as large as the emalloc figure — it includes the allocator's own bookkeeping and PHP's chunk-level over-allocation. Use it when:

- you are diagnosing why PHP hit its `memory_limit` despite the emalloc figure looking small
- you are tuning the OS-level memory budget for a long-running worker

For day-to-day measurements, the default (emalloc) figure is more useful — it reflects what your code is actually retaining.

## Logging the peak at shutdown

A common production pattern: log the peak of the process so you can spot the workers that are creeping toward the limit.

```php
register_shutdown_function(static function (): void {
    error_log(sprintf(
        '[%s] worker exit: peak=%s',
        date(DATE_ATOM),
        PerformanceMeter::peakMemoryUsage(),
    ));
});
```

For a worker that processes one job per invocation, this is one line per job — easy to `awk` over for trend analysis.

## See also

- [API Reference → `peakMemoryUsage()`](API-Reference.md#peakmemoryusage) — exact return shape and behaviour.
- [Concepts → Memory: emalloc vs system-allocated](Concepts.md#memory-emalloc-vs-system-allocated) — what the `realUsage` flag actually changes.
- [Recipes → `register_shutdown_function()` tip](Recipes.md#use-register_shutdown_function-for-run-to-end-totals) — why it survives `exit` / `die`.
