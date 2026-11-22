# Recipe — Cron Job Timing Log

Most cron jobs deserve exactly one line of operational visibility: how long did it take, how much memory did it touch, and was it successful? PerformanceMeter gives you that line without adding a metrics stack.

## The script

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use InitPHP\PerformanceMeter\PerformanceMeter;

PerformanceMeter::setPointer('boot');

$exitCode = 0;
$processed = 0;

try {
    $processed = run_nightly_export();
} catch (\Throwable $e) {
    $exitCode = 1;
    error_log("nightly-export failed: " . $e->getMessage());
}

file_put_contents(
    __DIR__ . '/var/log/nightly-export.log',
    sprintf(
        "[%s] status=%s rows=%d elapsed=%ss peak=%s\n",
        date(DATE_ATOM),
        $exitCode === 0 ? 'ok' : 'fail',
        $processed,
        PerformanceMeter::elapsedTime('boot'),
        PerformanceMeter::peakMemoryUsage(),
    ),
    FILE_APPEND,
);

exit($exitCode);
```

A sample week of output looks like:

```
[2026-05-19T02:00:01+00:00] status=ok   rows=14823  elapsed=12.4731s peak=64.32MB
[2026-05-20T02:00:01+00:00] status=ok   rows=15110  elapsed=12.8514s peak=64.51MB
[2026-05-21T02:00:01+00:00] status=ok   rows=15407  elapsed=13.2120s peak=64.77MB
[2026-05-22T02:00:01+00:00] status=fail rows=0      elapsed=0.0421s  peak=8.92MB
[2026-05-23T02:00:01+00:00] status=ok   rows=15901  elapsed=13.6014s peak=65.04MB
```

`grep status=fail nightly-export.log` is now a useful health check. `awk` over the `elapsed=` column gives you a duration trend.

## Why this shape

- **Open-ended `elapsedTime('boot')`** — no need to record a second checkpoint; the implicit "now" at the moment of the call is exactly the end of the run.
- **`peakMemoryUsage()` is stateless** — does not depend on any checkpoint, just asks PHP for its high-water mark.
- **`try`/`catch` around the workload** — the log line still lands even when the job fails, with the error visible in the standard error log via `error_log()`.
- **Exit code reflects success** — `cron`'s built-in `MAILTO` and most monitoring systems treat non-zero exit codes as failures.

## Variant — log to syslog instead of a file

```php
openlog('nightly-export', LOG_PID, LOG_CRON);
syslog(LOG_INFO, sprintf(
    'status=%s rows=%d elapsed=%ss peak=%s',
    $exitCode === 0 ? 'ok' : 'fail',
    $processed,
    PerformanceMeter::elapsedTime('boot'),
    PerformanceMeter::peakMemoryUsage(),
));
closelog();
```

syslog handles rotation, structured fields (in modern syslog daemons), and remote forwarding for you.

## Variant — emit structured JSON for log aggregators

```php
file_put_contents(
    __DIR__ . '/var/log/nightly-export.json',
    json_encode([
        'ts'        => date(DATE_ATOM),
        'job'       => 'nightly-export',
        'status'    => $exitCode === 0 ? 'ok' : 'fail',
        'rows'      => $processed,
        'elapsed_s' => PerformanceMeter::elapsedTime('boot'),
        'peak_mem'  => PerformanceMeter::peakMemoryUsage(),
    ], JSON_THROW_ON_ERROR) . "\n",
    FILE_APPEND,
);
```

One JSON object per line is what Loki, Vector, Filebeat, Promtail, and friends consume natively.

## Variant — guard against unbounded runtime

Combine PerformanceMeter with `set_time_limit()` for a soft and hard cap:

```php
set_time_limit(15 * 60); // hard cap: 15 minutes — PHP kills the process

PerformanceMeter::setPointer('boot');
run_nightly_export();

if (PerformanceMeter::elapsedTime('boot') > 10 * 60) {
    error_log('nightly-export: soft cap exceeded — investigate');
}
```

The hard cap protects the system; the soft cap signals "this is taking longer than expected, but did finish" without killing the process.

## See also

- [CLI Benchmark Script](Recipe-CLI-Benchmark.md) — the same pattern for one-off benchmarks.
- [API Reference → `peakMemoryUsage()`](API-Reference.md#peakmemoryusage) — what the figure actually represents.
- [Recipes → `register_shutdown_function()` tip](Recipes.md#use-register_shutdown_function-for-run-to-end-totals) — for measurements that survive `exit` / `die`.
