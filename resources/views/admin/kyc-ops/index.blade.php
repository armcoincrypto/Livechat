@extends('admin.kyc-ops.layout')

@section('title', 'KYC Operations')
@section('heading', 'KYC Operations Center')

@section('content')
    <div class="panel">
        <h2 style="margin:0 0 0.75rem;font-size:1rem">Operational summary</h2>
        @include('admin.kyc-ops.partials.dashboard-cards')
    </div>

    <div class="panel">
        <h2 style="margin:0 0 0.75rem;font-size:1rem">Verification overview</h2>
        <form method="get" action="{{ route('admin.kyc-ops.index') }}" class="filters">
            <label>Search
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="User ID or email">
            </label>
            <label>Provider
                <select name="provider">
                    <option value="">All</option>
                    @foreach (['didit','sumsub','manual'] as $p)
                        <option value="{{ $p }}" @selected(($filters['provider'] ?? '') === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </label>
            <label>Status
                <select name="status">
                    <option value="">All</option>
                    @foreach (['not_started','pending','manual_review','approved','declined','expired','manual_pending','manual_approved','manual_declined'] as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </label>
            <label>Verified
                <select name="verified">
                    <option value="">All</option>
                    <option value="1" @selected(($filters['verified'] ?? '') === '1' || ($filters['verified'] ?? '') === 1)>Yes</option>
                    <option value="0" @selected(($filters['verified'] ?? '') === '0' || ($filters['verified'] ?? '') === 0)>No</option>
                </select>
            </label>
            <label>From
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </label>
            <label>To
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </label>
            <label>Sort
                <select name="sort">
                    @foreach (['last_update'=>'Last update','created_at'=>'Created','user_id'=>'User ID','provider'=>'Provider','status'=>'Status'] as $k=>$label)
                        <option value="{{ $k }}" @selected(($filters['sort'] ?? '') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Dir
                <select name="dir">
                    <option value="desc" @selected(($filters['dir'] ?? 'desc') === 'desc')>Desc</option>
                    <option value="asc" @selected(($filters['dir'] ?? '') === 'asc')>Asc</option>
                </select>
            </label>
            <label class="check"><input type="checkbox" name="manual_only" value="1" @checked(!empty($filters['manual_only']))> Manual only</label>
            <label class="check"><input type="checkbox" name="webhook_failures" value="1" @checked(!empty($filters['webhook_failures']))> Webhook/mapping issues</label>
            <label class="check"><input type="checkbox" name="status_mismatch" value="1" @checked(!empty($filters['status_mismatch']))> Status mismatch</label>
            <button type="submit" class="primary">Apply</button>
            <a class="btn" href="{{ route('admin.kyc-ops.index') }}">Reset</a>
        </form>

        <table class="data">
            <thead>
            <tr>
                <th>User</th>
                <th>Provider</th>
                <th>Status / stage</th>
                <th>Verified</th>
                <th>Created</th>
                <th>Last update</th>
                <th>Last event</th>
                <th>Risk</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($cases as $row)
                <tr>
                    <td>
                        <a href="{{ route('admin.kyc-ops.show', ['caseKey' => $row['case_key']]) }}">#{{ $row['user_id'] }}</a>
                        <div class="muted">{{ $row['email_masked'] }}</div>
                    </td>
                    <td><span class="badge">{{ $row['provider'] }}</span></td>
                    <td>
                        <div>{{ $row['status'] }}</div>
                        <div class="muted">{{ $row['stage'] }}</div>
                    </td>
                    <td>
                        @if ($row['is_verify_account'])
                            <span class="badge ok">yes</span>
                        @else
                            <span class="badge muted">no</span>
                        @endif
                    </td>
                    <td class="muted">{{ $row['session_created_at'] ?? '—' }}</td>
                    <td class="muted">{{ $row['last_provider_update'] ?? '—' }}</td>
                    <td class="muted">{{ $row['last_webhook_at'] ?? '—' }}</td>
                    <td>
                        @php $risk = $row['risk'] ?? 'low'; @endphp
                        <span class="badge {{ $risk === 'high' ? 'bad' : ($risk === 'medium' ? 'warn' : 'ok') }}">{{ $risk }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No verification cases match the filters.</td></tr>
            @endforelse
            </tbody>
        </table>

        <div class="pagination">
            <span class="muted">{{ $cases->total() }} total · page {{ $cases->currentPage() }} / {{ max(1, $cases->lastPage()) }}</span>
            @if ($cases->previousPageUrl())
                <a class="btn" href="{{ $cases->appends(request()->query())->previousPageUrl() }}">Previous</a>
            @endif
            @if ($cases->nextPageUrl())
                <a class="btn" href="{{ $cases->appends(request()->query())->nextPageUrl() }}">Next</a>
            @endif
        </div>
    </div>
@endsection
