@extends('layouts.simple')
@section('title', 'Verifikasi dua langkah - PenpenHub')
@section('content')
    <h1>Verifikasi dua langkah</h1>
    <form method="POST" action="{{ url('/two-factor-challenge') }}">
        @csrf
        <label for="code">Kode dari aplikasi autentikator</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus>
        <label for="recovery_code">Atau kode pemulihan</label>
        <input id="recovery_code" name="recovery_code" autocomplete="off">
        <button type="submit">Verifikasi</button>
    </form>
@endsection
