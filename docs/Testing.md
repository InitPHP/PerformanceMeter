# Testing

PerformanceMeter has 100 % line, method, and class coverage in its own test suite. This page covers (1) how the package is tested upstream, and (2) how to write tests for application code that uses PerformanceMeter.

## The package's own test suite

Run from a checkout of the repository:

```bash
composer install
composer test         # PHPUnit
composer phpstan      # static analysis (level: max)
composer cs-check     # coding standards
composer qa           # all three
```

CI runs the matrix on PHP 8.2, 8.3, 8.4, 8.5, and 8.6 with the highest installable dependencies, plus a PHP 8.2 run with the lowest, and separate static-analysis and coding-standards jobs.

The test file is `tests/PerformanceMeterTest.php`. Notable patterns:

- `setUp()` calls `PerformanceMeter::reset()` so static state cannot bleed across cases.
- Memory assertions use `assertGreaterThanOrEqual` / `assertStringEndsWith` rather than exact-value comparisons, because per-PHP-version allocator behaviour varies.
- Time assertions assert *lower bounds* (`>= 0.005s` for a 5 ms sleep), never upper bounds, to avoid flaky failures on slow CI runners.
- A dedicated regression test (`testMemoryUsageWithNegativeMultiMegabyteDeltaUsesMBSuffix`) locks the v1 → v2 bug fix in place.

## Testing your own code that calls PerformanceMeter

PerformanceMeter is a static class holding shared mutable state. Three principles keep your tests honest:

### 1. Reset between tests

Without this, a test that records `'boot'` leaves that checkpoint visible to the next test. Combined with PHPUnit's `executionOrder="random"`, that produces results that change between runs.

```php
use InitPHP\PerformanceMeter\PerformanceMeter;
use PHPUnit\Framework\TestCase;

abstract class ProfiledTestCase extends TestCase
{
    protected function setUp(): void
    {
        PerformanceMeter::reset();
    }
}
```

Extending a base class keeps the boilerplate to one line per suite.

### 2. Assert behaviour, not numeric values

Do not assert on exact elapsed times or memory deltas. They depend on:

- The PHP version
- The OS and machine speed
- Whether Xdebug / pcov / observability extensions are loaded
- Other processes on the box

Assert what you actually care about:

```php
public function testWorkBlockProducesMeasurableDuration(): void
{
    PerformanceMeter::setPointer('s');
    $this->doWork();
    PerformanceMeter::setPointer('e');

    self::assertGreaterThan(0.0, PerformanceMeter::elapsedTime('s', 'e'));
}
```

If your test asserts that an operation took *at least* 100ms, sleep at least 100ms inside it — but never assert that it took *at most* anything you care about, because CI runners run on shared infrastructure.

### 3. Test the boundary, not the formatter

When your code consumes the output of `memoryUsage()` / `peakMemoryUsage()`, write a small parser at the boundary and test against the parser's output, not the raw `"0.13KB"` string. PerformanceMeter's formatter is fixed and well-tested upstream — testing it again in your suite is redundant and brittle.

```php
public function testWorkBlockAllocatesLessThanFiveMegabytes(): void
{
    PerformanceMeter::setPointer('s');
    $this->doWork();
    PerformanceMeter::setPointer('e');

    $output = PerformanceMeter::memoryUsage('s', 'e');
    $bytes  = $this->parseMemoryString($output);

    self::assertLessThan(5 * 1024 * 1024, $bytes);
}

private function parseMemoryString(string $value): float
{
    if (str_ends_with($value, 'MB')) {
        return (float) substr($value, 0, -2) * 1024 * 1024;
    }
    if (str_ends_with($value, 'KB')) {
        return (float) substr($value, 0, -2) * 1024;
    }
    self::fail("unrecognised memory string: {$value}");
}
```

## Faking PerformanceMeter in unit tests

Because the API is static, classic dependency-injection mocking does not work directly. Two pragmatic options:

### Option A — wrap it

If you need to assert that your code records a checkpoint, wrap PerformanceMeter behind a small interface and inject the wrapper:

```php
interface ProfilerInterface
{
    public function mark(string $name): void;
    public function elapsed(string $start, ?string $end = null): float;
}

final class PerformanceMeterProfiler implements ProfilerInterface
{
    public function mark(string $name): void
    {
        PerformanceMeter::mark($name);
    }

    public function elapsed(string $start, ?string $end = null): float
    {
        return PerformanceMeter::elapsedTime($start, $end);
    }
}
```

Production code depends on the interface; tests pass an in-memory fake.

### Option B — trust the call, assert the side effect

Don't mock at all. Let your code call the real static API, then assert on `PerformanceMeter::has()` / `PerformanceMeter::getPointers()` to verify the expected checkpoints were recorded:

```php
public function testServiceRecordsRequestBoundaries(): void
{
    $this->service->handle($request);

    self::assertTrue(PerformanceMeter::has('request:start'));
    self::assertTrue(PerformanceMeter::has('request:end'));
}
```

This is usually the lower-friction option for tests that simply care that the probes are present.

## Compatibility notes

- Tests using `usleep()` should sleep long enough to defeat scheduler noise — `usleep(5_000)` (5 ms) is a reasonable floor for "non-zero" assertions.
- `memory_get_usage()` figures include the small per-call cost of PHPUnit's own machinery; do not assert specific byte counts.
- PHPUnit 10 introduced attributes such as `#[DataProvider]` as the way to attach metadata to tests; when a test carries attributes, doc-comment annotations on it are ignored (see the [PHPUnit 10.5 attributes reference](https://docs.phpunit.de/en/10.5/attributes.html)). The package's own suite uses the attribute form — copy that pattern if you target PHPUnit 10+.
