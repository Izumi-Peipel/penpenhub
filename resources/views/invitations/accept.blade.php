@extends('layouts.simple')
@section('title', 'Terima undangan - PenpenHub')
@section('content')
    <h1>Undangan untuk {{ $invitation->email }}</h1>
    <p>{{ $invitation->company->name }} mengundang Anda sebagai {{ \App\Support\Roles::label($invitation->role) }}.</p>
    <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}">
        @csrf
        @unless ($hasAccount)
            <label for="name">Nama lengkap</label>
            <input id="name" name="name" value="{{ old('name') }}" required autofocus>
            <label for="password">Kata sandi (min. 10 karakter, huruf besar/kecil, angka)</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">
            <label for="password_confirmation">Ulangi kata sandi</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        @else
            <p>Email ini sudah punya akun. Menerima undangan akan menambahkan akses baru; Anda lalu masuk seperti biasa.</p>
        @endunless
        <button type="submit">Terima undangan</button>
    </form>
@endsection
