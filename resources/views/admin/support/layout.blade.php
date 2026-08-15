<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,noarchive">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Support') — {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #f4f6f9;
            --panel: #fff;
            --text: #1a1d23;
            --muted: #5c6370;
            --border: #e2e5eb;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --visitor: #0f766e;
            --support: #7c3aed;
            --system: #92400e;
        }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Ubuntu, sans-serif; margin: 0; background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.5; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; color: var(--accent-hover); }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 1rem 1.25rem 2rem; }
        header.bar { background: var(--panel); border-bottom: 1px solid var(--border); padding: 0.75rem 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
        header.bar h1 { margin: 0; font-size: 1rem; font-weight: 600; }
        .panel { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 1rem 1.25rem; margin-top: 1rem; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { text-align: left; padding: 0.5rem 0.6rem; border-bottom: 1px solid var(--border); vertical-align: top; }
        table.data th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); font-weight: 600; }
        table.data tr:hover td { background: #fafbfc; }
        table.data tr.row-needs-action td { background: #fffbeb; }
        table.data tr.row-needs-action td:first-child { box-shadow: inset 3px 0 0 #d97706; }
        table.data tr.row-needs-action:hover td { background: #fef3c7; }
        table.data .cell-email { word-break: break-word; max-width: 220px; }
        .badge { display: inline-block; padding: 0.15rem 0.45rem; border-radius: 4px; font-size: 12px; font-weight: 500; background: #eef2ff; color: #3730a3; }
        .badge.closed { background: #f3f4f6; color: #374151; }
        .badge.wait-op { background: #fef3c7; color: #92400e; }
        .badge.wait-vis { background: #dbeafe; color: #1e40af; }
        .filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; margin-bottom: 0.5rem; }
        .filters label { display: flex; flex-direction: column; gap: 0.25rem; font-size: 12px; color: var(--muted); }
        input[type="text"], select { padding: 0.45rem 0.55rem; border: 1px solid var(--border); border-radius: 6px; min-width: 200px; font-size: 14px; }
        button, .btn { display: inline-block; padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid var(--border); background: var(--panel); cursor: pointer; font-size: 14px; color: var(--text); }
        button.primary, .btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        button.primary:hover, .btn.primary:hover { background: var(--accent-hover); border-color: var(--accent-hover); }
        .muted { color: var(--muted); font-size: 13px; }
        .flash { padding: 0.65rem 0.85rem; border-radius: 6px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; margin-bottom: 1rem; }
        .flash.err { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.75rem 1.25rem; }
        .meta-grid dt { font-size: 11px; text-transform: uppercase; color: var(--muted); margin: 0; }
        .meta-grid dd { margin: 0.15rem 0 0; font-weight: 500; }
        .transcript { margin-top: 1rem; display: flex; flex-direction: column; gap: 0.75rem; }
        .msg { border-radius: 8px; padding: 0.65rem 0.85rem; border: 1px solid var(--border); max-width: 100%; }
        .msg.visitor { border-left: 4px solid var(--visitor); background: #f0fdfa; }
        .msg.operator { border-left: 4px solid var(--support); background: #f5f3ff; }
        .msg.system { border-left: 4px solid var(--system); background: #fffbeb; }
        .msg-head { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 0.35rem; font-size: 12px; margin-bottom: 0.35rem; color: var(--muted); }
        .msg .body { white-space: pre-wrap; word-break: break-word; }
        .telegram-meta { font-size: 11px; color: var(--muted); margin-top: 0.35rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; align-items: center; }
        .pagination { margin-top: 1rem; }
        .pagination nav { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
        .stat-cards { display: flex; flex-wrap: wrap; gap: 0.65rem; margin-bottom: 1rem; }
        .stat-card { flex: 1 1 140px; min-width: 130px; border: 1px solid var(--border); border-radius: 8px; padding: 0.65rem 0.75rem; background: var(--panel); text-decoration: none; color: inherit; transition: box-shadow 0.12s ease, border-color 0.12s ease; }
        a.stat-card:hover { box-shadow: 0 1px 4px rgba(0,0,0,0.06); border-color: #c7cbd4; text-decoration: none; color: inherit; }
        .stat-card .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); font-weight: 600; }
        .stat-card .stat-value { font-size: 1.35rem; font-weight: 700; margin-top: 0.2rem; line-height: 1.1; }
        .stat-card.stat-needs { border-left: 3px solid #d97706; }
        .stat-card.stat-wait-vis { border-left: 3px solid #2563eb; }
        .stat-card.stat-closed { border-left: 3px solid #6b7280; }
        .stat-card.stat-total { border-left: 3px solid #4b5563; }
        .stat-card.stat-wait-summary { border-left: 3px solid #d97706; }
        .stat-card.stat-wait-15 { border-left: 3px solid #f59e0b; }
        .stat-card.stat-wait-30 { border-left: 3px solid #ea580c; }
        .stat-card.stat-wait-60 { border-left: 3px solid #dc2626; }
        .wait-summary-heading { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); margin: 0 0 0.5rem; }
        .wait-badge { display: inline-block; padding: 0.15rem 0.45rem; border-radius: 4px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .wait-badge.normal { background: #ecfdf5; color: #047857; }
        .wait-badge.warning { background: #fef3c7; color: #92400e; }
        .wait-badge.danger { background: #ffedd5; color: #c2410c; }
        .wait-badge.critical { background: #fef2f2; color: #991b1b; }
        table.data tr.row-wait-warning td { background: #fffbeb; }
        table.data tr.row-wait-warning td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
        table.data tr.row-wait-danger td { background: #ffedd5; }
        table.data tr.row-wait-danger td:first-child { box-shadow: inset 3px 0 0 #ea580c; }
        table.data tr.row-wait-critical td { background: #fef2f2; }
        table.data tr.row-wait-critical td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
        table.data tr.row-wait-warning:hover td,
        table.data tr.row-wait-danger:hover td,
        table.data tr.row-wait-critical:hover td { filter: brightness(0.98); }
        .chip-row { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; }
        .chip { display: inline-block; padding: 0.12rem 0.4rem; border-radius: 999px; font-size: 11px; font-weight: 600; letter-spacing: 0.02em; }
        .chip.subtle { background: #f3f4f6; color: #4b5563; font-weight: 500; }
        .badge.tg-delivered { background: #ecfdf5; color: #047857; }
        .badge.tg-failed { background: #fef2f2; color: #b91c1c; }
        .badge.tg-pending { background: #fffbeb; color: #b45309; }
        .badge.tg-historical { background: #f3f4f6; color: #6b7280; }
        .visitor-context { margin-top: 0.75rem; padding: 0.65rem 0.75rem; border-radius: 8px; background: #f8fafc; border: 1px solid var(--border); }
        .visitor-context dt { font-size: 11px; text-transform: uppercase; color: var(--muted); margin: 0; }
        .visitor-context dd { margin: 0.1rem 0 0; font-weight: 500; word-break: break-word; }
        .visitor-context-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.6rem 1rem; }
        .msg-head-badges { display: inline-flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; margin-left: 0.5rem; }
    </style>
    @stack('head')
</head>
<body>
<header class="bar">
    <h1>@yield('heading', 'Support conversations')</h1>
    <nav>
        <a href="{{ url(config('iexexchanger.admin_folder')) }}">Admin</a>
        · <a href="{{ route('admin.support-conversations.index') }}">Support list</a>
        · <a href="{{ route('admin.support-chat.diagnostics') }}">Diagnostics</a>
    </nav>
</header>
<div class="wrap">
    @if(session('status'))
        <div class="flash" role="status">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="flash err" role="alert">{{ session('error') }}</div>
    @endif
    @yield('content')
</div>
</body>
</html>
