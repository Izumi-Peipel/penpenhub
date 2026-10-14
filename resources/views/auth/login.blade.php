@extends('layouts.simple')
@section('title', 'Masuk - PenpenHub')
@section('content')
    <h1>Masuk ke PenpenHub</h1>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        <label for="password">Kata sandi</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <button type="submit">Masuk</button>
    </form>
    <p><a href="{{ route('password.request') }}">Lupa kata sandi?</a></p>
@endsection
