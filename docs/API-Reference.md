# API Reference

Every public method on `InitPHP\PerformanceMeter\PerformanceMeter`. Each entry lists the signature, parameters, return value, error conditions, and a runnable example.

All methods are `static`. The class is `final` and cannot be instantiated.

## Index

- [`setPointer()`](#setpointer)
- [`mark()`](#mark)
- [`elapsedTime()`](#elapsedtime)
- [`memoryUsage()`](#memoryusage)
- [`peakMemoryUsage()`](#peakmemoryusage)
- [`has()`](#has)
- [`getPointers()`](#getpointers)
- [`reset()`](#reset)

The exception type [`PointerNotFoundException`](Exceptions.md) is documented on its own page.

---

## `setPointer()`

```php
public static function setPointer(string $name): void
```

Record a checkpoint under `$name`. Captures, in this order:

- `microtime(true)` — wall-clock seconds as a float
- `memory_get_usage(false)` — emalloc-tracked bytes
- `memory_get_usage(true)` — system-allocated bytes

The three values are stored as a single entry in a process-wide registry, keyed by `strtolower($name)`.

### Parameters

| Name | Type | Notes |
| --- | --- | --- |
| `$name` | `string` | Identifier for the checkpoint. Lower-cased before storage. Empty strings are valid. |

### Behaviour

- A second call with an existing name **overwrites** the previous capture. The first reading is lost.
- The checkpoint persists for the lifetime of the PHP process unless cleared with [`reset()`](#reset).
- The two `memory_get_usage()` calls are made sequentially — between them the allocator state may change by a few bytes. The cost is microseconds and only relevant when probing extremely tight code.

### Example

```php
PerformanceMeter::setPointer('warm-cache');
warm_cache();
PerformanceMeter::setPointer('cache-warm');
```

---

## `mark()`

```php
public static function mark(string $name): void
```

One-to-one alias of [`setPointer()`](#setpointer). Identical behaviour, identical state. Exposed for callers who prefer stopwatch-style vocabulary.

Calling `setPointer('x')` and then `mark('x')` overwrites the same entry — they are the same operation.

### Example

```php
PerformanceMeter::mark('request:start');
$response = $kernel->handle($request);
PerformanceMeter::mark('request:end');
```

---

## `elapsedTime()`

```php
public static function elapsedTime(
    string $startPoint,
    ?string $endPoint = null,
    int $decimal = 4,
): float
```

Return the wall-clock seconds between two checkpoints.

### Parameters

| Name | Type | Default | Notes |
| --- | --- | --- | --- |
| `$startPoint` | `string` | — | Name of the starting checkpoint. **Must already exist.** |
| `$endPoint` | `?string` | `null` | Name of the ending checkpoint. When `null`, the current moment is captured and used. When non-null, **must already exist**. |
| `$decimal` | `int` | `4` | Number of fractional digits in the rounded result. Must be `>= 0`. |

### Returns

`float` — Elapsed time in seconds, rounded to `$decimal` digits. Non-negative as long as `$endPoint` was recorded after `$startPoint`.

### Throws

| Exception | When |
| --- | --- |
| [`PointerNotFoundException`](Exceptions.md) | `$startPoint` is unknown, or a non-null `$endPoint` is unknown. |
| `\InvalidArgumentException` | `$decimal < 0`. |

### Examples

Two explicit checkpoints:

```php
PerformanceMeter::setPointer('a');
do_work();
PerformanceMeter::setPointer('b');

echo PerformanceMeter::elapsedTime('a', 'b', 6), "s\n";
```

Open-ended (since boot):

```php
PerformanceMeter::setPointer('boot');
// ... whole script ...
echo PerformanceMeter::elapsedTime('boot'), "s elapsed since boot\n";
```

Whole-second rounding:

```php
echo PerformanceMeter::elapsedTime('boot', null, 0); // e.g. 2.0
```

---

## `memoryUsage()`

```php
public static function memoryUsage(
    string $startPoint,
    ?string $endPoint = null,
    int $decimal = 2,
    bool $realUsage = false,
): string
```

Return the memory delta between two checkpoints, formatted as a human-readable string.

### Parameters

| Name | Type | Default | Notes |
| --- | --- | --- | --- |
| `$startPoint` | `string` | — | See [`elapsedTime()`](#elapsedtime). |
| `$endPoint` | `?string` | `null` | See [`elapsedTime()`](#elapsedtime). |
| `$decimal` | `int` | `2` | Fractional digits in the formatted output. Must be `>= 0`. |
| `$realUsage` | `bool` | `false` | When `true`, use the system-allocated memory (`memory_get_usage(true)`) instead of the emalloc-tracked figure. |

### Returns

`string` — One of:

- `"<n>KB"` when `|delta| < 1 MB`
- `"<n>MB"` when `|delta| >= 1 MB`

The sign is preserved, so a freed-memory delta starts with `-` (for example `"-3.00MB"`).

### Throws

| Exception | When |
| --- | --- |
| [`PointerNotFoundException`](Exceptions.md) | `$startPoint` is unknown, or a non-null `$endPoint` is unknown. |
| `\InvalidArgumentException` | `$decimal < 0`. |

### Examples

Allocation delta:

```php
PerformanceMeter::setPointer('m1');
$payload = str_repeat('x', 2_000_000);
PerformanceMeter::setPointer('m2');

echo PerformanceMeter::memoryUsage('m1', 'm2');        // e.g. "1.91MB"
```

Freed memory:

```php
$payload = str_repeat('x', 3 * 1024 * 1024);
PerformanceMeter::setPointer('before');
unset($payload);
PerformanceMeter::setPointer('after');

echo PerformanceMeter::memoryUsage('before', 'after'); // e.g. "-3.00MB"
```

System-allocated reading:

```php
echo PerformanceMeter::memoryUsage('m1', 'm2', 4, true);
```

---

## `peakMemoryUsage()`

```php
public static function peakMemoryUsage(int $decimal = 2, bool $realUsage = false): string
```

Report the peak memory used so far by the PHP process, formatted with the same KB/MB rules as [`memoryUsage()`](#memoryusage). Reflects PHP's `memory_get_peak_usage()`.

Does **not** depend on any checkpoints — this method is callable at any time, with or without a prior `setPointer()` call.

### Parameters

| Name | Type | Default | Notes |
| --- | --- | --- | --- |
| `$decimal` | `int` | `2` | Fractional digits. Must be `>= 0`. |
| `$realUsage` | `bool` | `false` | When `true`, return the system-allocated peak. |

### Returns

`string` — `"<n>KB"` or `"<n>MB"`. Always non-negative (a process can never have a negative peak).

### Throws

| Exception | When |
| --- | --- |
| `\InvalidArgumentException` | `$decimal < 0`. |

### Example

```php
import_large_dataset();
echo PerformanceMeter::peakMemoryUsage();           // e.g. "8.50MB"
echo PerformanceMeter::peakMemoryUsage(2, true);    // real-usage peak
```

> On PHP 8.2+, you can call `memory_reset_peak_usage()` to reset the high-water mark between phases of a script.

---

## `has()`

```php
public static function has(string $name): bool
```

Return whether a checkpoint with `$name` has been recorded. Case-insensitive, matching the lowercase normalisation used by `setPointer()`.

### Parameters

| Name | Type | Notes |
| --- | --- | --- |
| `$name` | `string` | Identifier to look up. |

### Returns

`bool` — `true` if the checkpoint exists, `false` otherwise.

### Example

Defensive "first probe wins" pattern:

```php
if (!PerformanceMeter::has('boot')) {
    PerformanceMeter::setPointer('boot');
}
```

Or guard a measurement so it does not throw:

```php
if (PerformanceMeter::has('boot')) {
    echo PerformanceMeter::elapsedTime('boot');
}
```

---

## `getPointers()`

```php
public static function getPointers(): array
```

Return a snapshot copy of the entire checkpoint registry.

### Returns

`array<string, array{time: float, memory: int, memoryReal: int}>` — Keyed by the lowercased checkpoint name. The returned array is a copy (PHP's copy-on-write semantics apply); mutating it has no effect on the internal registry.

Each entry has three keys:

| Key | Type | Source |
| --- | --- | --- |
| `time` | `float` | `microtime(true)` at the moment `setPointer()` was called |
| `memory` | `int` | `memory_get_usage(false)` at the same moment |
| `memoryReal` | `int` | `memory_get_usage(true)` at the same moment |

### Example

```php
PerformanceMeter::setPointer('alpha');
PerformanceMeter::setPointer('beta');

foreach (PerformanceMeter::getPointers() as $name => $snapshot) {
    printf(
        "%s @ %.6fs / %d bytes (emalloc) / %d bytes (real)\n",
        $name,
        $snapshot['time'],
        $snapshot['memory'],
        $snapshot['memoryReal'],
    );
}
```

When would you call this? Most often: dumping the full set at the end of a benchmark for ad-hoc post-processing, or asserting on the registry contents in a test.

---

## `reset()`

```php
public static function reset(): void
```

Clear every recorded checkpoint, returning the registry to its initial empty state. Safe to call when the registry is already empty (idempotent).

### When to use it

- Between independent benchmark runs in a long-lived script.
- In test suites — `setUp()` is the natural place.
- In long-running workers or daemons, to bound the registry's memory footprint.

### Example

```php
foreach ($scenarios as $name => $scenario) {
    PerformanceMeter::reset();
    PerformanceMeter::setPointer('start');
    $scenario();
    printf("%s: %ss\n", $name, PerformanceMeter::elapsedTime('start'));
}
```
