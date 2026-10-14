@extends('layouts.simple')
@section('title', 'Lupa kata sandi - PenpenHub')
@section('content')
    <h1>Atur ulang kata sandi</h1>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
        <button type="submit">Kirim tautan atur ulang</button>
    </form>
@endsection
