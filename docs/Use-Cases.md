# Use Cases & Comparison

PerformanceMeter occupies a deliberately narrow slice of the PHP profiling landscape. This page covers what it is good at, what it is not for, and how it compares to the alternatives.

## Good fits

The package is built for short, probe-style measurements where adding a heavier dependency would defeat the purpose. Concrete examples:

### One-off benchmarks

A throwaway `bench.php` next to a workload, run once or twice while you decide between two implementations. You want the answer in seconds, not after configuring a benchmarking framework.

### CLI tools and cron jobs

A single line printed at the end of a job — "processed 12,034 rows in 3.21s, peak 14MB" — is often all the operational visibility a script needs. PerformanceMeter gives you that line without committing your code to a metrics backend.

### Tutorial and educational code

When you are teaching PHP or demonstrating a technique, every `composer require` you add is friction for the reader. A zero-dependency profiler that fits on the same page as the lesson keeps the focus on the lesson.

### Bug-report reproducers

A small standalone script that demonstrates a performance regression. The maintainer of the affected package wants to run the script, not also `composer install` a profiler.

### Hot-path probes during local development

Five seconds to add, five seconds to remove. Useful when you are pretty sure where the slowness is and you just want to confirm before reaching for a real profiler.

## Bad fits

For any of the following, reach for a purpose-built tool instead.

| Need | Use instead |
| --- | --- |
| Application-level profiling with nested sections, periods, categories | [`symfony/stopwatch`](https://github.com/symfony/stopwatch) |
| Production profiling, flame graphs, call tree analysis | [Xdebug profiler](https://xdebug.org/docs/profiler), [Blackfire](https://blackfire.io), [Tideways](https://tideways.com), [SPX](https://github.com/NoiseByNorthwest/php-spx) |
| Web request profiling with timeline UI | Symfony's WebProfilerBundle, Laravel Telescope, Clockwork |
| Memory leak hunting with object retention graphs | Xdebug + `xdebug_debug_zval`, or `memprof` |
| Statistical microbenchmarks (warm-up, outlier rejection, confidence intervals) | [`phpbench/phpbench`](https://github.com/phpbench/phpbench) |
| OpenTelemetry / APM integration | [`open-telemetry/sdk`](https://github.com/open-telemetry/opentelemetry-php) and an APM vendor SDK |
| Distributed tracing across services | OpenTelemetry / Zipkin / Jaeger SDKs |

Reaching for any of these means PerformanceMeter is the wrong tool — and that is by design.

## Why so opinionated?

The package's design choices — a single static registry, no instances, no configuration — work *because* the scope is narrow. Extending the API to compete with `symfony/stopwatch` would mean:

- Adding instance-based scopes (so two callers cannot collide on names)
- Adding nested sections, periods, and categories
- Tracking event hierarchies
- Adding factories, builders, formatters

…all of which already exist in `symfony/stopwatch`, written by people who care about that problem and have spent the time. PerformanceMeter explicitly does not try to be that.

## Side-by-side: PerformanceMeter vs `symfony/stopwatch`

These are the two packages most often considered against each other.

| Concern | PerformanceMeter | `symfony/stopwatch` |
| --- | --- | --- |
| Dependencies | Zero | `symfony/service-contracts` |
| API surface | 1 class, 7 methods (+ 1 alias) | Multiple classes (`Stopwatch`, `StopwatchEvent`, `Section`, `StopwatchPeriod`) |
| Instantiation | Static — no `new` | `new Stopwatch()` per scope |
| Nested measurements | Not supported (flat registry) | First-class: events can be started inside other events |
| Categories / sections | Not supported | First-class |
| Memory delta | Per checkpoint pair | Per period, plus aggregates |
| Peak memory helper | `peakMemoryUsage()` | Available per period |
| Integration with frameworks | None | Drop-in for Symfony's WebProfilerBundle |
| Reset between runs | `PerformanceMeter::reset()` | Throw away the `Stopwatch` instance |
| Footprint added to `composer.lock` | 0 packages | 2 packages |

The decision lattice is simple:

- **Are you writing tutorial code, a reproducer, a one-off script, or a cron job?** → PerformanceMeter.
- **Do you need any of the right-column features?** → `symfony/stopwatch`.
- **Are you optimising a production service?** → Neither — use a real profiler like Blackfire / Tideways / SPX or Xdebug's profiler.

## Side-by-side: PerformanceMeter vs raw PHP functions

The closest "alternative" is just calling `microtime(true)` and `memory_get_usage()` yourself. PerformanceMeter is essentially a thin convention over that.

```php
// Without PerformanceMeter
$startTime  = microtime(true);
$startMem   = memory_get_usage();
do_work();
$elapsed    = round(microtime(true) - $startTime, 4);
$memDelta   = memory_get_usage() - $startMem;
$memLabel   = abs($memDelta) < 1_048_576
    ? round($memDelta / 1024, 2) . 'KB'
    : round($memDelta / 1_048_576, 2) . 'MB';
echo "elapsed: {$elapsed}s, memory: {$memLabel}";
```

```php
// With PerformanceMeter
PerformanceMeter::setPointer('s');
do_work();
PerformanceMeter::setPointer('e');
echo "elapsed: ", PerformanceMeter::elapsedTime('s', 'e'),
     "s, memory: ", PerformanceMeter::memoryUsage('s', 'e');
```

You save:

- Repeated naming of `$start*` / `$end*` variables for every probe.
- A potentially-buggy formatter (the absolute-value bug that was a real defect in v1).
- Implicit knowledge of which `memory_get_usage()` flag and which `microtime()` form to use.
- A consistent vocabulary across a codebase.

You lose:

- One line per probe (`use InitPHP\PerformanceMeter\PerformanceMeter;`) and a `composer require`.

The trade-off is worth it once you have more than one or two probes in a project, or once you want consistent output formatting across them.

## What this package will never grow into

To set expectations: features explicitly out of scope, with no plan to add them.

- Nested or scoped measurements.
- Instance-based scopes.
- Time formatters beyond seconds (no `ms`, `min`, `h`).
- Memory formatters beyond `KB` / `MB` (no `B`, `GB`, `TB`).
- Tagged, categorised, or hierarchical events.
- Persistence, serialisation, or export of the registry.
- Framework integrations.

Any of those is a strong signal that you have outgrown PerformanceMeter and should pick one of the tools listed above.
