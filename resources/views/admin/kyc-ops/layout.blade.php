<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,noarchive">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'KYC Operations') — {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #f4f6f9;
            --panel: #fff;
            --text: #1a1d23;
            --muted: #5c6370;
            --border: #e2e5eb;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
        }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Ubuntu, sans-serif; margin: 0; background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.5; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; color: var(--accent-hover); }
        .wrap { max-width: 1280px; margin: 0 auto; padding: 1rem 1.25rem 2rem; }
        header.bar { background: var(--panel); border-bottom: 1px solid var(--border); padding: 0.75rem 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
        header.bar h1 { margin: 0; font-size: 1rem; font-weight: 600; }
        nav.tabs { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 1rem 0 0; }
        nav.tabs a { padding: 0.4rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); background: var(--panel); color: var(--text); font-size: 13px; font-weight: 500; }
        nav.tabs a.active { background: var(--accent); border-color: var(--accent); color: #fff; }
        nav.tabs a:hover { text-decoration: none; }
        .panel { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 1rem 1.25rem; margin-top: 1rem; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { text-align: left; padding: 0.5rem 0.6rem; border-bottom: 1px solid var(--border); vertical-align: top; }
        table.data th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); font-weight: 600; }
        table.data tr:hover td { background: #fafbfc; }
        .badge { display: inline-block; padding: 0.15rem 0.45rem; border-radius: 4px; font-size: 12px; font-weight: 500; background: #eef2ff; color: #3730a3; }
        .badge.ok { background: #ecfdf5; color: #047857; }
        .badge.warn { background: #fef3c7; color: #92400e; }
        .badge.bad { background: #fef2f2; color: #b91c1c; }
        .badge.muted { background: #f3f4f6; color: #4b5563; }
        .filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; margin-bottom: 0.75rem; }
        .filters label { display: flex; flex-direction: column; gap: 0.25rem; font-size: 12px; color: var(--muted); }
        input[type="text"], input[type="date"], select { padding: 0.45rem 0.55rem; border: 1px solid var(--border); border-radius: 6px; min-width: 140px; font-size: 14px; }
        button, .btn { display: inline-block; padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid var(--border); background: var(--panel); cursor: pointer; font-size: 14px; color: var(--text); }
        button.primary, .btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .muted { color: var(--muted); font-size: 13px; }
        .flash { padding: 0.65rem 0.85rem; border-radius: 6px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; margin-bottom: 1rem; }
        .flash.err { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem 1.25rem; }
        .meta-grid dt { font-size: 11px; text-transform: uppercase; color: var(--muted); margin: 0; }
        .meta-grid dd { margin: 0.15rem 0 0; font-weight: 500; word-break: break-word; }
        .stat-cards { display: flex; flex-wrap: wrap; gap: 0.65rem; }
        .stat-card { flex: 1 1 120px; min-width: 110px; border: 1px solid var(--border); border-radius: 8px; padding: 0.65rem 0.75rem; background: var(--panel); }
        .stat-card .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); font-weight: 600; }
        .stat-card .stat-value { font-size: 1.25rem; font-weight: 700; margin-top: 0.2rem; line-height: 1.1; }
        .timeline { list-style: none; margin: 0; padding: 0; border-left: 2px solid var(--border); }
        .timeline li { position: relative; padding: 0 0 1rem 1rem; }
        .timeline li::before { content: ''; position: absolute; left: -6px; top: 0.35rem; width: 10px; height: 10px; border-radius: 50%; background: var(--accent); border: 2px solid #fff; box-shadow: 0 0 0 1px var(--border); }
        .timeline .t-label { font-weight: 600; }
        .timeline .t-at { font-size: 12px; color: var(--muted); }
        .actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; align-items: center; }
        .pagination { margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
        .check { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 13px; color: var(--muted); }
    </style>
    @stack('head')
</head>
<body>
<header class="bar">
    <h1>@yield('heading', 'KYC Operations Center')</h1>
    <nav>
        <a href="{{ url(config('iexexchanger.admin_folder')) }}">Admin</a>
        · <a href="{{ url(config('iexexchanger.admin_folder').'/verifications/kyc') }}">Manual KYC</a>
        · <a href="{{ route('admin.kyc-ops.index') }}">Ops Center</a>
    </nav>
</header>
<div class="wrap">
    @if(session('status'))
        <div class="flash" role="status">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="flash err" role="alert">{{ session('error') }}</div>
    @endif

    <nav class="tabs" aria-label="KYC ops sections">
        <a href="{{ route('admin.kyc-ops.index') }}" class="{{ ($tab ?? '') === 'overview' ? 'active' : '' }}">Overview</a>
        <a href="{{ route('admin.kyc-ops.attention') }}" class="{{ ($tab ?? '') === 'attention' ? 'active' : '' }}">Attention queue</a>
        <a href="{{ route('admin.kyc-ops.diagnostics') }}" class="{{ ($tab ?? '') === 'diagnostics' ? 'active' : '' }}">Provider diagnostics</a>
    </nav>

    @yield('content')
</div>
</body>
</html>
