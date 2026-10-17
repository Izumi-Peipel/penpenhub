@extends('layouts.app')
@section('title', 'Dashboard - PenpenHub')
@section('content')

{{-- ===== KARTU SELAMAT DATANG ===== --}}
<div class="card">
    <h1>Halo, {{ $user->name }} 👋</h1>
    <p>
        <a class="btn" href="{{ route('tickets.new') }}">+ Lapor masalah</a>
        <a class="btn secondary" href="{{ route('tickets.index') }}">Lihat semua tiket</a>
    </p>
</div>

{{-- ===== STATISTIK RINGKAS ===== --}}
<div class="row" style="margin-bottom: 14px;">
    <div class="card" style="margin-bottom:0; text-align:center;">
        <div style="font-size:2rem; font-weight:700; color:#1d1d1b;">{{ $stats['open'] }}</div>
        <div class="muted">Tiket aktif</div>
    </div>
    <div class="card" style="margin-bottom:0; text-align:center;">
        <div style="font-size:2rem; font-weight:700; color:{{ $stats['breached'] > 0 ? '#b00020' : '#1b6e3c' }};">
            {{ $stats['breached'] }}
        </div>
        <div class="muted">SLA terlewat</div>
    </div>
</div>

{{-- ===== KARTU KUOTA ===== --}}
@if ($quotaSummary)
    @if ($quotaSummary['status'] === 'no_subscription')
        <div class="card">
            <h1>Paket Langganan</h1>
            <p class="muted">Belum ada paket aktif. Hubungi tim PenpenHub untuk berlangganan.</p>
        </div>
    @else
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h1 style="margin:0;">Paket {{ $quotaSummary['plan'] }}</h1>
                @if ($quotaSummary['period_end'])
                    <span class="muted">Periode s/d {{ \Carbon\Carbon::parse($quotaSummary['period_end'])->locale('id')->isoFormat('D MMMM Y') }}</span>
                @endif
            </div>

            {{-- Kuota Request (Tiket) --}}
            <div style="margin-bottom: 16px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span style="font-size:.9rem; font-weight:600;">Request Tiket</span>
                    <span class="muted">{{ $quotaSummary['requests_used'] }} / {{ $quotaSummary['requests_total'] }}</span>
                </div>
                <div style="background:#e4e4df; border-radius:999px; height:10px; overflow:hidden;">
                    @php $reqPct = $quotaSummary['request_pct']; $reqColor = $reqPct >= 90 ? '#b00020' : ($reqPct >= 70 ? '#e67e22' : '#1b6e3c'); @endphp
                    <div style="width:{{ $reqPct }}%; height:100%; background:{{ $reqColor }}; border-radius:999px; transition:width .3s;"></div>
                </div>
                <div style="margin-top:3px;" class="muted">
                    {{ $quotaSummary['requests_total'] - $quotaSummary['requests_used'] }} request tersisa
                    @if ($quotaSummary['request_pct'] >= 90)
                        &nbsp;<span class="badge breached">Hampir habis</span>
                    @endif
                </div>
            </div>

            {{-- Kuota Jam Kerja --}}
            <div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span style="font-size:.9rem; font-weight:600;">Jam Layanan</span>
                    <span class="muted">{{ $quotaSummary['hours_used'] }} / {{ $quotaSummary['hours_total'] }} jam</span>
                </div>
                <div style="background:#e4e4df; border-radius:999px; height:10px; overflow:hidden;">
                    @php $hrPct = $quotaSummary['hours_pct']; $hrColor = $hrPct >= 90 ? '#b00020' : ($hrPct >= 70 ? '#e67e22' : '#1b6e3c'); @endphp
                    <div style="width:{{ $hrPct }}%; height:100%; background:{{ $hrColor }}; border-radius:999px; transition:width .3s;"></div>
                </div>
                <div style="margin-top:3px;" class="muted">
                    {{ $quotaSummary['hours_total'] - $quotaSummary['hours_used'] }} jam tersisa
                    @if ($quotaSummary['hours_pct'] >= 90)
                        &nbsp;<span class="badge breached">Hampir habis</span>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endif

{{-- ===== TIKET AKTIF TERKINI ===== --}}
<div class="card">
    <h1>Tiket Aktif</h1>
    @if ($activeTickets->isEmpty())
        <p class="muted">Tidak ada tiket aktif. Semua beres! 🎉</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Prio</th>
                    <th>Masalah</th>
                    <th>Status</th>
                    <th>SLA Resolusi</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($activeTickets as $t)
                <tr>
                    <td><a href="{{ route('tickets.show', $t) }}">{{ $t->ticket_number }}</a></td>
                    <td><span class="badge {{ $t->priority }}">{{ $t->priority }}</span></td>
                    <td>{{ $t->title }}</td>
                    <td>{{ \App\Support\TicketStatus::label($t->status) }}</td>
                    <td>
                        @if ($t->isSlaPaused())
                            <span class="badge paused">Dijeda</span>
                        @elseif ($t->sla_resolution_due_at)
                            <span class="badge {{ $t->isResolutionBreached() ? 'breached' : 'pending' }}">
                                {{ $t->isResolutionBreached() ? 'Lewat SLA' : $t->sla_resolution_due_at->timezone('Asia/Jakarta')->format('d M H:i') }}
                            </span>
                        @else
                            <span class="muted">-</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @if ($stats['open'] > 5)
            <p style="margin-top:10px;">
                <a class="btn secondary" href="{{ route('tickets.index') }}">Lihat semua {{ $stats['open'] }} tiket →</a>
            </p>
        @endif
    @endif
</div>

{{-- ===== AKSES SAYA ===== --}}
<div class="card">
    <h1>Akses Saya</h1>
    <ul style="margin:0; padding-left:18px;">
        @forelse ($user->memberships as $m)
            <li>{{ \App\Support\Roles::label($m->role) }} di {{ $m->company->name }} (scope: {{ $m->scope_type }})</li>
        @empty
            <li class="muted">Belum ada akses yang diberikan.</li>
        @endforelse
    </ul>
</div>

@endsection
