<?php

declare(strict_types=1);

namespace InitPHP\PerformanceMeter\Tests;

use InitPHP\PerformanceMeter\Exception\PointerNotFoundException;
use InitPHP\PerformanceMeter\PerformanceMeter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PerformanceMeter::class)]
#[CoversClass(PointerNotFoundException::class)]
final class PerformanceMeterTest extends TestCase
{
    protected function setUp(): void
    {
        PerformanceMeter::reset();
    }

    public function testSetPointerRecordsCheckpoint(): void
    {
        PerformanceMeter::setPointer('alpha');

        self::assertTrue(PerformanceMeter::has('alpha'));
    }

    public function testMarkIsAliasOfSetPointer(): void
    {
        PerformanceMeter::mark('beta');

        self::assertTrue(PerformanceMeter::has('beta'));
    }

    public function testPointerNamesAreCaseInsensitive(): void
    {
        PerformanceMeter::setPointer('MixedCase');

        self::assertTrue(PerformanceMeter::has('mixedcase'));
        self::assertTrue(PerformanceMeter::has('MIXEDCASE'));
        self::assertTrue(PerformanceMeter::has('MixedCase'));
    }

    public function testSetPointerOverwritesExistingEntry(): void
    {
        PerformanceMeter::setPointer('twice');
        $first = PerformanceMeter::getPointers()['twice'];

        usleep(2_000);
        PerformanceMeter::setPointer('twice');
        $second = PerformanceMeter::getPointers()['twice'];

        self::assertGreaterThan($first['time'], $second['time']);
    }

    public function testElapsedTimeBetweenTwoPointersIsNonNegativeAndReflectsSleep(): void
    {
        PerformanceMeter::setPointer('start');
        usleep(5_000); // 5 ms
        PerformanceMeter::setPointer('end');

        $elapsed = PerformanceMeter::elapsedTime('start', 'end', 6);

        self::assertGreaterThanOrEqual(0.005, $elapsed);
        self::assertLessThan(1.0, $elapsed, 'A 5ms sleep must not produce a 1s+ measurement.');
    }

    public function testElapsedTimeWithNullEndPointMeasuresUpToNow(): void
    {
        PerformanceMeter::setPointer('boot');
        usleep(3_000);

        $elapsed = PerformanceMeter::elapsedTime('boot', null, 6);

        self::assertGreaterThanOrEqual(0.003, $elapsed);
    }

    public function testElapsedTimeWithMissingStartPointThrows(): void
    {
        $this->expectException(PointerNotFoundException::class);
        $this->expectExceptionMessage('"missing"');

        PerformanceMeter::elapsedTime('missing');
    }

    public function testElapsedTimeWithMissingEndPointThrows(): void
    {
        PerformanceMeter::setPointer('present');

        $this->expectException(PointerNotFoundException::class);
        $this->expectExceptionMessage('"typo"');

        PerformanceMeter::elapsedTime('present', 'typo');
    }

    public function testElapsedTimeRespectsDecimalArgument(): void
    {
        PerformanceMeter::setPointer('a');
        usleep(1_500);
        PerformanceMeter::setPointer('b');

        $rounded = PerformanceMeter::elapsedTime('a', 'b', 0);
        $unrounded = PerformanceMeter::elapsedTime('a', 'b', 6);

        self::assertSame((float) (int) $rounded, $rounded, 'decimal=0 must return an integral float.');
        self::assertGreaterThanOrEqual(0.001, $unrounded);
    }

    public function testElapsedTimeRejectsNegativeDecimal(): void
    {
        PerformanceMeter::setPointer('a');
        PerformanceMeter::setPointer('b');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$decimal');

        PerformanceMeter::elapsedTime('a', 'b', -1);
    }

    public function testMemoryUsageReturnsKBSuffixForSmallDelta(): void
    {
        PerformanceMeter::setPointer('m1');
        $payload = str_repeat('x', 4_096); // ~4 KB
        PerformanceMeter::setPointer('m2');

        $output = PerformanceMeter::memoryUsage('m1', 'm2');

        self::assertStringEndsWith('KB', $output);
        self::assertNotSame($payload, ''); // keep reference
    }

    public function testMemoryUsageReturnsMBSuffixForLargeDelta(): void
    {
        PerformanceMeter::setPointer('m1');
        $payload = str_repeat('x', 2 * 1024 * 1024); // 2 MB
        PerformanceMeter::setPointer('m2');

        $output = PerformanceMeter::memoryUsage('m1', 'm2');

        self::assertStringEndsWith('MB', $output);
        self::assertNotSame($payload, '');
    }

    /**
     * Regression test for the negative-delta bug in v1: when memory was
     * freed between two checkpoints (delta > 1 MB in absolute value), the
     * formatter would incorrectly report the figure in KB.
     */
    public function testMemoryUsageWithNegativeMultiMegabyteDeltaUsesMBSuffix(): void
    {
        $payload = str_repeat('x', 3 * 1024 * 1024); // 3 MB
        PerformanceMeter::setPointer('before-free');
        unset($payload);
        PerformanceMeter::setPointer('after-free');

        $output = PerformanceMeter::memoryUsage('before-free', 'after-free');

        self::assertStringEndsWith('MB', $output);
        self::assertStringStartsWith('-', $output, 'Freed memory must format as a negative delta.');
    }

    public function testMemoryUsageWithNullEndPointMeasuresUpToNow(): void
    {
        PerformanceMeter::setPointer('mem-start');
        $payload = str_repeat('x', 1_024);

        $output = PerformanceMeter::memoryUsage('mem-start');

        self::assertMatchesRegularExpression('/^-?\d+(\.\d+)?(KB|MB)$/', $output);
        self::assertNotSame($payload, '');
    }

