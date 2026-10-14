@php
    $ctx = app(\App\Support\ContextSwitcher::class)->state(auth()->user());
    $pick = function (string $field, $items, $selected, string $placeholder) {
        $html = '<form method="POST" action="'.route('context.update').'" class="ctx">'.csrf_field();
        $html .= '<select name="'.$field.'" onchange="this.form.submit()" aria-label="'.e($placeholder).'">';
        $html .= '<option value="">'.e($placeholder).'</option>';
        foreach ($items as $i) {
            $html .= '<option value="'.$i->id.'"'.($selected === (int) $i->id ? ' selected' : '').'>'.e($i->name).'</option>';
        }
        return $html.'</select></form>';
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'PenpenHub')</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #f6f6f4; margin: 0; color: #1d1d1b; }
        nav { background: #1d1d1b; color: #fff; padding: 10px 16px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        nav a { color: #fff; text-decoration: none; }
        nav .spacer { flex: 1; }
        nav select { padding: 6px; border-radius: 6px; max-width: 160px; }
        nav button { background: none; border: 1px solid #888; color: #fff; border-radius: 6px; padding: 6px 10px; cursor: pointer; }
        form.ctx { margin: 0; }
        main { max-width: 880px; margin: 18px auto; padding: 0 14px; }
        .card { background: #fff; padding: 18px; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.08); margin-bottom: 14px; }
        h1 { font-size: 1.3rem; margin: 0 0 12px; }
        label { display: block; margin: 12px 0 4px; font-size: .9rem; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #bbb; border-radius: 8px; font-size: 1rem; font-family: inherit; }
        input[type=checkbox], input[type=radio] { width: auto; }
        .btn { display: inline-block; padding: 12px 16px; border: 0; border-radius: 8px; background: #1d1d1b; color: #fff; font-size: 1rem; cursor: pointer; text-decoration: none; }
        .btn.secondary { background: #e4e4df; color: #1d1d1b; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; }
        .row > * { flex: 1; min-width: 140px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .8rem; background: #e4e4df; }
        .badge.P1 { background: #b00020; color: #fff; } .badge.P2 { background: #e67e22; color: #fff; }
        .error { color: #b00020; font-size: .85rem; } .status { color: #1b6e3c; }
        table { width: 100%; border-collapse: collapse; } td, th { text-align: left; padding: 8px; border-bottom: 1px solid #eee; }
        .internal { background: #fff7e0; border-left: 4px solid #e6a700; }
        .muted { color: #666; font-size: .85rem; }
    </style>
</head>
<body>
<nav>
    <a href="{{ route('dashboard') }}"><strong>PenpenHub</strong></a>
    <a href="{{ route('tickets.index') }}">Tiket</a>
    <a href="{{ route('tickets.new') }}">+ Lapor masalah</a>
    @if (auth()->user()->is_internal || auth()->user()->isSuperAdmin())<a href="/admin">Admin</a>@endif
    <span class="spacer"></span>
    @if ($ctx['companies']->count() > 1) {!! $pick('company_id', $ctx['companies'], $ctx['company_id'], 'Semua klien') !!} @endif
    @if ($ctx['brands']->count() > 1 || $ctx['brand_id']) {!! $pick('brand_id', $ctx['brands'], $ctx['brand_id'], 'Semua brand') !!} @endif
    @if ($ctx['outlets']->count() > 1 || $ctx['outlet_id']) {!! $pick('outlet_id', $ctx['outlets'], $ctx['outlet_id'], 'Semua outlet') !!} @endif
    <form method="POST" action="{{ url('/logout') }}" class="ctx">@csrf<button type="submit">Keluar</button></form>
</nav>
<main>
    @if (session('status'))<p class="status">{{ session('status') }}</p>@endif
    @if ($errors->any())
        <div class="card error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif
    {{ $slot ?? '' }}
    @yield('content')
</main>
</body>
</html>
