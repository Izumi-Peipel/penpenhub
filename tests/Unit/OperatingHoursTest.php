<?php

namespace Tests\Unit;

use App\Support\OperatingHours;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class OperatingHoursTest extends TestCase
{
    private function wib(string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($datetime, 'Asia/Jakarta');
    }

    public function test_regular_hours(): void
    {
        $hours = new OperatingHours(['mon' => [['10:00', '22:00']]]);

        $this->assertTrue($hours->isOpenAt($this->wib('2026-10-05 12:10'))); // Senin
        $this->assertTrue($hours->isOpenAt($this->wib('2026-10-05 10:00')));
        $this->assertFalse($hours->isOpenAt($this->wib('2026-10-05 09:59')));
        $this->assertFalse($hours->isOpenAt($this->wib('2026-10-05 22:00')));
        $this->assertFalse($hours->isOpenAt($this->wib('2026-10-06 12:00'))); // Selasa tutup
    }

    public function test_hours_crossing_midnight(): void
    {
        $hours = new OperatingHours(['fri' => [['18:00', '02:00']]]);

        $this->assertFalse($hours->isOpenAt($this->wib('2026-10-09 17:59'))); // Jumat
        $this->assertTrue($hours->isOpenAt($this->wib('2026-10-09 23:00')));
        $this->assertTrue($hours->isOpenAt($this->wib('2026-10-10 01:59'))); // Sabtu dini hari
        $this->assertFalse($hours->isOpenAt($this->wib('2026-10-10 02:00')));
        $this->assertFalse($hours->isOpenAt($this->wib('2026-10-10 03:00')));
    }

    public function test_midnight_as_24_00(): void
    {
        $hours = new OperatingHours(['sun' => [['10:00', '24:00']]]);

        $this->assertTrue($hours->isOpenAt($this->wib('2026-10-04 23:59'))); // Minggu
        $this->assertFalse($hours->isOpenAt($this->wib('2026-10-05 00:00')));
    }

    public function test_timezone_conversion(): void
    {
        $hours = new OperatingHours(['mon' => [['10:00', '22:00']]]);
        $instant = CarbonImmutable::parse('2026-10-05 04:00', 'UTC'); // 11:00 WIB

        $this->assertTrue($hours->isOpenAt($instant, 'Asia/Jakarta'));
        $this->assertFalse($hours->isOpenAt($instant, 'UTC'));
    }

    public function test_validation(): void
    {
        $this->assertSame([], OperatingHours::errors(['mon' => [['10:00', '22:00']], 'fri' => [['18:00', '02:00']]]));
        $this->assertSame([], OperatingHours::errors(['sun' => [['10:00', '24:00']]]));

        $this->assertNotEmpty(OperatingHours::errors('bukan array'));
        $this->assertNotEmpty(OperatingHours::errors(['xyz' => [['10:00', '22:00']]]));
        $this->assertNotEmpty(OperatingHours::errors(['mon' => [['25:00', '26:00']]]));
        $this->assertNotEmpty(OperatingHours::errors(['mon' => [['10:00', '10:00']]]));
        $this->assertNotEmpty(OperatingHours::errors(['mon' => [['24:00', '10:00']]]));
        $this->assertNotEmpty(OperatingHours::errors(['mon' => [['08:00', '09:00'], ['10:00', '11:00'], ['12:00', '13:00'], ['14:00', '15:00']]]));
    }
}
