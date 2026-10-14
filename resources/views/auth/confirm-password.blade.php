@extends('layouts.simple')
@section('title', 'Konfirmasi kata sandi - PenpenHub')
@section('content')
    <h1>Konfirmasi kata sandi</h1>
    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <label for="password">Kata sandi</label>
        <input id="password" name="password" type="password" required autofocus autocomplete="current-password">
        <button type="submit">Konfirmasi</button>
    </form>
@endsection
