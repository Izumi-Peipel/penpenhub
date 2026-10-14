<?php

namespace Tests\Unit;

use App\Models\Outlet;
use App\Support\OperatingHours;
use App\Support\SlaCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SlaCalculatorTest extends TestCase
{
    private function wib(string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($datetime, 'Asia/Jakarta');
    }

    public function test_p1_24x7_continuous_due_calculation(): void
    {
        // P1 respon 15 menit, resolusi 120 menit (2 jam) 24x7
        $now = $this->wib('2026-10-05 23:50:00'); // Senin malam menjelang tengah malam

        $resp = SlaCalculator::calculateResponseDue('P1', $now);
        $reso = SlaCalculator::calculateResolutionDue('P1', $now);

        $this->assertSame('2026-10-06 00:05:00', $resp->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-06 01:50:00', $reso->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));

    }

    public function test_operating_hours_calculation_within_shift(): void
    {
        // Outlet buka 10:00 - 22:00
        $hours = new OperatingHours(['mon' => [['10:00', '22:00']]]);

        $due = $hours->addOperatingMinutes($this->wib('2026-10-05 12:00:00'), 120);
        $this->assertSame('2026-10-05 14:00:00', $due->format('Y-m-d H:i:s'));
    }

    public function test_operating_hours_calculation_starting_when_closed(): void
    {
        // Outlet buka 10:00 - 22:00. Tiket dibuat jam 08:00 saat tutup.
        $hours = new OperatingHours(['mon' => [['10:00', '22:00']]]);

        // 30 menit setelah buka
        $due = $hours->addOperatingMinutes($this->wib('2026-10-05 08:00:00'), 30);
        $this->assertSame('2026-10-05 10:30:00', $due->format('Y-m-d H:i:s'));
    }

    public function test_operating_hours_calculation_crossing_days(): void
    {
        // Senin 10:00 - 22:00, Selasa 10:00 - 22:00
        $hours = new OperatingHours([
            'mon' => [['10:00', '22:00']],
            'tue' => [['10:00', '22:00']],
        ]);

        // Dibuat Senin 21:00 (tersisa 60 menit hari Senin). Target 120 menit.
        // Sisa 60 menit berlanjut ke Selasa 10:00 -> selesai Selasa 11:00.
        $due = $hours->addOperatingMinutes($this->wib('2026-10-05 21:00:00'), 120);
        $this->assertSame('2026-10-06 11:00:00', $due->format('Y-m-d H:i:s'));
    }

    public function test_operating_hours_crossing_midnight(): void
    {
        // Jumat 18:00 - 02:00 (Sabtu pagi)
        $hours = new OperatingHours([
            'fri' => [['18:00', '02:00']],
        ]);

        // Dibuat Jumat 23:00. Target 180 menit (3 jam).
        // 23:00 + 3 jam = Sabtu 02:00.
        $due = $hours->addOperatingMinutes($this->wib('2026-10-09 23:00:00'), 180);
        $this->assertSame('2026-10-10 02:00:00', $due->format('Y-m-d H:i:s'));
    }

    public function test_operating_minutes_between(): void
    {
        $hours = new OperatingHours([
            'mon' => [['10:00', '22:00']],
            'tue' => [['10:00', '22:00']],
        ]);

        // Dari Senin 21:00 sampai Selasa 11:00:
        // Senin 21:00 - 22:00 = 60 menit
        // Selasa 10:00 - 11:00 = 60 menit
        // Total = 120 menit
        $minutes = SlaCalculator::operatingMinutesBetween(
            $this->wib('2026-10-05 21:00:00'),
            $this->wib('2026-10-06 11:00:00'),
            $hours
        );

        $this->assertSame(120, $minutes);
    }
}
