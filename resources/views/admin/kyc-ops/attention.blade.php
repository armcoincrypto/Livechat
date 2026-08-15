@extends('admin.kyc-ops.layout')

@section('title', 'KYC Attention Queue')
@section('heading', 'Attention queue')

@section('content')
    <div class="panel">
        @include('admin.kyc-ops.partials.dashboard-cards')
    </div>

    <div class="panel">
        <h2 style="margin:0 0 0.5rem;font-size:1rem">Users requiring action</h2>
        <p class="muted" style="margin-top:0">Derived from existing sessions, manual records, webhook events, and KYC logs. No separate workflow.</p>

        <table class="data">
            <thead>
            <tr>
                <th>Kind</th>
                <th>User</th>
                <th>Provider</th>
                <th>Detail</th>
                <th>Detected</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($items as $item)
                <tr>
                    <td><span class="badge warn">{{ $item['kind'] }}</span></td>
                    <td>
                        @if (!empty($item['user_id']))
                            #{{ $item['user_id'] }}
                            <div class="muted">{{ $item['email_masked'] ?? '' }}</div>
                        @else
                            <span class="muted">n/a</span>
                        @endif
                    </td>
                    <td>{{ $item['provider'] ?? '—' }}</td>
                    <td class="muted">{{ $item['detail'] ?? '' }}</td>
                    <td class="muted">{{ $item['detected_at'] ?? '—' }}</td>
                    <td>
                        @if (!empty($item['case_key']))
                            <a href="{{ route('admin.kyc-ops.show', ['caseKey' => $item['case_key']]) }}">Open</a>
                        @elseif (($item['kind'] ?? '') === 'manual_review_required')
                            <a href="{{ url(config('iexexchanger.admin_folder').'/verifications/kyc') }}">Manual queue</a>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No attention items right now.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
