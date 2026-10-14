<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Jam operasional mingguan. Format:
 *   ['mon' => [['10:00', '22:00']], 'fri' => [['18:00', '02:00']], ...]
 * Jam tutup lebih kecil dari jam buka berarti melewati tengah malam; "24:00" boleh untuk jam tutup.
 */
final class OperatingHours
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    private const TIME = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';

    /** @param array<string, array<int, array{0: string, 1: string}>> $schedule */
    public function __construct(private readonly array $schedule)
    {
    }

    /** @return string[] daftar pesan galat; kosong berarti valid */
    public static function errors(mixed $schedule): array
    {
        if (! is_array($schedule)) {
            return ['Jam operasional harus berupa objek per hari.'];
        }

        $errors = [];

        foreach ($schedule as $day => $intervals) {
            if (! in_array($day, self::DAYS, true)) {
                $errors[] = "Hari tidak dikenal: {$day}.";

                continue;
            }

            if (! is_array($intervals) || count($intervals) > 3) {
                $errors[] = "Interval hari {$day} tidak valid (maksimal 3).";

                continue;
            }

            foreach ($intervals as $interval) {
                if (
                    ! is_array($interval) || count($interval) !== 2
                    || ! self::validTime($interval[0] ?? null, false)
                    || ! self::validTime($interval[1] ?? null, true)
                ) {
                    $errors[] = "Format jam {$day} harus [\"HH:MM\", \"HH:MM\"].";

                    continue;
                }

                if (self::minutes($interval[0]) === self::minutes($interval[1])) {
                    $errors[] = "Jam buka dan tutup {$day} tidak boleh sama.";
                }
            }
        }

        return $errors;
    }

    public function isOpenAt(CarbonInterface $at, string $timezone = 'Asia/Jakarta'): bool
    {
        $local = CarbonImmutable::instance($at)->setTimezone($timezone);
        $minute = $local->hour * 60 + $local->minute;
        $today = self::DAYS[$local->dayOfWeekIso - 1];
        $yesterday = self::DAYS[($local->dayOfWeekIso + 5) % 7];

        foreach ($this->schedule[$today] ?? [] as [$open, $close]) {
            $o = self::minutes($open);
            $c = self::minutes($close);

            // Jika melewati tengah malam, bagian setelah 00:00 dihitung dari jadwal kemarin.
            if ($c > $o ? ($minute >= $o && $minute < $c) : $minute >= $o) {
                return true;
            }
        }

        foreach ($this->schedule[$yesterday] ?? [] as [$open, $close]) {
            if (self::minutes($close) < self::minutes($open) && $minute < self::minutes($close)) {
                return true;
            }
        }

        return false;
    }

    public function schedule(): array
    {
        return $this->schedule;
    }

    public function isEmpty(): bool
    {
        foreach ($this->schedule as $intervals) {
            if (! empty($intervals)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mengembalikan interval buka pada hari tertentu (dalam pasangan [CarbonImmutable $start, CarbonImmutable $end]).
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function intervalsForDate(CarbonInterface $date, string $timezone = 'Asia/Jakarta'): array
    {
        $local = CarbonImmutable::instance($date)->setTimezone($timezone)->startOfDay();
        $today = self::DAYS[$local->dayOfWeekIso - 1];
        $yesterday = self::DAYS[($local->dayOfWeekIso + 5) % 7];

        $windows = [];

        // Interval dari kemarin yang melewati tengah malam (sebelum 00:00 s.d. jam tutup pagi ini)
        foreach ($this->schedule[$yesterday] ?? [] as [$open, $close]) {
            if (self::minutes($close) < self::minutes($open)) {
                $closeTime = self::makeTime($local, $close);
                $windows[] = [$local, $closeTime];
            }
        }

        // Interval yang dimulai hari ini
        foreach ($this->schedule[$today] ?? [] as [$open, $close]) {
            $openTime = self::makeTime($local, $open);
            if ($close === '24:00') {
                $closeTime = $local->addDay()->startOfDay();
            } elseif (self::minutes($close) < self::minutes($open)) {
                $closeTime = self::makeTime($local->addDay(), $close);
            } else {
                $closeTime = self::makeTime($local, $close);
            }
            $windows[] = [$openTime, $closeTime];
        }

        usort($windows, fn ($a, $b) => $a[0]->getTimestamp() <=> $b[0]->getTimestamp());

        return $windows;
    }

    /**
     * Menambahkan sejumlah menit kerja operasional dari waktu acuan.
     */
    public function addOperatingMinutes(CarbonInterface $from, int $minutes, string $timezone = 'Asia/Jakarta'): CarbonImmutable
    {
        if ($minutes <= 0) {
            return CarbonImmutable::instance($from)->setTimezone($timezone);
        }

        if ($this->isEmpty()) {
            return CarbonImmutable::instance($from)->setTimezone($timezone)->addMinutes($minutes);
        }

        $current = CarbonImmutable::instance($from)->setTimezone($timezone);
        $remaining = $minutes;
        $maxDays = 30;
        $dayCursor = $current->startOfDay();

        for ($d = 0; $d < $maxDays; $d++) {
            $windows = $this->intervalsForDate($dayCursor, $timezone);

            foreach ($windows as [$start, $end]) {
                if ($end <= $current) {
                    continue;
                }

                if ($current < $start) {
                    $current = $start;
                }

                $available = (int) $current->diffInMinutes($end);
                if ($available <= 0) {
                    continue;
                }

                if ($remaining <= $available) {
                    return $current->addMinutes($remaining);
                }

                $remaining -= $available;
                $current = $end;
            }

            $dayCursor = $dayCursor->addDay();
        }

        return $current->addMinutes($remaining);
    }

    private static function makeTime(CarbonImmutable $base, string $time): CarbonImmutable
    {
        [$h, $m] = explode(':', $time);

        return $base->setHour((int) $h)->setMinute((int) $m)->setSecond(0);
    }

    private static function validTime(mixed $value, bool $allow24): bool
    {
        return is_string($value) && (preg_match(self::TIME, $value) === 1 || ($allow24 && $value === '24:00'));
    }

    private static function minutes(string $time): int
    {
        [$h, $m] = explode(':', $time);

        return (int) $h * 60 + (int) $m;
    }
}

