<?php
declare(strict_types=1);

namespace App\Services\Sla;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class BusinessTimeCalculator
{
    /**
     * @param list<string> $holidays YYYY-MM-DD
     */
    public function __construct(
        private string $businessStart = '08:00:00',
        private string $businessEnd = '17:00:00',
        private string $timezone = 'America/Bogota',
        private array $holidays = []
    ) {
    }

    public function businessMinutesBetween(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): int {
        $tz = new DateTimeZone($this->timezone);
        $start = $this->asBusinessLocalTime($start, $tz);
        $end = $this->asBusinessLocalTime($end, $tz);

        if ($end <= $start) {
            return 0;
        }

        $totalSeconds = 0;
        $day = $start->setTime(0, 0, 0);
        $lastDay = $end->setTime(0, 0, 0);

        while ($day <= $lastDay) {
            if ($this->isBusinessDay($day)) {
                $windowStart = $this->atBusinessTime($day, $this->businessStart);
                $windowEnd = $this->atBusinessTime($day, $this->businessEnd);

                $from = $start > $windowStart ? $start : $windowStart;
                $to = $end < $windowEnd ? $end : $windowEnd;

                if ($to > $from) {
                    $totalSeconds += $to->getTimestamp() - $from->getTimestamp();
                }
            }

            $day = $day->add(new DateInterval('P1D'));
        }

        return (int)floor($totalSeconds / 60);
    }

    public function addBusinessMinutes(
        DateTimeImmutable $start,
        int $minutes
    ): DateTimeImmutable {
        $minutes = max(0, $minutes);
        $tz = new DateTimeZone($this->timezone);
        $cursor = $this->asBusinessLocalTime($start, $tz);

        if ($minutes === 0) {
            return $cursor;
        }

        $cursor = $this->normalizeToBusinessWindow($cursor);
        $remaining = $minutes;

        while ($remaining > 0) {
            $dayStart = $this->atBusinessTime($cursor, $this->businessStart);
            $dayEnd = $this->atBusinessTime($cursor, $this->businessEnd);

            if (!$this->isBusinessDay($cursor) || $cursor >= $dayEnd) {
                $cursor = $this->nextBusinessStart($cursor);
                continue;
            }

            if ($cursor < $dayStart) {
                $cursor = $dayStart;
            }

            $availableMinutes = (int)floor(
                ($dayEnd->getTimestamp() - $cursor->getTimestamp()) / 60
            );

            if ($remaining <= $availableMinutes) {
                return $cursor->add(new DateInterval('PT' . $remaining . 'M'));
            }

            $remaining -= $availableMinutes;
            $cursor = $this->nextBusinessStart($cursor);
        }

        return $cursor;
    }

    private function asBusinessLocalTime(
        DateTimeImmutable $value,
        DateTimeZone $timezone
    ): DateTimeImmutable {
        $local = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $value->format('Y-m-d H:i:s'),
            $timezone
        );

        if ($local === false) {
            throw new \RuntimeException('No fue posible normalizar la fecha de negocio.');
        }

        return $local;
    }

    private function normalizeToBusinessWindow(
        DateTimeImmutable $value
    ): DateTimeImmutable {
        if (!$this->isBusinessDay($value)) {
            return $this->nextBusinessStart($value);
        }

        $start = $this->atBusinessTime($value, $this->businessStart);
        $end = $this->atBusinessTime($value, $this->businessEnd);

        if ($value < $start) {
            return $start;
        }

        if ($value >= $end) {
            return $this->nextBusinessStart($value);
        }

        return $value;
    }

    private function nextBusinessStart(
        DateTimeImmutable $from
    ): DateTimeImmutable {
        $day = $from->setTime(0, 0, 0)->add(new DateInterval('P1D'));

        while (!$this->isBusinessDay($day)) {
            $day = $day->add(new DateInterval('P1D'));
        }

        return $this->atBusinessTime($day, $this->businessStart);
    }

    private function isBusinessDay(DateTimeImmutable $day): bool
    {
        $isoDay = (int)$day->format('N');

        if ($isoDay >= 6) {
            return false;
        }

        return !in_array($day->format('Y-m-d'), $this->holidays, true);
    }

    private function atBusinessTime(
        DateTimeImmutable $day,
        string $time
    ): DateTimeImmutable {
        [$hour, $minute, $second] = array_map(
            'intval',
            explode(':', $time)
        );

        return $day->setTime($hour, $minute, $second);
    }
}
