<?php

namespace App\Support;

use App\Models\Outlet;
use Carbon\CarbonInterface;

/** Lampiran A butir 4: saran prioritas otomatis berdasar kategori, gejala, dan jam operasional. */
final class PrioritySuggester
{
    public static function basePriority(string $category, ?string $symptom): string
    {
        $cat = config("penpenhub.ticket_categories.{$category}");

        if ($cat === null) {
            return 'P4';
        }

        return $cat['symptoms'][$symptom]['priority'] ?? ($cat['default_priority'] ?? 'P3');
    }

    /** P1 di luar jam operasional outlet perlu konfirmasi dampak kritis dari pelapor. */
    public static function needsCriticalConfirmation(string $category, ?string $symptom, ?Outlet $outlet, CarbonInterface $at): bool
    {
        return self::basePriority($category, $symptom) === 'P1'
            && $outlet !== null
            && ! $outlet->isOpenAt($at);
    }

    public static function suggest(string $category, ?string $symptom, ?Outlet $outlet, CarbonInterface $at, bool $criticalConfirmed = false): string
    {
        if (self::needsCriticalConfirmation($category, $symptom, $outlet, $at) && ! $criticalConfirmed) {
            return 'P2';
        }

        return self::basePriority($category, $symptom);
    }
}
