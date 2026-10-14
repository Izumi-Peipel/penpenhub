<?php

namespace Tests\Unit;

use App\Support\OperatingHours;
use App\Support\OperatingHoursRows;
use PHPUnit\Framework\TestCase;

class OperatingHoursRowsTest extends TestCase
{
    public function test_round_trip_between_schedule_and_form_rows(): void
    {
        $schedule = ['mon' => [['10:00', '14:00'], ['17:00', '22:00']], 'fri' => [['18:00', '02:00']]];

        $rows = OperatingHoursRows::toRows($schedule);

        $this->assertCount(3, $rows);
        $this->assertSame(['day' => 'mon', 'open' => '10:00', 'close' => '14:00'], $rows[0]);
        $this->assertSame($schedule, OperatingHoursRows::fromRows($rows));
    }

    public function test_rows_with_seconds_and_uuid_keys_are_normalized(): void
    {
        $schedule = OperatingHoursRows::fromRows([
            'a1b2' => ['day' => 'tue', 'open' => '09:00:00', 'close' => '17:00:00'],
            'c3d4' => ['day' => '', 'open' => '', 'close' => ''], // baris kosong diabaikan
        ]);

        $this->assertSame(['tue' => [['09:00', '17:00']]], $schedule);
        $this->assertSame([], OperatingHours::errors($schedule));
    }

    public function test_empty_or_invalid_input_yields_an_empty_schedule(): void
    {
        $this->assertSame([], OperatingHoursRows::toRows(null));
        $this->assertSame([], OperatingHoursRows::fromRows(null));
    }
}
