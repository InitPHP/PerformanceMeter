<?php

declare(strict_types=1);

/**
 * This file is part of InitPHP PerformanceMeter.
 *
 * @author    Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright Copyright © 2022 InitPHP
 * @license   https://opensource.org/licenses/MIT  MIT License
 *
 * @link      https://github.com/InitPHP/PerformanceMeter
 */

namespace InitPHP\PerformanceMeter;

use InitPHP\PerformanceMeter\Exception\PointerNotFoundException;
use InvalidArgumentException;

use function abs;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function round;
use function strtolower;

/**
 * Zero-dependency static profiler for measuring elapsed time and memory
 * usage between named checkpoints.
 *
 * Checkpoints are stored in a single class-level registry, so all
 * measurements share the same global scope within a process. This is
 * intentional — it keeps the API as small as possible for one-off
 * benchmarking scripts, CLI tools, and reproduction snippets.
 *
 * If you need isolated scopes, nested sections, or production-grade
 * profiling, prefer `symfony/stopwatch` or a real profiler such as
 * Xdebug, Blackfire, or SPX.
 *
 * @api
 */
final class PerformanceMeter
{
    /**
     * Number of bytes in a kibibyte (KB), used by the formatter.
     */
    private const BYTES_PER_KB = 1024;

    /**
     * Number of bytes in a mebibyte (MB), used by the formatter.
     */
    private const BYTES_PER_MB = 1024 * 1024;

    /**
     * Pointer registry. Each entry is keyed by the lowercased pointer
     * name and holds the timestamp + both memory readings captured at
     * setPointer() time.
     *
     * @var array<string, array{time: float, memory: int, memoryReal: int}>
     */
    private static array $pointers = [];

    /**
     * The class is purely static and must not be instantiated.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * Record a checkpoint under the given name.
     *
     * Captures the current wall-clock time (`microtime(true)`) and both
     * memory readings (`memory_get_usage(false)` and `memory_get_usage(true)`).
     * Names are normalised to lower case, so `"Foo"` and `"foo"` refer to
     * the same checkpoint. Calling this method again with an existing name
     * overwrites the previous reading.
     */
    public static function setPointer(string $name): void
    {
        self::$pointers[strtolower($name)] = self::captureNow();
    }

    /**
     * Alias of {@see self::setPointer()} for readers who prefer
     * stopwatch-style vocabulary.
     */
    public static function mark(string $name): void
    {
        self::setPointer($name);
    }

    /**
     * Measure the elapsed wall-clock time between two checkpoints, in seconds.
     *
     * If `$endPoint` is `null`, the current moment is used as the end of
     * the interval — useful for "since boot" style measurements without
     * having to mark an explicit end.
     *
     * @param string $startPoint Name of the starting checkpoint. Must already exist.
     * @param string|null $endPoint Name of the ending checkpoint, or `null` for "now".
     * @param int $decimal Number of fractional digits to round to. Must be ≥ 0.
     *
     * @throws PointerNotFoundException If `$startPoint` or a non-null `$endPoint` is unknown.
     * @throws InvalidArgumentException If `$decimal` is negative.
     */
    public static function elapsedTime(string $startPoint, ?string $endPoint = null, int $decimal = 4): float
    {
        self::assertDecimal($decimal);

        $start = self::requirePointer($startPoint);
        $end = self::resolveEndPoint($endPoint);

        return round($end['time'] - $start['time'], $decimal);
    }

