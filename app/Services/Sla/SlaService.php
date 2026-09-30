<?php
declare(strict_types=1);

namespace App\Services\Sla;

use App\Repositories\SlaRepository;
use DateTimeImmutable;
use DateTimeZone;

final class SlaService
{
    /** @var array<string,mixed> */
    private array $policy;

    private BusinessTimeCalculator $calculator;

    public function __construct(private SlaRepository $repository)
    {
        $this->policy = $repository->activePolicy();

        $this->calculator = new BusinessTimeCalculator(
            (string)$this->policy['business_start'],
            (string)$this->policy['business_end'],
            (string)$this->policy['timezone'],
            $repository->activeHolidays()
        );
    }

    /**
     * @param array<string,mixed> $case
     * @return array<string,mixed>
     */
    public function snapshot(
        array $case,
        ?DateTimeImmutable $now = null
    ): array {
        $tz = new DateTimeZone((string)$this->policy['timezone']);
        $startRaw = (string)($case['radicated_at'] ?: $case['created_at']);

        $start = new DateTimeImmutable($startRaw, $tz);
        $end = $case['closed_at']
            ? new DateTimeImmutable((string)$case['closed_at'], $tz)
            : ($now ?? new DateTimeImmutable('now', $tz));

        $elapsed = $this->calculator->businessMinutesBetween($start, $end);
        $due = $this->calculator->addBusinessMinutes(
            $start,
            (int)$this->policy['target_minutes']
        );

        $status = $this->statusForMinutes($elapsed);

        return [
            'policy_id'=>(int)$this->policy['id'],
            'elapsed_minutes'=>$elapsed,
            'elapsed_label'=>$this->minutesLabel($elapsed),
            'due_at'=>$due->format('Y-m-d H:i:s'),
            'status'=>$status,
            'target_minutes'=>(int)$this->policy['target_minutes'],
            'remaining_minutes'=>max(
                0,
                (int)$this->policy['target_minutes'] - $elapsed
            ),
        ];
    }

    /** @return array{processed:int,alerts_touched:int} */
    public function evaluateOpenCases(): array
    {
        $processed = 0;
        $openedAlerts = 0;

        foreach ($this->repository->openCases() as $case) {
            $snapshot = $this->snapshot($case);
            $caseId = (int)$case['id'];

            $this->repository->persistSnapshot($caseId, $snapshot);
            $openedAlerts += $this->syncAlerts($case, $snapshot);
            $processed++;
        }

        return [
            'processed'=>$processed,
            'alerts_touched'=>$openedAlerts,
        ];
    }

    /**
     * @param array<string,mixed> $case
     * @param array<string,mixed> $snapshot
     */
    private function syncAlerts(array $case, array $snapshot): int
    {
        $caseId = (int)$case['id'];
        $elapsed = (int)$snapshot['elapsed_minutes'];
        $status = (string)$snapshot['status'];
        $touched = 0;

        $noManagementThreshold =
            (int)$this->policy['no_management_alert_minutes'];

        if (
            empty($case['first_management_at'])
            && $elapsed >= $noManagementThreshold
        ) {
            $this->repository->upsertAlert(
                $caseId,
                'NO_MANAGEMENT',
                'WARNING',
                'Caso sin gestión',
                sprintf(
                    'El caso lleva %s hábiles sin registrar su primera gestión.',
                    $this->minutesLabel($elapsed)
                )
            );
            $touched++;
        } else {
            $this->repository->resolveAlert($caseId, 'NO_MANAGEMENT');
        }

        if ($status === 'RED') {
            $this->repository->upsertAlert(
                $caseId,
                'NEAR_SLA',
                'WARNING',
                'ANS próximo a vencer',
                sprintf(
                    'Restan aproximadamente %s hábiles para el vencimiento.',
                    $this->minutesLabel((int)$snapshot['remaining_minutes'])
                )
            );
            $touched++;
        } else {
            $this->repository->resolveAlert($caseId, 'NEAR_SLA');
        }

        if ($status === 'BREACHED') {
            $this->repository->upsertAlert(
                $caseId,
                'SLA_BREACHED',
                'CRITICAL',
                'ANS vencido',
                sprintf(
                    'El caso superó el ANS de %s hábiles.',
                    $this->minutesLabel((int)$snapshot['target_minutes'])
                )
            );
            $touched++;
        } else {
            $this->repository->resolveAlert($caseId, 'SLA_BREACHED');
        }

        return $touched;
    }

    private function statusForMinutes(int $elapsed): string
    {
        if ($elapsed >= (int)$this->policy['target_minutes']) {
            return 'BREACHED';
        }

        if ($elapsed >= (int)$this->policy['red_from_minutes']) {
            return 'RED';
        }

        if ($elapsed >= (int)$this->policy['green_until_minutes']) {
            return 'YELLOW';
        }

        return 'GREEN';
    }

    private function minutesLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours === 0) {
            return $remaining . ' min';
        }

        if ($remaining === 0) {
            return $hours . ' h';
        }

        return $hours . ' h ' . $remaining . ' min';
    }
}
