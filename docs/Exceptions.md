# Exceptions

PerformanceMeter throws two exception types. Both are standard PHP exceptions or extend them — there is no custom base class to learn.

## `PointerNotFoundException`

```php
namespace InitPHP\PerformanceMeter\Exception;

final class PointerNotFoundException extends \InvalidArgumentException
{
    public static function forName(string $name): self;
}
```

Thrown by [`elapsedTime()`](API-Reference.md#elapsedtime) and [`memoryUsage()`](API-Reference.md#memoryusage) when a referenced checkpoint name has not been recorded.

### When it fires

- `$startPoint` is unknown.
- A non-null `$endPoint` is unknown. (When `$endPoint` is `null`, "now" is used and no lookup happens.)

### Message format

The message embeds the offending name in double quotes so the typo is obvious at a glance:

```
No checkpoint named "boott" has been recorded. Call PerformanceMeter::setPointer() before referencing it.
```

### Catching it

Because it extends `\InvalidArgumentException`, a broad catch will also work:

```php
try {
    PerformanceMeter::elapsedTime('boot');
} catch (\InvalidArgumentException $e) {
    // catches PointerNotFoundException *and* the decimal-validation case
}
```

Catch the specific class when you need to distinguish "unknown checkpoint" from "invalid argument":

```php
use InitPHP\PerformanceMeter\Exception\PointerNotFoundException;

try {
    PerformanceMeter::elapsedTime('boot');
} catch (PointerNotFoundException $e) {
    // unknown checkpoint — record it and move on
    PerformanceMeter::setPointer('boot');
}
```

### Constructing it manually

The static `forName()` factory is the only intended construction path. It builds a consistent message for a given name:

```php
throw PointerNotFoundException::forName('alpha');
// Message: "No checkpoint named "alpha" has been recorded. Call PerformanceMeter::setPointer() before referencing it."
```

You usually do not construct this exception yourself — the methods on `PerformanceMeter` are the only places it is thrown.

### Avoiding it deliberately

If your code path may or may not have recorded a checkpoint yet, guard the lookup with [`has()`](API-Reference.md#has) instead of relying on `try`/`catch`:

```php
if (PerformanceMeter::has('boot')) {
    echo PerformanceMeter::elapsedTime('boot');
}
```

`has()` is the explicit "do not throw" version of the lookup.

---

## `\InvalidArgumentException`

PerformanceMeter throws the standard `\InvalidArgumentException` (uncustomised) from three methods when `$decimal < 0`:

- [`elapsedTime()`](API-Reference.md#elapsedtime)
- [`memoryUsage()`](API-Reference.md#memoryusage)
- [`peakMemoryUsage()`](API-Reference.md#peakmemoryusage)

The message is:

```
The $decimal argument must be greater than or equal to 0.
```

Negative `$decimal` values are almost always typos — the legitimate range is 0 (whole-unit rounding) and up. Treating a negative argument as an error catches the mistake at the call site.

### Distinguishing the two

| Exception class | Cause |
| --- | --- |
| `PointerNotFoundException` (extends `\InvalidArgumentException`) | Unknown checkpoint name |
| `\InvalidArgumentException` (raw) | `decimal < 0` |

If you `catch (\InvalidArgumentException $e)`, you catch both. If you `catch (PointerNotFoundException $e)`, you catch only the first.

---

## Why fail fast?

Earlier versions of the package returned `~0` for an unknown start name (`"now" - "now"`) and silently fell back to "now" for an unknown end name. Both behaviours hid bugs:

- A typo in a checkpoint name produced a "successful" measurement of zero seconds, which looked like a fast operation when nothing had been measured at all.
- A measurement that *seemed* to compare two named points actually compared one named point to "now", quietly inflating the delta.

Both failure modes are silent and survive code review. v2 throws so they cannot.

If the trade-off (a throwable rather than a silent zero) is wrong for your use case, [`has()`](API-Reference.md#has) gives you the explicit fallback path without forcing a `try`/`catch` block.
