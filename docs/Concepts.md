# Concepts

PerformanceMeter is a small package, but a few choices in its API have non-obvious consequences. This page explains them all in one place so the rest of the wiki can stay focused on usage.

## A single global registry

There is **one** registry of checkpoints per PHP process, stored as a `private static array` on the `PerformanceMeter` class. Every caller — your application code, a library you depend on, the test suite — writes into and reads from the same array.

```php
PerformanceMeter::setPointer('start');     // writes to the registry
PerformanceMeter::elapsedTime('start');    // reads from the registry
```

There is no instance to construct, no scope to manage, no per-request bucket.

**Implications:**

- Probes are one line each — there is no boilerplate.
- Two unrelated pieces of code can collide on a name. If you write a library, namespace your checkpoint names (`mylib:request:start` rather than `start`).
- A long-running worker that records new checkpoints on every iteration will grow its memory footprint unboundedly. Use [`reset()`](API-Reference.md#reset) to clear the registry between independent runs.

If isolation per scope or per request matters to you, this package is the wrong tool — use `symfony/stopwatch` or a real profiler. See [Use Cases & Comparison](Use-Cases.md).

## Names are case-insensitive

`setPointer()`, `has()`, `elapsedTime()`, and `memoryUsage()` all normalise the name to lower case before storing or looking up. The following four calls all reference the same checkpoint:

```php
PerformanceMeter::setPointer('Boot');
PerformanceMeter::has('boot');     // true
PerformanceMeter::has('BOOT');     // true
PerformanceMeter::has('Boot');     // true
```

This is convenient — you cannot fail to find a checkpoint just because your reader and writer disagreed on capitalisation — but it does mean `'foo'` and `'Foo'` are not distinct slots. Setting one then the other will overwrite the first.

## Open-ended measurements default to "now"

`elapsedTime()` and `memoryUsage()` both treat a `null` end-point as "capture the current moment and use that". This lets you write:

```php
PerformanceMeter::setPointer('boot');

// ... your whole script ...

echo PerformanceMeter::elapsedTime('boot'); // seconds since 'boot'
```

without having to manually record an `'end'` checkpoint. The same is true of `memoryUsage()`.

A common pattern: record one `'boot'` checkpoint at the top of a script and probe the elapsed time at the end (or in a `register_shutdown_function()` callback) for a "total run time" log line.

## Fail-fast on unknown names

If you reference a checkpoint that has not been recorded, you get a [`PointerNotFoundException`](Exceptions.md):

```php
PerformanceMeter::setPointer('start');
PerformanceMeter::elapsedTime('strat'); // throws — typo
```

This applies to **both** the start argument and a non-null end argument. The exception extends `\InvalidArgumentException`, so broad catches still work; catch the specific class when you need to distinguish "you asked for a checkpoint that does not exist" from other validation errors.

**Why throw instead of returning 0 or `null`?** Earlier versions of the package silently returned ~0 in this case (`"now" - "now"`). Typos turned into hours of debugging because the measurement *looked* fine. Failing loudly at the call site is the safer default — and the [`has()`](API-Reference.md#has) method exists precisely for the "measure if present" pattern when you genuinely want it.

## Memory: emalloc vs system-allocated

PHP exposes two memory readings:

| Reading | Function | What it measures |
| --- | --- | --- |
| Emalloc-tracked (default) | `memory_get_usage(false)` | Memory PHP's allocator currently holds for your code. |
| System-allocated | `memory_get_usage(true)` | Memory the OS has handed to PHP, including the allocator's own overhead and any over-allocation. |

`setPointer()` captures **both** at every call, so the choice between them is made at query time:

```php
PerformanceMeter::memoryUsage('start', 'end');             // emalloc (default)
PerformanceMeter::memoryUsage('start', 'end', 2, true);    // system-allocated
```

For most application-level work the emalloc figure is what you want — it reflects what your code is actually retaining. Use `realUsage: true` when you are debugging questions about PHP's memory limit or about chunk allocation behaviour.

The same flag exists on [`peakMemoryUsage()`](API-Reference.md#peakmemoryusage).

## Memory delta formatting

`memoryUsage()` returns a string ending in either `KB` or `MB`, picked from the **absolute** size of the delta. Negative deltas — memory that was freed between two checkpoints — are reported with a leading `-`:

```php
PerformanceMeter::memoryUsage('a', 'b'); // "0.13KB"
PerformanceMeter::memoryUsage('a', 'b'); // "2.50MB"
PerformanceMeter::memoryUsage('a', 'b'); // "-3.00MB" (freed)
```

The threshold for switching from `KB` to `MB` is exactly `1024 * 1024` bytes (one mebibyte). Below that the formatter uses `KB`; at or above, `MB`.

## The `decimal` parameter

Methods that produce a number accept an `int $decimal` argument controlling the precision of the rounded output:

| Method | Default | Notes |
| --- | --- | --- |
| `elapsedTime()` | `4` | seconds rounded to 4 fractional digits ≈ 0.1 ms resolution |
| `memoryUsage()` | `2` | KB / MB to two decimal places |
| `peakMemoryUsage()` | `2` | KB / MB to two decimal places |

Negative values are rejected with an `\InvalidArgumentException` — passing `decimal = -1` is almost always a typo.

A `decimal = 0` is legal and useful for whole-second or whole-KB rounding.

## `final` and uninstantiable

The class is declared `final` with a `private` constructor. You cannot:

- subclass `PerformanceMeter`
- call `new PerformanceMeter()`

This is enforced because every method is `static` — extending or instantiating the class achieves nothing functional and would lock the package into a public surface it has no reason to expose. Compose, do not inherit; if you need to wrap the API behind your own façade, write a small adapter class that delegates to the static methods.

## Static state and tests

Because the registry is class-level static state, tests that touch `PerformanceMeter` must reset it between cases or measurements will bleed across tests. The pattern in the package's own test suite:

```php
protected function setUp(): void
{
    PerformanceMeter::reset();
}
```

See [Testing](Testing.md) for the complete picture, including how to integrate with PHPUnit's data providers and `executionOrder="random"`.

## What this implies for you

If you take only one thing from this page: **probe code lives in global namespace and persists for the life of the process**. That is exactly what makes the API as small as it is, and exactly what you should remember when something behaves unexpectedly.
