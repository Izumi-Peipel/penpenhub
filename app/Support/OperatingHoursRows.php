<?php

namespace App\Support;

/** Konversi jadwal mingguan <-> baris formulir (hari, buka, tutup) untuk panel admin. */
final class OperatingHoursRows
{
    /** @return array<string, string> */
    public static function dayLabels(): array
    {
        return [
            'mon' => 'Senin', 'tue' => 'Selasa', 'wed' => 'Rabu', 'thu' => 'Kamis',
            'fri' => 'Jumat', 'sat' => 'Sabtu', 'sun' => 'Minggu',
        ];
    }

    /** @return array<int, array{day: string, open: string, close: string}> */
    public static function toRows(mixed $schedule): array
    {
        $rows = [];

        foreach (is_array($schedule) ? $schedule : [] as $day => $intervals) {
            foreach (is_array($intervals) ? $intervals : [] as $interval) {
                if (is_array($interval) && count($interval) === 2) {
                    $rows[] = ['day' => $day, 'open' => $interval[0], 'close' => $interval[1]];
                }
            }
        }

        return $rows;
    }

    /** @return array<string, array<int, array{0: string, 1: string}>> */
    public static function fromRows(mixed $rows): array
    {
        $schedule = [];

        foreach (is_array($rows) ? array_values($rows) : [] as $row) {
            if (! is_array($row) || empty($row['day']) || empty($row['open']) || empty($row['close'])) {
                continue;
            }

            $schedule[$row['day']][] = [substr((string) $row['open'], 0, 5), substr((string) $row['close'], 0, 5)];
        }

        return $schedule;
    }
}