    public function testMemoryUsageWithMissingStartPointThrows(): void
    {
        $this->expectException(PointerNotFoundException::class);

        PerformanceMeter::memoryUsage('nope');
    }

    public function testMemoryUsageWithMissingEndPointThrows(): void
    {
        PerformanceMeter::setPointer('here');

        $this->expectException(PointerNotFoundException::class);

        PerformanceMeter::memoryUsage('here', 'nowhere');
    }

    public function testMemoryUsageRealUsageFlagIsAccepted(): void
    {
        PerformanceMeter::setPointer('r1');
        PerformanceMeter::setPointer('r2');

        $output = PerformanceMeter::memoryUsage('r1', 'r2', 2, true);

        self::assertMatchesRegularExpression('/^-?\d+(\.\d+)?(KB|MB)$/', $output);
    }

    public function testMemoryUsageRejectsNegativeDecimal(): void
    {
        PerformanceMeter::setPointer('a');
        PerformanceMeter::setPointer('b');

        $this->expectException(InvalidArgumentException::class);

        PerformanceMeter::memoryUsage('a', 'b', -2);
    }

    public function testPeakMemoryUsageFormatsAsKBorMB(): void
    {
        $output = PerformanceMeter::peakMemoryUsage();

        self::assertMatchesRegularExpression('/^\d+(\.\d+)?(KB|MB)$/', $output);
    }

    public function testPeakMemoryUsageRealUsageFlagIsAccepted(): void
    {
        $output = PerformanceMeter::peakMemoryUsage(2, true);

        self::assertMatchesRegularExpression('/^\d+(\.\d+)?(KB|MB)$/', $output);
    }

    public function testPeakMemoryUsageRejectsNegativeDecimal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PerformanceMeter::peakMemoryUsage(-1);
    }

    public function testHasReturnsFalseForUnknownPointer(): void
    {
        self::assertFalse(PerformanceMeter::has('ghost'));
    }

    public function testHasIsCaseInsensitive(): void
    {
        PerformanceMeter::setPointer('Section-1');

        self::assertTrue(PerformanceMeter::has('section-1'));
        self::assertTrue(PerformanceMeter::has('SECTION-1'));
    }

    public function testGetPointersReturnsSnapshotKeyedByLowercaseName(): void
    {
        PerformanceMeter::setPointer('Foo');
        PerformanceMeter::setPointer('BAR');

        $pointers = PerformanceMeter::getPointers();

        self::assertArrayHasKey('foo', $pointers);
        self::assertArrayHasKey('bar', $pointers);
        self::assertArrayHasKey('time', $pointers['foo']);
        self::assertArrayHasKey('memory', $pointers['foo']);
        self::assertArrayHasKey('memoryReal', $pointers['foo']);
    }

    public function testGetPointersReturnsCopyThatDoesNotMutateInternalState(): void
    {
        PerformanceMeter::setPointer('immutable');

        $snapshot = PerformanceMeter::getPointers();
        $snapshot['immutable']['time'] = 0.0;
        unset($snapshot['immutable']);

        self::assertTrue(PerformanceMeter::has('immutable'));
        self::assertNotSame(0.0, PerformanceMeter::getPointers()['immutable']['time']);
    }

    public function testResetClearsAllPointers(): void
    {
        PerformanceMeter::setPointer('one');
        PerformanceMeter::setPointer('two');

        PerformanceMeter::reset();

        self::assertSame([], PerformanceMeter::getPointers());
        self::assertFalse(PerformanceMeter::has('one'));
        self::assertFalse(PerformanceMeter::has('two'));
    }

    public function testResetIsIdempotent(): void
    {
        PerformanceMeter::reset();
        PerformanceMeter::reset();

        self::assertSame([], PerformanceMeter::getPointers());
    }

    public function testElapsedTimeBetweenIdenticalPointerNameIsZero(): void
    {
        PerformanceMeter::setPointer('same');

        self::assertSame(0.0, PerformanceMeter::elapsedTime('same', 'same', 6));
    }

    public function testEmptyStringIsAValidPointerName(): void
    {
        PerformanceMeter::setPointer('');

        self::assertTrue(PerformanceMeter::has(''));
        self::assertIsFloat(PerformanceMeter::elapsedTime(''));
    }

    /**
     * @param non-empty-string $expectedSuffix
     */
    #[DataProvider('byteFormattingProvider')]
    public function testFormatterBoundaryBehaviour(int $payloadBytes, string $expectedSuffix): void
    {
        PerformanceMeter::setPointer('p1');
        $payload = str_repeat('x', $payloadBytes);
        PerformanceMeter::setPointer('p2');

        $output = PerformanceMeter::memoryUsage('p1', 'p2');

        self::assertStringEndsWith($expectedSuffix, $output);
        self::assertNotSame($payload, '');
    }

    /**
     * @return iterable<string, array{int, non-empty-string}>
     */
    public static function byteFormattingProvider(): iterable
    {
        yield 'sub-MB stays in KB' => [512 * 1024, 'KB'];   // 512 KB
        yield 'multi-MB switches to MB' => [4 * 1024 * 1024, 'MB']; // 4 MB
    }

    public function testPointerNotFoundExceptionIsInvalidArgumentException(): void
    {
        $exception = PointerNotFoundException::forName('x');

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('"x"', $exception->getMessage());
    }
}
