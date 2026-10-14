@extends('layouts.simple')
@section('title', 'Kata sandi baru - PenpenHub')
@section('content')
    <h1>Kata sandi baru</h1>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required>
        <label for="password">Kata sandi baru (min. 10 karakter, huruf besar/kecil, angka)</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">
        <label for="password_confirmation">Ulangi kata sandi</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        <button type="submit">Simpan</button>
    </form>
@endsection
