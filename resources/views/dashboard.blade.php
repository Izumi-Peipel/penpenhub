@extends('layouts.app')
@section('title', 'Dashboard - PenpenHub')
@section('content')
    <div class="card">
        <h1>Halo, {{ $user->name }}</h1>
        <p><a class="btn" href="{{ route('tickets.new') }}">Lapor masalah</a>
           <a class="btn secondary" href="{{ route('tickets.index') }}">Lihat tiket</a></p>
        <p class="muted">Akses Anda:</p>
        <ul>
            @forelse ($user->memberships as $m)
                <li>{{ \App\Support\Roles::label($m->role) }} di {{ $m->company->name }} (scope: {{ $m->scope_type }})</li>
            @empty
                <li>Belum ada akses.</li>
            @endforelse
        </ul>
        <p class="muted">Dashboard lengkap (paket, kuota, status layanan) hadir di Sprint 4.</p>
    </div>
@endsection
