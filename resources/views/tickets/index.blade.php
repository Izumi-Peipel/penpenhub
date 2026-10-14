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
            <thead><tr><th>No.</th><th>Prio</th><th>Masalah</th><th>Outlet</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($tickets as $t)
                <tr>
                    <td><a href="{{ route('tickets.show', $t) }}">{{ $t->ticket_number }}</a></td>
                    <td><span class="badge {{ $t->priority }}">{{ $t->priority }}</span></td>
                    <td>{{ $t->title }}</td>
                    <td>{{ $t->outlet?->name ?? $t->brand->name }}</td>
                    <td>{{ \App\Support\TicketStatus::label($t->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Tidak ada tiket.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $tickets->links() }}
    </div>
@endsection
