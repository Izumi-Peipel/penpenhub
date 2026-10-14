<?php

namespace App\Support;

use App\Models\Outlet;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class SlaCalculator
{
    /**
     * Hitung tenggat respon untuk tiket.
     */
    public static function calculateResponseDue(string $priority, CarbonInterface $from, ?Outlet $outlet = null): CarbonImmutable
    {
        $config = self::configFor($priority);
        $minutes = (int) ($config['response_minutes'] ?? 60);
        $calendar = $config['calendar'] ?? 'operating_hours';

        return self::calculateDue($minutes, $calendar, $from, $outlet);
    }

    /**
     * Hitung tenggat resolusi untuk tiket.
     */
    public static function calculateResolutionDue(string $priority, CarbonInterface $from, ?Outlet $outlet = null): CarbonImmutable
    {
        $config = self::configFor($priority);
        $minutes = (int) ($config['resolution_minutes'] ?? 240);
        $calendar = $config['calendar'] ?? 'operating_hours';

        return self::calculateDue($minutes, $calendar, $from, $outlet);
    }

    /**
     * Perpanjang tenggat resolusi akibat jeda (mis. status 'menunggu_klien').
     */
    public static function extendResolutionDueForPause(
        Ticket $ticket,
        CarbonInterface $pausedAt,
        CarbonInterface $resumedAt
    ): ?CarbonImmutable {
        if ($ticket->sla_resolution_due_at === null) {
            return null;
        }

        $config = self::configFor($ticket->priority);
        $calendar = $config['calendar'] ?? 'operating_hours';
        $timezone = self::timezoneFor($ticket->outlet);

        $paused = CarbonImmutable::instance($pausedAt)->setTimezone($timezone);
        $resumed = CarbonImmutable::instance($resumedAt)->setTimezone($timezone);

        if ($resumed <= $paused) {
            return CarbonImmutable::instance($ticket->sla_resolution_due_at);
        }

        if ($calendar === '24x7') {
            $seconds = $resumed->diffInSeconds($paused);

            return CarbonImmutable::instance($ticket->sla_resolution_due_at)
                ->addSeconds($seconds)
                ->setTimezone(config('app.timezone', 'UTC'));
        }

        $operatingHours = self::operatingHoursFor($ticket->outlet);
        $pausedOperatingMinutes = self::operatingMinutesBetween($paused, $resumed, $operatingHours, $timezone);

        if ($pausedOperatingMinutes <= 0) {
            return CarbonImmutable::instance($ticket->sla_resolution_due_at)->setTimezone(config('app.timezone', 'UTC'));
        }

        return $operatingHours->addOperatingMinutes($ticket->sla_resolution_due_at, $pausedOperatingMinutes, $timezone)
            ->setTimezone(config('app.timezone', 'UTC'));
    }

    /**
     * Menghitung total menit kerja antara dua titik waktu.
     */
    public static function operatingMinutesBetween(
        CarbonInterface $start,
        CarbonInterface $end,
        OperatingHours $operatingHours,
        string $timezone = 'Asia/Jakarta'
    ): int {
        $from = CarbonImmutable::instance($start)->setTimezone($timezone);
        $to = CarbonImmutable::instance($end)->setTimezone($timezone);

        if ($to <= $from) {
            return 0;
        }

        if ($operatingHours->isEmpty()) {
            return (int) $from->diffInMinutes($to);
        }

        $minutes = 0;
        $dayCursor = $from->startOfDay();
        $endDay = $to->startOfDay()->addDay();

        while ($dayCursor < $endDay) {
            foreach ($operatingHours->intervalsForDate($dayCursor, $timezone) as [$wStart, $wEnd]) {
                if ($wEnd <= $from || $wStart >= $to) {
                    continue;
                }

                $segStart = $from > $wStart ? $from : $wStart;
                $segEnd = $to < $wEnd ? $to : $wEnd;

                if ($segEnd > $segStart) {
                    $minutes += (int) $segStart->diffInMinutes($segEnd);
                }
            }

            $dayCursor = $dayCursor->addDay();
        }

        return $minutes;
    }

    private static function calculateDue(
        int $minutes,
        string $calendar,
        CarbonInterface $from,
        ?Outlet $outlet
    ): CarbonImmutable {
        $timezone = self::timezoneFor($outlet);
        $base = CarbonImmutable::instance($from)->setTimezone($timezone);

        if ($calendar === '24x7') {
            return $base->addMinutes($minutes)->setTimezone(config('app.timezone', 'UTC'));
        }

        $hours = self::operatingHoursFor($outlet);

        return $hours->addOperatingMinutes($base, $minutes, $timezone)
            ->setTimezone(config('app.timezone', 'UTC'));
    }


    public static function operatingHoursFor(?Outlet $outlet): OperatingHours
    {
        if ($outlet !== null) {
            $hours = $outlet->operatingHours();
            if (! $hours->isEmpty()) {
                return $hours;
            }
        }

        $defaultSchedule = config('penpenhub.sla.default_operating_hours', []);

        return new OperatingHours($defaultSchedule);
    }

    public static function timezoneFor(?Outlet $outlet): string
    {
        return $outlet?->timezone ?: config('penpenhub.sla.default_timezone', 'Asia/Jakarta');
    }

    private static function configFor(string $priority): array
    {
        return config("penpenhub.sla.{$priority}", [
            'response_minutes' => 60,
            'resolution_minutes' => 240,
            'calendar' => 'operating_hours',
        ]);
    }
}