    /**
     * Measure the memory-usage delta between two checkpoints, formatted
     * as a human-readable string ending in "KB" or "MB".
     *
     * The delta may be negative when memory has been freed between the
     * two checkpoints; the formatted output preserves the sign
     * (e.g. `-1.50MB`).
     *
     * @param string $startPoint Name of the starting checkpoint. Must already exist.
     * @param string|null $endPoint Name of the ending checkpoint, or `null` for "now".
     * @param int $decimal Number of fractional digits to round to. Must be ≥ 0.
     * @param bool $realUsage When `true`, use the system-allocated memory
     *                        (`memory_get_usage(true)`) instead of the
     *                        emalloc-tracked figure.
     *
     * @throws PointerNotFoundException If `$startPoint` or a non-null `$endPoint` is unknown.
     * @throws InvalidArgumentException If `$decimal` is negative.
     */
    public static function memoryUsage(
        string $startPoint,
        ?string $endPoint = null,
        int $decimal = 2,
        bool $realUsage = false,
    ): string {
        self::assertDecimal($decimal);

        $start = self::requirePointer($startPoint);
        $end = self::resolveEndPoint($endPoint);

        $key = $realUsage ? 'memoryReal' : 'memory';
        $delta = $end[$key] - $start[$key];

        return self::formatBytes($delta, $decimal);
    }

    /**
     * Report the peak memory usage of the current process, formatted as
     * a human-readable string. Reflects PHP's `memory_get_peak_usage()`.
     *
     * @param int $decimal Number of fractional digits to round to. Must be ≥ 0.
     * @param bool $realUsage When `true`, use the system-allocated peak.
     *
     * @throws InvalidArgumentException If `$decimal` is negative.
     */
    public static function peakMemoryUsage(int $decimal = 2, bool $realUsage = false): string
    {
        self::assertDecimal($decimal);

        return self::formatBytes(memory_get_peak_usage($realUsage), $decimal);
    }

    /**
     * Determine whether a checkpoint with the given name has been recorded.
     */
    public static function has(string $name): bool
    {
        return isset(self::$pointers[strtolower($name)]);
    }

    /**
     * Return a snapshot of all recorded checkpoints, keyed by name.
     *
     * The returned array is a copy of the internal registry; mutating
     * it will not affect subsequent measurements.
     *
     * @return array<string, array{time: float, memory: int, memoryReal: int}>
     */
    public static function getPointers(): array
    {
        return self::$pointers;
    }

    /**
     * Clear every recorded checkpoint, returning the registry to its
     * initial empty state. Useful in long-running processes and in
     * test suites where measurements must not bleed across cases.
     */
    public static function reset(): void
    {
        self::$pointers = [];
    }

    /**
     * Return the stored snapshot for `$name`, throwing if absent.
     *
     * @return array{time: float, memory: int, memoryReal: int}
     *
     * @throws PointerNotFoundException
     */
    private static function requirePointer(string $name): array
    {
        $key = strtolower($name);

        if (!isset(self::$pointers[$key])) {
            throw PointerNotFoundException::forName($name);
        }

        return self::$pointers[$key];
    }

    /**
     * Resolve the end-of-interval snapshot: either a stored pointer or
     * the current moment when `$name` is `null`.
     *
     * @return array{time: float, memory: int, memoryReal: int}
     *
     * @throws PointerNotFoundException When a non-null name does not exist.
     */
    private static function resolveEndPoint(?string $name): array
    {
        if ($name === null) {
            return self::captureNow();
        }

        return self::requirePointer($name);
    }

    /**
     * Capture the current wall-clock time and memory usage.
     *
     * @return array{time: float, memory: int, memoryReal: int}
     */
    private static function captureNow(): array
    {
        return [
            'time' => microtime(true),
            'memory' => memory_get_usage(false),
            'memoryReal' => memory_get_usage(true),
        ];
    }

    /**
     * Format a byte count as "x.xxKB" or "x.xxMB", preserving sign.
     */
    private static function formatBytes(int $bytes, int $decimal): string
    {
        if (abs($bytes) < self::BYTES_PER_MB) {
            return round($bytes / self::BYTES_PER_KB, $decimal) . 'KB';
        }

        return round($bytes / self::BYTES_PER_MB, $decimal) . 'MB';
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function assertDecimal(int $decimal): void
    {
        if ($decimal < 0) {
            throw new InvalidArgumentException('The $decimal argument must be greater than or equal to 0.');
        }
    }
}
