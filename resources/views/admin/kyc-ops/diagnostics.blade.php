@extends('admin.kyc-ops.layout')

@section('title', 'KYC Provider Diagnostics')
@section('heading', 'Provider diagnostics')

@section('content')
    @php
        $d = $diagnostics;
        $webhook = $d['webhook'] ?? [];
        $retrieve = $d['retrieve'] ?? [];
        $status = $d['status'] ?? [];
        $secrets = $d['secrets_configured'] ?? [];
    @endphp

    <div class="panel">
        <h2 style="margin:0 0 0.75rem;font-size:1rem">Current provider</h2>
        <dl class="meta-grid">
            <div><dt>Configured</dt><dd>{{ $d['current_provider'] ?? '—' }}</dd></div>
            <div><dt>Allowlist empty</dt><dd>{{ !empty($d['allowlist_empty']) ? 'yes (global default)' : 'no (cohort gate)' }}</dd></div>
            <div><dt>Didit API key</dt><dd>{{ !empty($secrets['didit_api_key']) ? 'configured' : 'missing' }}</dd></div>
            <div><dt>Didit webhook secret</dt><dd>{{ !empty($secrets['didit_webhook_secret']) ? 'configured' : 'missing' }}</dd></div>
            <div><dt>Didit workflow</dt><dd>{{ !empty($secrets['didit_workflow_id']) ? 'configured' : 'missing' }}</dd></div>
        </dl>
        <p class="muted" style="margin-bottom:0">Secret values are never displayed.</p>
    </div>

    <div class="panel">
        <h2 style="margin:0 0 0.75rem;font-size:1rem">Webhook</h2>
        <dl class="meta-grid">
            <div><dt>Last delivery</dt><dd>{{ $webhook['last_delivery_at'] ?? '—' }}</dd></div>
            <div><dt>Last accepted</dt><dd>{{ $webhook['last_accepted_at'] ?? '—' }}</dd></div>
            <div><dt>Last status</dt><dd>{{ $webhook['last_status'] ?? '—' }}</dd></div>
            <div><dt>Signature mode</dt><dd>{{ $webhook['signature_status'] ?? '—' }}</dd></div>
            <div><dt>Processed</dt><dd>{{ (int) ($webhook['processed'] ?? 0) }}</dd></div>
            <div><dt>Failed / rejected</dt><dd>{{ (int) ($webhook['failed'] ?? 0) }}</dd></div>
            <div><dt>Duplicate protection</dt><dd>{{ $webhook['duplicate_hint'] ?? '—' }}</dd></div>
        </dl>
    </div>

    <div class="panel">
        <h2 style="margin:0 0 0.75rem;font-size:1rem">Retrieve / reconciliation</h2>
        <dl class="meta-grid">
            <div><dt>Last reconciliation</dt><dd>{{ $retrieve['last_reconciliation_at'] ?? '—' }}</dd></div>
            <div><dt>Last sync</dt><dd>{{ $retrieve['last_sync_at'] ?? '—' }}</dd></div>
            <div><dt>Last decision log</dt><dd>{{ $retrieve['last_decision_log_at'] ?? '—' }}</dd></div>
            <div><dt>Last failure</dt><dd>{{ $retrieve['last_failure'] ?? 'not exposed' }}</dd></div>
        </dl>
        <p class="muted" style="margin-bottom:0">{{ $retrieve['note'] ?? '' }}</p>
    </div>

    <div class="panel">
        <h2 style="margin:0 0 0.75rem;font-size:1rem">Status distribution</h2>
        <dl class="meta-grid">
            @foreach ($status as $key => $count)
                <div><dt>{{ str_replace('_', ' ', $key) }}</dt><dd>{{ (int) $count }}</dd></div>
            @endforeach
            <div><dt>Manual pending</dt><dd>{{ (int) ($d['manual_pending'] ?? 0) }}</dd></div>
        </dl>
    </div>
@endsection
