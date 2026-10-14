<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'PenpenHub')</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f6f6f4; margin: 0; color: #1d1d1b; }
        main { max-width: 420px; margin: 8vh auto; background: #fff; padding: 28px; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size: 1.25rem; margin-top: 0; }
        label { display: block; margin: 14px 0 4px; font-size: .9rem; }
        input { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #bbb; border-radius: 8px; font-size: 1rem; }
        button { margin-top: 18px; width: 100%; padding: 12px; border: 0; border-radius: 8px; background: #1d1d1b; color: #fff; font-size: 1rem; cursor: pointer; }
        .error { color: #b00020; font-size: .85rem; }
        .status { color: #1b6e3c; font-size: .9rem; }
        a { color: #1d1d1b; }
    </style>
</head>
<body>
<main>
    @if (session('status'))<p class="status">{{ session('status') }}</p>@endif
    @if ($errors->any())
        <div class="error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif
    @yield('content')
</main>
</body>
</html>
