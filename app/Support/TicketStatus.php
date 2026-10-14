<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class TicketStatus
{
    public const BARU = 'baru';
    public const DITERIMA = 'diterima';
    public const DIKERJAKAN = 'dikerjakan';
    public const MENUNGGU_KLIEN = 'menunggu_klien';
    public const TERSELESAIKAN = 'terselesaikan';
    public const DITUTUP = 'ditutup';

    /** Diagram status PRD bagian 7. */
    public const TRANSITIONS = [
        self::BARU => [self::DITERIMA, self::DITUTUP],
        self::DITERIMA => [self::DIKERJAKAN],
        self::DIKERJAKAN => [self::MENUNGGU_KLIEN, self::TERSELESAIKAN],
        self::MENUNGGU_KLIEN => [self::DIKERJAKAN],
        self::TERSELESAIKAN => [self::DIKERJAKAN, self::DITUTUP],
        self::DITUTUP => [],
    ];

    /** Aksi klien: konfirmasi tutup atau tolak solusi. */
    public const CLIENT_TRANSITIONS = [
        self::TERSELESAIKAN => [self::DIKERJAKAN, self::DITUTUP],
    ];

    public static function label(string $status): string
    {
        return match ($status) {
            self::BARU => 'Baru',
            self::DITERIMA => 'Diterima',
            self::DIKERJAKAN => 'Dikerjakan',
            self::MENUNGGU_KLIEN => 'Menunggu klien',
            self::TERSELESAIKAN => 'Terselesaikan',
            self::DITUTUP => 'Ditutup',
            default => $status,
        };
    }

    /** @return string[] status tujuan yang boleh dipilih pengguna ini untuk tiket ini */
    public static function availableFor(Ticket $ticket, User $user): array
    {
        if (Gate::forUser($user)->allows('manage', $ticket)) {
            return self::TRANSITIONS[$ticket->status] ?? [];
        }

        if (Gate::forUser($user)->allows('confirmClose', $ticket)) {
            return self::CLIENT_TRANSITIONS[$ticket->status] ?? [];
        }

        return [];
    }
}
