# Recipe — A/B Comparison of Two Implementations

When you have two implementations of the same operation and want to know which is faster on your data, the pattern is: iterate the candidates, reset between runs, capture the same metrics for each, print a table.

## The script

```php
<?php

declare(strict_types=1);

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
    'array_reduce' => static fn () => array_reduce($payload, fn ($c, $n) => $c + $n, 0),
];

printf("%-15s %-12s %-12s\n", 'candidate', 'elapsed', 'memory');
printf("%-15s %-12s %-12s\n", '---------', '-------', '------');

foreach ($candidates as $name => $fn) {
    PerformanceMeter::reset();
    PerformanceMeter::setPointer('s');
    $result = $fn();
    PerformanceMeter::setPointer('e');

    printf(
        "%-15s %-12s %-12s\n",
        $name,
        PerformanceMeter::elapsedTime('s', 'e', 6) . 's',
        PerformanceMeter::memoryUsage('s', 'e'),
    );
}
```

Sample output:

```
candidate       elapsed      memory
---------       -------      ------
array_sum       0.005821s    0.13KB
foreach         0.029104s    0.13KB
array_reduce    0.187392s    0.13KB
```

## Why the `reset()` call matters

Without `PerformanceMeter::reset()` between iterations, the `'s'` and `'e'` checkpoints from candidate N stay in the registry. When candidate N+1 calls `setPointer('s')` it overwrites the previous one — fine for that pair — but the second `setPointer('e')` likewise overwrites the previous end. The measurement still works because each iteration captures both fresh, but the registry slowly fills with state from earlier runs unless you clear it.

For a small script with three candidates this is invisible; for a script that iterates thousands of candidate / input combinations, calling `reset()` between runs keeps the registry from growing.

## Variant — multiple iterations per candidate to defeat noise

A single-shot measurement is noisy: GC pauses, opcode-cache warmup, scheduler interrupts. Run each candidate many times and divide:

```php
$iterations = 100;

foreach ($candidates as $name => $fn) {
    PerformanceMeter::reset();
    PerformanceMeter::setPointer('s');
    for ($i = 0; $i < $iterations; $i++) {
        $fn();
    }
    PerformanceMeter::setPointer('e');

    $total = PerformanceMeter::elapsedTime('s', 'e', 6);
    printf("%-15s total=%ss avg=%ss\n", $name, $total, $total / $iterations);
}
```

## Variant — warm-up runs

If you suspect the first iteration of each candidate is slower because of cold caches or lazy autoloading:

```php
foreach ($candidates as $name => $fn) {
    // warm-up: discard measurements
    for ($i = 0; $i < 5; $i++) {
        $fn();
    }

    PerformanceMeter::reset();
    PerformanceMeter::setPointer('s');
    for ($i = 0; $i < 100; $i++) {
        $fn();
    }
    PerformanceMeter::setPointer('e');

    // ... print ...
}
```

## Variant — output sorted by elapsed time

When you have more than three candidates, sorting the output makes the result immediately readable:

```php
$results = [];

foreach ($candidates as $name => $fn) {
    PerformanceMeter::reset();
    PerformanceMeter::setPointer('s');
    $fn();
    PerformanceMeter::setPointer('e');

    $results[$name] = PerformanceMeter::elapsedTime('s', 'e', 6);
}

asort($results); // fastest first

foreach ($results as $name => $elapsed) {
    printf("%-20s %ss\n", $name, $elapsed);
}
```

## When this script is the wrong tool

A few hundred iterations of an in-memory operation is enough to see a 5× difference; it is not enough to see a 5 % difference. If you need:

- statistical confidence intervals
- outlier rejection (median / trimmed mean instead of average)
- correlation with input size or system load
- comparison between PHP versions or extension configurations

…reach for [`phpbench/phpbench`](https://github.com/phpbench/phpbench). It is purpose-built for the things PerformanceMeter deliberately does not do.

## See also

- [CLI Benchmark Script](Recipe-CLI-Benchmark.md) — the same idea for a single workload.
- [Use Cases & Comparison](Use-Cases.md) — clear pointers on when you have outgrown this package.
- [Concepts → A single global registry](Concepts.md#a-single-global-registry) — why `reset()` is necessary.
