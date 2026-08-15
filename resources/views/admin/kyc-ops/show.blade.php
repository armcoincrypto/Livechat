@extends('admin.kyc-ops.layout')

@section('title', 'KYC case '.$detail['case_key'])
@section('heading', 'Verification details')

@section('content')
    <div class="panel">
        <p class="muted" style="margin-top:0">
            Case <strong>{{ $detail['case_key'] }}</strong>
            · <a href="{{ route('admin.kyc-ops.index') }}">Back to overview</a>
            · Manual queue: <a href="{{ url(config('iexexchanger.admin_folder').'/verifications/kyc') }}">existing KYC page</a>
        </p>

        <h2 style="margin:1rem 0 0.5rem;font-size:1rem">Identity</h2>
        <dl class="meta-grid">
            <div><dt>User ID</dt><dd>{{ $detail['identity']['user_id'] }}</dd></div>
            <div><dt>Email</dt><dd>{{ $detail['identity']['email_masked'] }}</dd></div>
            <div><dt>Account state</dt><dd>{{ $detail['identity']['account_state'] }}</dd></div>
            <div><dt>is_verify_account</dt><dd>{{ !empty($detail['identity']['is_verify_account']) ? '1' : '0' }}</dd></div>
        </dl>

        <h2 style="margin:1.25rem 0 0.5rem;font-size:1rem">Provider</h2>
        <dl class="meta-grid">
            <div><dt>Current provider</dt><dd>{{ $detail['provider']['current'] }}</dd></div>
            <div><dt>Resolved provider</dt><dd>{{ $detail['provider']['resolved'] }}</dd></div>
            <div><dt>Routing reason</dt><dd>{{ $detail['provider']['routing_reason'] }}</dd></div>
            <div><dt>Session ID</dt><dd>{{ $detail['provider']['session_id_masked'] ?? '—' }}</dd></div>
            <div><dt>Session created</dt><dd>{{ $detail['provider']['session_created_at'] ?? '—' }}</dd></div>
            <div><dt>Last update</dt><dd>{{ $detail['provider']['last_update'] ?? '—' }}</dd></div>
        </dl>

        <h2 style="margin:1.25rem 0 0.5rem;font-size:1rem">Verification</h2>
        <dl class="meta-grid">
            <div><dt>Local status</dt><dd>{{ $detail['verification']['local_status'] }}</dd></div>
            <div><dt>Provider status</dt><dd>{{ $detail['verification']['provider_status'] ?: '—' }}</dd></div>
            <div><dt>Stage</dt><dd>{{ $detail['verification']['stage'] }}</dd></div>
            <div><dt>Exchange eligible</dt><dd>{{ !empty($detail['verification']['exchange_eligible']) ? 'yes' : 'no' }}</dd></div>
        </dl>

        <div class="actions">
            @if (in_array('reconcile', $detail['safe_actions'] ?? [], true))
                <form method="post" action="{{ route('admin.kyc-ops.reconcile', ['caseKey' => $detail['case_key']]) }}">
                    @csrf
                    <button type="submit" class="primary">Refresh / reconcile</button>
                </form>
            @endif
            @if (in_array('open_session', $detail['safe_actions'] ?? [], true) && !empty($detail['provider']['has_open_session_url']))
                <form method="post" action="{{ route('admin.kyc-ops.open-session', ['caseKey' => $detail['case_key']]) }}">
                    @csrf
                    <button type="submit">Open provider session</button>
                </form>
            @endif
            @if (in_array('export_audit', $detail['safe_actions'] ?? [], true))
                <a class="btn" href="{{ route('admin.kyc-ops.export', ['caseKey' => $detail['case_key']]) }}">Export audit history</a>
            @endif
            @if (in_array('open_manual_queue', $detail['safe_actions'] ?? [], true))
                <a class="btn" href="{{ url(config('iexexchanger.admin_folder').'/verifications/kyc') }}">Open manual verification</a>
            @endif
            <button type="button" class="btn" onclick="navigator.clipboard.writeText(@json($detail['case_key'].' / user '.$detail['identity']['user_id'].' / '.$detail['provider']['session_id_masked']))">Copy masked refs</button>
        </div>
        <p class="muted" style="margin-bottom:0">Safe actions only. Manual approve/decline remains on the existing verification page. Provider status cannot be edited here.</p>
    </div>

    <div class="panel">
        <h2 style="margin:0 0 0.75rem;font-size:1rem">Lifecycle timeline</h2>
        @if (empty($detail['timeline']))
            <p class="muted">No timeline events recorded.</p>
        @else
            <ul class="timeline">
                @foreach ($detail['timeline'] as $event)
                    <li>
                        <div class="t-label">{{ $event['label'] ?? 'Event' }}</div>
                        @if (!empty($event['detail']))
                            <div class="muted">{{ $event['detail'] }}</div>
                        @endif
                        <div class="t-at">{{ $event['at'] ?? '—' }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
