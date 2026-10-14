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
