@php
    $totals = $dashboard['totals'] ?? [];
    $status = $dashboard['status_counts'] ?? [];
    $webhook = $dashboard['webhook'] ?? [];
@endphp
<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-label">Provider sessions</div>
        <div class="stat-value">{{ (int) ($totals['provider_sessions'] ?? 0) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending</div>
        <div class="stat-value">{{ (int) (($status['pending'] ?? 0) + ($status['not_started'] ?? 0)) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Processing / review</div>
        <div class="stat-value">{{ (int) ($status['manual_review'] ?? 0) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Approved</div>
        <div class="stat-value">{{ (int) ($status['approved'] ?? 0) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Declined</div>
        <div class="stat-value">{{ (int) ($status['declined'] ?? 0) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Manual pending</div>
        <div class="stat-value">{{ (int) ($totals['manual_pending'] ?? 0) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Webhook processed</div>
        <div class="stat-value">{{ (int) ($webhook['processed'] ?? 0) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Mismatches</div>
        <div class="stat-value">{{ (int) ($dashboard['mismatch_count'] ?? 0) }}</div>
    </div>
</div>
<p class="muted" style="margin-top:0.75rem;margin-bottom:0">
    Default provider: <strong>{{ $dashboard['configured_provider'] ?? '—' }}</strong>
    · Allowlist empty: {{ !empty($dashboard['allowlist_empty']) ? 'yes' : 'no' }}
    · Generated {{ $dashboard['generated_at'] ?? '—' }}
</p>
