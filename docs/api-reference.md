# API Reference

This document covers every public method on `InitPHP\PerformanceMeter\PerformanceMeter` and the one exception type it throws. Each entry lists the signature, parameters, return value, error conditions, and a minimal runnable example.

All methods are **static**. The class is **`final`** and cannot be instantiated.

## Index

- [`setPointer()`](#setpointer)
- [`mark()`](#mark)
- [`elapsedTime()`](#elapsedtime)
- [`memoryUsage()`](#memoryusage)
- [`peakMemoryUsage()`](#peakmemoryusage)
- [`has()`](#has)
- [`getPointers()`](#getpointers)
- [`reset()`](#reset)
- [`PointerNotFoundException`](#pointernotfoundexception)

---

## `setPointer()`

```php
public static function setPointer(string $name): void
```

Record a checkpoint under the given name. Captures the current wall-clock time (`microtime(true)`) and both memory readings (`memory_get_usage(false)` and `memory_get_usage(true)`).

### Parameters

- `string $name` — Identifier for the checkpoint. Names are normalised to lower case before storage, so `'Foo'` and `'foo'` refer to the same checkpoint. Empty strings are valid.

### Behaviour

- Calling `setPointer()` again with a name that already exists **overwrites** the previous reading.
- The checkpoint persists for the lifetime of the PHP process unless cleared with [`reset()`](#reset).

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

One-to-one alias of [`setPointer()`](#setpointer). Exposed for callers who prefer stopwatch-style vocabulary. Identical behaviour, identical state — calling `setPointer('x')` and then `mark('x')` overwrites the same entry.

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

Measure the wall-clock seconds between two checkpoints.

### Parameters

- `string $startPoint` — Name of the starting checkpoint. **Must already exist** or `PointerNotFoundException` is thrown.
- `?string $endPoint` — Name of the ending checkpoint. When `null` (default), the current moment is captured and used as the end of the interval. When a non-null name is given, it **must already exist**.
- `int $decimal` — Number of fractional digits to round to. Must be `>= 0`.

### Returns

`float` — Elapsed time in seconds, rounded to the requested precision. Always uses the absolute difference, so it is non-negative as long as `$endPoint` was recorded after `$startPoint`.

### Throws

- `PointerNotFoundException` — `$startPoint` is unknown, or a non-null `$endPoint` is unknown.
- `InvalidArgumentException` — `$decimal < 0`.

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
// ... whole request lifecycle ...
echo PerformanceMeter::elapsedTime('boot'), "s elapsed since boot\n";
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

Measure the memory delta between two checkpoints and format it as a human-readable string.

### Parameters

- `string $startPoint` — See [`elapsedTime()`](#elapsedtime).
- `?string $endPoint` — See [`elapsedTime()`](#elapsedtime).
- `int $decimal` — Fractional digits in the formatted output. Must be `>= 0`.
- `bool $realUsage` — When `true`, use the system-allocated memory (`memory_get_usage(true)`) instead of the emalloc-tracked figure. Default is `false`.

### Returns

`string` — One of:

- `"<n>KB"` when `|delta| < 1 MB`
- `"<n>MB"` when `|delta| >= 1 MB`

The sign is preserved, so a freed-memory delta will start with `-` (for example `"-3.00MB"`).

### Throws

- `PointerNotFoundException` — `$startPoint` is unknown, or a non-null `$endPoint` is unknown.
- `InvalidArgumentException` — `$decimal < 0`.

### Examples

```php
PerformanceMeter::setPointer('m1');
$payload = str_repeat('x', 2_000_000);
PerformanceMeter::setPointer('m2');

echo PerformanceMeter::memoryUsage('m1', 'm2'), "\n";       // "1.91MB"
echo PerformanceMeter::memoryUsage('m1', 'm2', 4, true), "\n"; // real-usage variant
```

Freed memory:

```php
$payload = str_repeat('x', 3 * 1024 * 1024);
PerformanceMeter::setPointer('before');
unset($payload);
PerformanceMeter::setPointer('after');

echo PerformanceMeter::memoryUsage('before', 'after'); // "-3.00MB"
```

---

## `peakMemoryUsage()`

```php
public static function peakMemoryUsage(int $decimal = 2, bool $realUsage = false): string
```

Report the peak memory used so far by the PHP process, formatted with the same KB/MB suffix rules as [`memoryUsage()`](#memoryusage). Reflects PHP's `memory_get_peak_usage()`.

### Parameters

- `int $decimal` — Fractional digits in the output. Must be `>= 0`.
- `bool $realUsage` — When `true`, returns the system-allocated peak.

### Returns

`string` — `"<n>KB"` or `"<n>MB"`. Always non-negative.

### Throws

- `InvalidArgumentException` — `$decimal < 0`.

### Example

```php
import_large_dataset();
echo PerformanceMeter::peakMemoryUsage(), "\n";          // "8.50MB"
echo PerformanceMeter::peakMemoryUsage(2, true), "\n";   // real-usage peak
```

---

## `has()`

```php
public static function has(string $name): bool
```

Return whether a checkpoint with the given name has been recorded. Case-insensitive lookup, matching the lowercase normalisation used by `setPointer()`.

### Example

```php
if (!PerformanceMeter::has('request:start')) {
    PerformanceMeter::setPointer('request:start');
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

### Example

```php
PerformanceMeter::setPointer('alpha');
PerformanceMeter::setPointer('beta');

foreach (PerformanceMeter::getPointers() as $name => $snapshot) {
    printf("%s @ %.6fs / %d bytes\n", $name, $snapshot['time'], $snapshot['memory']);
}
```

---

## `reset()`

```php
public static function reset(): void
```

Clear every recorded checkpoint. Safe to call when the registry is already empty (idempotent).

### When to use it

- Between independent benchmark runs in a long-lived script
- In test suites, to prevent state from one test bleeding into another (`setUp()` is the natural place)
- In long-running workers, to bound the registry's memory footprint

### Example

```php
foreach ($scenarios as $name => $scenario) {
    PerformanceMeter::reset();
    PerformanceMeter::setPointer('start');
    $scenario();
    printf("%s: %ss\n", $name, PerformanceMeter::elapsedTime('start'));
}
```

---

## `PointerNotFoundException`

```php
namespace InitPHP\PerformanceMeter\Exception;

final class PointerNotFoundException extends \InvalidArgumentException
{
    public static function forName(string $name): self;
}
```

Thrown by `elapsedTime()` and `memoryUsage()` when a referenced checkpoint name has not been recorded. The exception message includes the offending name so the source of the typo is obvious.

Because it extends `\InvalidArgumentException`, broad `catch (\InvalidArgumentException $e)` blocks will also catch it. Catch the specific class when you need to distinguish "you asked for a checkpoint that does not exist" from other argument-validation errors.

### Example

```php
use InitPHP\PerformanceMeter\Exception\PointerNotFoundException;

try {
    PerformanceMeter::elapsedTime('boot');
} catch (PointerNotFoundException $e) {
    // first call in this process — record the boot checkpoint and move on
    PerformanceMeter::setPointer('boot');
}
```
