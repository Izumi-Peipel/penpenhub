@extends('layouts.app')
@section('title', 'Tiket - PenpenHub')
@section('content')
    <div class="card">
        <h1>Tiket</h1>
        <p>
            @foreach (['open' => 'Terbuka', 'closed' => 'Ditutup', 'all' => 'Semua'] as $key => $label)
                <a class="btn {{ $filter === $key ? '' : 'secondary' }}" href="{{ route('tickets.index', ['status' => $key]) }}">{{ $label }}</a>
            @endforeach
            <a class="btn" href="{{ route('tickets.new') }}">+ Lapor masalah</a>
        </p>
        <table>
            <thead><tr><th>No.</th><th>Prio</th><th>Masalah</th><th>Outlet</th><th>Status</th><th>SLA Resolusi</th></tr></thead>
            <tbody>
            @forelse ($tickets as $t)
                <tr>
                    <td><a href="{{ route('tickets.show', $t) }}">{{ $t->ticket_number }}</a></td>
                    <td><span class="badge {{ $t->priority }}">{{ $t->priority }}</span></td>
                    <td>{{ $t->title }}</td>
                    <td>{{ $t->outlet?->name ?? $t->brand->name }}</td>
                    <td>{{ \App\Support\TicketStatus::label($t->status) }}</td>
                    <td>
                        @if ($t->status === 'ditutup' || $t->status === 'terselesaikan')
                            <span class="badge {{ $t->isResolutionBreached() ? 'breached' : 'met' }}">
                                {{ $t->isResolutionBreached() ? 'Lewat SLA' : 'Tepat Waktu' }}
                            </span>
                        @elseif ($t->isSlaPaused())
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
            @empty
                <tr><td colspan="6" class="muted">Tidak ada tiket.</td></tr>
            @endforelse

            </tbody>
        </table>
        {{ $tickets->links() }}
    </div>
@endsection
