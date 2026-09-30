<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sla\BusinessTimeCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BusinessTimeCalculatorTest extends TestCase
{
    public function testCountsOnlyBusinessMinutesInSameDay(): void
    {
        $calc = new BusinessTimeCalculator();

        $minutes = $calc->businessMinutesBetween(
            new DateTimeImmutable('2026-09-30 08:00:00'),
            new DateTimeImmutable('2026-09-30 10:30:00')
        );

        self::assertSame(150, $minutes);
    }

    public function testSkipsNightAndWeekend(): void
    {
        $calc = new BusinessTimeCalculator();

        $minutes = $calc->businessMinutesBetween(
            new DateTimeImmutable('2026-10-02 16:00:00'),
            new DateTimeImmutable('2026-10-05 09:00:00')
        );

        self::assertSame(120, $minutes);
    }

    public function testAddsSixBusinessHoursAcrossDayBoundary(): void
    {
        $calc = new BusinessTimeCalculator();

        $due = $calc->addBusinessMinutes(
            new DateTimeImmutable('2026-09-30 15:00:00'),
            360
        );

        self::assertSame(
            '2026-10-01 12:00:00',
            $due->format('Y-m-d H:i:s')
        );
    }

    public function testStopsAtBusinessEndBeforeWeekend(): void
    {
        $calc = new BusinessTimeCalculator();

        $minutes = $calc->businessMinutesBetween(
            new DateTimeImmutable('2026-10-02 16:30:00'),
            new DateTimeImmutable('2026-10-05 08:30:00')
        );

        self::assertSame(60, $minutes);
    }

    public function testSkipsConfiguredHoliday(): void
    {
        $calc = new BusinessTimeCalculator(
            '08:00:00',
            '17:00:00',
            'America/Bogota',
            ['2026-10-01']
        );

        $due = $calc->addBusinessMinutes(
            new DateTimeImmutable('2026-09-30 16:00:00'),
            120
        );

        self::assertSame(
            '2026-10-02 09:00:00',
            $due->format('Y-m-d H:i:s')
        );
    }

    public function testSixBusinessHoursAreExactly360Minutes(): void
    {
        $calc = new BusinessTimeCalculator();

        $minutes = $calc->businessMinutesBetween(
            new DateTimeImmutable('2026-09-30 08:00:00'),
            new DateTimeImmutable('2026-09-30 14:00:00')
        );

        self::assertSame(360, $minutes);
    }
    public function testSkipsWeekendAndConfiguredHoliday(): void
    {
        $calc = new BusinessTimeCalculator(
            '08:00:00',
            '17:00:00',
            'America/Bogota',
            ['2026-10-12']
        );

        $due = $calc->addBusinessMinutes(
            new DateTimeImmutable('2026-10-09 16:00:00'),
            120
        );

        self::assertSame(
            '2026-10-13 10:00:00',
            $due->format('Y-m-d H:i:s')
        );
    }

}
