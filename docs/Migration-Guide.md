# Migration Guide — v1.x → v2.0

v2.0 fixes real bugs and tightens the API. Most callers only need to bump PHP. This page lists every behavioural change, what to do about it, and how to apply each change to existing code.

> The version table below is the 1.x to 2.0 picture, where the PHP floor became 8.1. Version 3.0.0 raises it again to PHP 8.2 (see the [Changelog](https://github.com/InitPHP/PerformanceMeter/blob/main/CHANGELOG.md)), so check [Installation](Installation.md) for the current requirement.

## TL;DR

If your code only ever:

- recorded checkpoints with names you spelled correctly
- read deltas between checkpoints that you actually recorded
- never relied on the exact textual output of `memoryUsage()` for freed memory ≥ 1 MB
- already runs on PHP 8.1+
- does not subclass `PerformanceMeter`

…then you have nothing to change. Upgrade and move on.

If any of the above does not match, read the relevant section below.

## Compatibility matrix

| Area | v1 | v2 | Impact |
| --- | --- | --- | --- |
| PHP requirement | `>=7.4` | `^8.1` | **Breaking** if you still run 7.4 or 8.0. |
| Missing `$startPoint` | Silently returned `~0` (`"now" - "now"`) | Throws `PointerNotFoundException` | **Breaking** if you relied on the silent fallback. |
| Missing non-null `$endPoint` | Silently fell back to "now" | Throws `PointerNotFoundException` | **Breaking** for the same reason. |
| `memoryUsage()` with freed memory ≥ 1 MB | Reported in `KB` (a bug — e.g. `"-3072KB"`) | Reports correctly in `MB` (e.g. `"-3.00MB"`) | **Breaking** only if your code parsed the broken output. |
| `decimal < 0` | Accepted, produced odd output | Throws `\InvalidArgumentException` | **Breaking** if you passed negative decimals. |
| Subclassing `PerformanceMeter` | Allowed (pointless — all-static) | Blocked (`final`) | **Breaking** if you extended it. |
| `protected static $pointers` | Visible to subclasses | `private` | **Breaking** for the same reason. |
| `microtime()` parsing | `explode(' ', microtime())` + sum | `microtime(true)` directly | Internal — no API impact. |
| New: `reset()`, `has()`, `peakMemoryUsage()`, `getPointers()` | — | Added | Opt-in additions. |
| PHPDoc language | Turkish | English | Doc-only. |
| Composer autoload | `files` | PSR-4 | Internal — `use` statements unchanged. |

## Step-by-step migration

### 1. Bump PHP and the constraint

```diff
 // composer.json
 "require": {
-    "initphp/performance-meter": "^1.0",
-    "php": ">=7.4"
+    "initphp/performance-meter": "^2.0",
+    "php": "^8.1"
 }
```

Then:

```bash
composer update initphp/performance-meter
```

### 2. Fix call sites that referenced unknown names

Run your test suite. Any test or code path that referenced a checkpoint name that was never recorded will now throw `PointerNotFoundException`.

**If the typo was an actual bug** (the v1 silent fallback was masking it), fix the typo.

**If you intentionally wanted "measure if present"**, guard with `has()`:

```diff
- echo PerformanceMeter::elapsedTime('boot');
+ if (PerformanceMeter::has('boot')) {
+     echo PerformanceMeter::elapsedTime('boot');
+ }
```

**If you intentionally wanted "fall back to now if missing"**, you can replicate the v1 behaviour with a one-liner:

```diff
- echo PerformanceMeter::elapsedTime('boot');
+ if (!PerformanceMeter::has('boot')) {
+     PerformanceMeter::setPointer('boot');
+ }
+ echo PerformanceMeter::elapsedTime('boot');
```

### 3. Update tests that assert on broken `memoryUsage()` output

Only relevant if you wrote tests like:

```diff
- self::assertSame('-3072KB', PerformanceMeter::memoryUsage('a', 'b'));
+ self::assertSame('-3.00MB', PerformanceMeter::memoryUsage('a', 'b'));
```

The new output is the correct one. The unit is now selected from `abs($delta)`, so a 3 MB freed delta prints in MB instead of KB.

### 4. Remove negative `$decimal` arguments

These were always weird; v2 rejects them outright:

```diff
- PerformanceMeter::elapsedTime('a', 'b', -1);
+ PerformanceMeter::elapsedTime('a', 'b', 0);
```

If you were passing `-1` intentionally for some rounding effect, switch to `0` and explicitly cast/format on the receiving side.

### 5. Replace subclasses with composition

If you had a subclass purely to access `protected static $pointers`, switch to the public accessor:

```diff
- final class MyMeter extends PerformanceMeter
- {
-     public static function dump(): array
-     {
-         return self::$pointers;
-     }
- }
- $snapshot = MyMeter::dump();
+ $snapshot = PerformanceMeter::getPointers();
```

If you had a subclass for any other reason: it was probably dead code (every method on the parent is static), and the subclass can be deleted.

### 6. Optionally adopt the new methods

These additions are pure opt-in — your existing code continues to work without them:

```php
PerformanceMeter::reset();                       // clear the registry
PerformanceMeter::has('boot');                   // bool — does this exist?
PerformanceMeter::peakMemoryUsage();             // memory_get_peak_usage(), formatted
PerformanceMeter::getPointers();                 // full snapshot copy
PerformanceMeter::memoryUsage('a', 'b', 2, true); // real-usage variant
PerformanceMeter::peakMemoryUsage(2, true);      // real-usage variant
```

The most commonly useful one is `reset()` — drop it into the `setUp()` of any test that touches PerformanceMeter so static state cannot bleed across cases.

## Worked example — full diff

A small benchmark script that worked on v1, updated for v2:

```diff
 <?php
 require __DIR__ . '/vendor/autoload.php';

 use InitPHP\PerformanceMeter\PerformanceMeter;

+PerformanceMeter::reset();
 PerformanceMeter::setPointer('boot');

 for ($i = 0; $i < 1_000_000; $i++) {
     hash('sha256', (string) $i);
 }

 PerformanceMeter::setPointer('done');

-printf(
-    "elapsed=%ss memory=%s\n",
-    PerformanceMeter::elapsedTime('boot', 'doen'), // typo silently returned ~0 on v1
-    PerformanceMeter::memoryUsage('boot', 'done')
-);
+printf(
+    "elapsed=%ss memory=%s peak=%s\n",
+    PerformanceMeter::elapsedTime('boot', 'done'),
+    PerformanceMeter::memoryUsage('boot', 'done'),
+    PerformanceMeter::peakMemoryUsage(),
+);
```

The typo (`'doen'`) was a silent zero on v1; on v2 it would throw, forcing the fix.

## Rollback

If you discover a regression in v2 that v1 did not have, file an issue at [InitPHP/PerformanceMeter](https://github.com/InitPHP/PerformanceMeter/issues) — we want to know. In the meantime, pin the previous major:

```bash
composer require initphp/performance-meter:^1.0
```

v1 will not receive new features, but the package is small enough that it is unlikely to be a blocker.
