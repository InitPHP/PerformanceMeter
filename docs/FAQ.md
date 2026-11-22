# FAQ

Questions that come up repeatedly in issues, discussions, and code reviews.

## Why a single static class? Couldn't I want multiple isolated scopes?

You could, and if you do, this is the wrong package — use [`symfony/stopwatch`](https://github.com/symfony/stopwatch), which is built around the `Stopwatch` instance precisely so two unrelated callers can each have their own state.

PerformanceMeter trades isolation for terseness. Probe code is one line; the cost is that all callers share one namespace of checkpoint names. For the use cases this package targets (one-off benchmarks, CLI tools, library examples, reproducers), that trade is almost always worth it. See [Use Cases & Comparison](Use-Cases.md) for the full reasoning.

## My checkpoint name worked once and now overwrites the old reading. Bug?

Not a bug — it is the documented behaviour. `setPointer()` always writes; calling it a second time with the same name **overwrites** the previous capture. The lower-cased name is the registry key, and there is one value per key.

If you want both readings, use two distinct names:

```php
PerformanceMeter::setPointer('attempt-1');
work();
PerformanceMeter::setPointer('attempt-1-done');

PerformanceMeter::setPointer('attempt-2');
work();
PerformanceMeter::setPointer('attempt-2-done');
```

## Why does `'Foo'` and `'foo'` resolve to the same checkpoint?

Names are normalised to lower case before storage and lookup. This makes it impossible to fail a lookup just because reader and writer disagreed on capitalisation. The cost: `'foo'` and `'Foo'` are not distinct slots — setting one then the other overwrites the first.

If you depend on case-sensitive names, this package is not the right tool. There is no plan to make case sensitivity configurable — it would add a flag that 99 % of callers do not care about.

## Why does an unknown name throw? v1 returned 0 and that was convenient.

The v1 behaviour produced silent zero-duration measurements when you mistyped a name. The most common pattern of "convenient" was: copy-paste a checkpoint, change one letter, deploy, then spend an hour wondering why the new code path looks instant.

v2 throws so the typo surfaces at the call site. If you want the v1 fallback intentionally, the explicit replacement is one extra line:

```php
if (!PerformanceMeter::has('boot')) {
    PerformanceMeter::setPointer('boot');
}
echo PerformanceMeter::elapsedTime('boot');
```

## Should I use `memoryUsage()` or `peakMemoryUsage()`?

Different questions:

- **`memoryUsage('a', 'b')`** — How much memory was held at `'b'` compared to `'a'`? Can be negative if memory was freed between them.
- **`peakMemoryUsage()`** — What was the highest memory usage at any point so far? Always non-negative, only ever grows.

The delta misses transient allocations: a workload that allocates 50MB then frees it before the next checkpoint has a delta near zero but a peak that captured the 50MB spike. When you care about footprint at any point, use peak.

## What's the difference between `setPointer()` and `mark()`?

Nothing functional. `mark($name)` is a one-to-one alias of `setPointer($name)` — same state, same effect. It exists because some readers find stopwatch vocabulary (`mark before` / `mark after`) more natural than registry vocabulary (`setPointer before` / `setPointer after`). Use whichever reads better in context.

## Why does `elapsedTime()` round by default? I want raw microsecond precision.

It rounds to 4 fractional digits by default — about 0.1 ms resolution, which is plenty for any measurement where reading the number off a terminal is the goal. If you want raw precision, pass a larger `$decimal` argument:

```php
PerformanceMeter::elapsedTime('a', 'b', 9); // nanosecond formatting
```

PHP's `microtime(true)` is a `float`, so the actual underlying precision is around 14 significant digits — but the *accuracy* of that value depends on your OS clock, not on PerformanceMeter.

## Can I serialise the registry to disk and reload it next run?

There is no built-in helper. The state lives in `private static array $pointers` and `getPointers()` returns a copy you can serialise yourself:

```php
file_put_contents(__DIR__ . '/run.json', json_encode(PerformanceMeter::getPointers(), JSON_THROW_ON_ERROR));
```

But this is almost certainly the wrong use of the package — measurements are tied to the process they were taken in; comparing them across runs of different processes is meaningful only if you reduce them to scalars (elapsed seconds, peak MB) and store those.

## Is this thread-safe / fiber-safe?

PHP does not have user-visible threads. PHP **does** have fibers (PHP 8.1+), and PerformanceMeter is shared between them — they all see the same registry. If two fibers concurrently record checkpoints with the same name, the second write wins, exactly as in non-fiber code.

If you need per-fiber isolation, this package is not the right tool. Use one `symfony/stopwatch` instance per fiber instead.

## Does this work in `phpdbg` / `php-fpm` / `roadrunner` / `frankenphp`?

Yes — the package only uses standard SAPIs-agnostic functions (`microtime`, `memory_get_usage`, `memory_get_peak_usage`). It will work anywhere a PHP script runs.

A caveat for **long-lived workers** (roadrunner, frankenphp, swoole, react-php): the registry persists across requests within a single worker process. Call `PerformanceMeter::reset()` at the start of each request handler to avoid bleeding measurements across requests.

## How can I be sure the measurement overhead is small?

A single `setPointer()` call does three things: `microtime(true)`, two `memory_get_usage()` calls, one array write, one `strtolower()`. All of these are O(1) PHP-native operations, each microseconds-scale.

A back-of-the-envelope estimate for a `setPointer()` call on a modern CPU is **~1-5 µs**. If you are measuring an operation that itself takes microseconds, the overhead matters; if you are measuring milliseconds or longer, it does not.

For measurements where overhead matters, prefer two checkpoints around a *large* block of work rather than many checkpoints inside a hot loop.

## Is there a way to format time as "1m23s" or "1.23s ago"?

No, and not planned. `elapsedTime()` returns a `float` of seconds; format it however you need on your side. If you find yourself reaching for a heavier helper, `symfony/console` ships a `Helper::formatTime()` and `Helper::formatMemory()` that work on any `float` / `int` input.

## Why isn't there a `composer.lock` in the repo?

PerformanceMeter is a library, not an application. Libraries publish version constraints (in `composer.json`); the consuming project pins exact versions (in its own `composer.lock`). Shipping a library `composer.lock` would just create maintenance work without giving consumers anything useful.

## I want to contribute. Where do I start?

The org-wide [CONTRIBUTING guide](https://github.com/InitPHP/.github/blob/main/CONTRIBUTING.md) is the entry point. For this specific package:

- The whole behaviour is in `src/PerformanceMeter.php` (about 270 lines) — small enough to read in one sitting.
- Tests are in `tests/PerformanceMeterTest.php` with 100 % coverage — start by reading them to see the contract.
- Run `composer qa` locally before opening a PR; CI runs the same checks.

Bug reports with a minimal reproducer are always welcome. Feature proposals are best discussed in [Discussions → Ideas](https://github.com/orgs/InitPHP/discussions/categories/ideas) before code, because the scope of this package is deliberately narrow and not all proposals fit.
