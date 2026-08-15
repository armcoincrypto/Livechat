@extends('admin.support.layout')

@section('title', 'Support diagnostics')
@section('heading', 'Support diagnostics')

@section('content')
    @if (! ($supportChatDiagnosticsAvailable ?? ($health['diagnostics_available'] ?? true)))
        <div class="flash err" role="alert">
            <strong>Delivery telemetry unavailable.</strong>
            Required database schema is incomplete ({{ $health['reason'] ?? 'schema_mismatch' }}).
            @if (! empty($health['missing_columns']))
                Missing columns: {{ implode(', ', $health['missing_columns']) }}.
            @endif
            @if (! empty($health['missing_indexes']))
                Missing indexes: {{ implode(', ', $health['missing_indexes']) }}.
            @endif
            Telegram failure counters and retry actions are disabled until migrations are applied.
        </div>
    @endif

    <div class="panel">
        <p class="muted">Internal read-only snapshot · generated {{ $health['generated_at'] ?? '—' }}</p>
        <p>
            <a href="{{ route('admin.support-chat.health.blade') }}" target="_blank" rel="noopener">JSON health</a>
            ·
            <a href="{{ route('admin.support-conversations.index') }}">Conversations</a>
        </p>

        <dl class="meta-grid" style="margin-top:1rem">
            @foreach ($health as $key => $value)
                @if ($key === 'telegram_delivery_summary')
                    @continue
                @endif
                @if (is_array($value))
                    <div>
                        <dt>{{ str_replace('_', ' ', $key) }}</dt>
                        <dd>{{ $value === [] ? '—' : json_encode($value) }}</dd>
                    </div>
                @else
                    <div>
                        <dt>{{ str_replace('_', ' ', $key) }}</dt>
                        <dd>
                            @if ($value === null)
                                unavailable
                            @elseif (is_bool($value))
                                {{ $value ? 'yes' : 'no' }}
                            @else
                                {{ $value }}
                            @endif
                        </dd>
                    </div>
                @endif
            @endforeach
        </dl>
    </div>

    <div class="panel">
        <h2 style="margin:0 0 0.75rem;font-size:1rem">Telegram delivery status</h2>
        @if (! ($supportChatDiagnosticsAvailable ?? false) || $telegramDeliverySummary === null)
            <p class="muted">Delivery telemetry unavailable — apply Support Chat schema migrations to enable counters.</p>
        @else
            <dl class="meta-grid">
                <div>
                    <dt>Delivered (last 24h)</dt>
                    <dd>{{ $telegramDeliverySummary['telegram_delivered_last_24h'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt>Failed (last 24h)</dt>
                    <dd>{{ $telegramDeliverySummary['telegram_failed_last_24h'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt>Pending (last 24h)</dt>
                    <dd>{{ $telegramDeliverySummary['telegram_pending_last_24h'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt>Historical untracked (all time)</dt>
                    <dd>{{ $telegramDeliverySummary['telegram_historical_untracked_count'] ?? 0 }}</dd>
                </div>
            </dl>
            <p class="muted" style="margin-top:0.5rem;margin-bottom:0">Visitor messages only. Historical = created before LC-P4 telemetry restoration with no outbound id.</p>
        @endif
    </div>

    @if (! empty($aiLearningMetrics))
        <div class="panel">
            <h2 style="margin:0 0 0.75rem;font-size:1rem">AI learning telemetry (Phase A)</h2>
            <p class="muted" style="margin-top:0">Read-only · generated {{ $aiLearningMetrics['generated_at'] ?? '—' }} · {{ $aiLearningMetrics['period_days'] ?? 7 }}-day window</p>
            <dl class="meta-grid" style="margin-top:0.75rem">
                <div>
                    <dt>Phase A status</dt>
                    <dd>{{ $aiLearningMetrics['phase_a_status'] ?? 'WAITING' }}</dd>
                </div>
                <div>
                    <dt>Accepted (all time)</dt>
                    <dd>{{ $aiLearningMetrics['accepted_total'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt>Resolved (all time)</dt>
                    <dd>{{ $aiLearningMetrics['resolved_total'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt>Ready for promotion</dt>
                    <dd>{{ $aiLearningMetrics['ready_for_promotion'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt>Quarantined candidates</dt>
                    <dd>{{ $aiLearningMetrics['quarantined'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt>Unknown match rate (period)</dt>
                    <dd>{{ isset($aiLearningMetrics['unknown_rate_pct']) ? $aiLearningMetrics['unknown_rate_pct'].'%' : 'n/a' }}</dd>
                </div>
            </dl>
            @php
                $gates = $aiLearningMetrics['milestones']['gates'] ?? [];
            @endphp
            @if ($gates !== [])
                <p class="muted" style="margin-top:0.75rem;margin-bottom:0.35rem">Next milestone gates:</p>
                <ul class="muted" style="margin:0;padding-left:1.25rem;font-size:13px">
                    @foreach ($gates as $name => $gate)
                        <li>{{ str_replace('_', ' ', $name) }}: {{ (int) ($gate['current'] ?? 0) }} / {{ (int) ($gate['required'] ?? 0) }} {{ ! empty($gate['met']) ? '(met)' : '(not met)' }}</li>
                    @endforeach
                </ul>
            @endif
            @if (! empty($aiLearningMetrics['message']))
                <p class="muted" style="margin-top:0.75rem;margin-bottom:0">{{ $aiLearningMetrics['message'] }}</p>
            @endif
        </div>
    @endif

    <div class="panel">
        <h2 style="margin:0 0 0.75rem;font-size:1rem">Issues &amp; open items</h2>
        @if (count($rows) === 0)
            <p class="muted">No diagnostic rows.</p>
        @else
            <table class="data">
                <thead>
                <tr>
                    <th>Kind</th>
                    <th>Details</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td><span class="badge">{{ $row['kind'] }}</span></td>
                        <td class="muted" style="font-size:12px">
                            @foreach ($row as $k => $v)
                                @if ($k !== 'kind' && $v !== null && $v !== '')
                                    <div><strong>{{ $k }}:</strong> {{ is_bool($v) ? ($v ? 'yes' : 'no') : $v }}</div>
                                @endif
                            @endforeach
                        </td>
                        <td>
                            @if (($row['kind'] ?? '') === 'telegram_message_failed' && ! empty($row['support_message_id']) && ($supportChatDiagnosticsAvailable ?? false))
                                <form method="post" action="{{ route('admin.support-chat.retry-message', ['message' => $row['support_message_id']]) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn">Retry Telegram</button>
                                </form>
                            @elseif (($row['kind'] ?? '') === 'telegram_attachment_failed' && ! empty($row['attachment_id']) && ($supportChatDiagnosticsAvailable ?? false))
                                <form method="post" action="{{ route('admin.support-chat.retry-attachment', ['attachment' => $row['attachment_id']]) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn">Retry attachment</button>
                                </form>
                            @elseif (($row['kind'] ?? '') === 'open_conversation' && empty($row['telegram_topic_id']) && ($health['forum_topics_enabled'] ?? false))
                                <form method="post" action="{{ route('admin.support-chat.recreate-topic', ['conversation' => $row['uuid']]) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn">Create topic</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
