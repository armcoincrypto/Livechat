@extends('admin.support.layout')

@section('title', __('admin_support.page_title'))
@section('heading', __('admin_support.page_heading'))

@section('content')
    <div class="panel">
        @php
            $qParam = $filters['q'] !== '' ? $filters['q'] : null;
            $sortParam = ($filters['sort'] ?? 'waiting') !== 'waiting' ? ($filters['sort'] ?? 'waiting') : null;
            $filterLink = static function (string $status) use ($qParam, $sortParam): string {
                $params = array_filter(
                    [
                        'q' => $qParam,
                        'status' => $status === 'all' ? null : $status,
                        'sort' => $sortParam,
                    ],
                    static fn ($v) => $v !== null && $v !== ''
                );

                return route('admin.support-conversations.index', $params);
            };
        @endphp

        <div class="stat-cards">
            <a href="{{ $filterLink(\App\Models\SupportConversation::STATUS_WAITING_OPERATOR) }}" class="stat-card stat-needs" title="{{ __('admin_support.card_title_needs_reply') }}">
                <div class="stat-label">{{ __('admin_support.counter_needs_reply') }}</div>
                <div class="stat-value">{{ $status_counts['waiting_operator'] }}</div>
            </a>
            <a href="{{ $filterLink(\App\Models\SupportConversation::STATUS_WAITING_VISITOR) }}" class="stat-card stat-wait-vis" title="{{ __('admin_support.card_title_waiting_visitor') }}">
                <div class="stat-label">{{ __('admin_support.counter_waiting_visitor') }}</div>
                <div class="stat-value">{{ $status_counts['waiting_visitor'] }}</div>
            </a>
            <a href="{{ $filterLink(\App\Models\SupportConversation::STATUS_CLOSED) }}" class="stat-card stat-closed" title="{{ __('admin_support.card_title_closed') }}">
                <div class="stat-label">{{ __('admin_support.counter_closed') }}</div>
                <div class="stat-value">{{ $status_counts['closed'] }}</div>
            </a>
            <a href="{{ $filterLink('all') }}" class="stat-card stat-total" title="{{ __('admin_support.card_title_total') }}">
                <div class="stat-label">{{ __('admin_support.counter_total') }}</div>
                <div class="stat-value">{{ $status_counts['total'] }}</div>
            </a>
        </div>

        <p class="wait-summary-heading">{{ __('admin_support.wait_summary_heading') }}</p>
        <div class="stat-cards">
            <div class="stat-card stat-wait-summary">
                <div class="stat-label">{{ __('admin_support.wait_summary_now') }}</div>
                <div class="stat-value">{{ $wait_summary['waiting_now'] }}</div>
            </div>
            <div class="stat-card stat-wait-15">
                <div class="stat-label">{{ __('admin_support.wait_summary_over_15') }}</div>
                <div class="stat-value">{{ $wait_summary['over_15'] }}</div>
            </div>
            <div class="stat-card stat-wait-30">
                <div class="stat-label">{{ __('admin_support.wait_summary_over_30') }}</div>
                <div class="stat-value">{{ $wait_summary['over_30'] }}</div>
            </div>
            <div class="stat-card stat-wait-60">
                <div class="stat-label">{{ __('admin_support.wait_summary_over_60') }}</div>
                <div class="stat-value">{{ $wait_summary['over_60'] }}</div>
            </div>
        </div>

        <form method="get" action="{{ route('admin.support-conversations.index') }}" class="filters">
            <label>
                {{ __('admin_support.filter_search_label') }}
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('admin_support.filter_search_placeholder') }}" autocomplete="off">
            </label>
            <label>
                {{ __('admin_support.filter_status') }}
                <select name="status">
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('admin_support.filter_status_all') }}</option>
                    <option value="{{ \App\Models\SupportConversation::STATUS_WAITING_OPERATOR }}" @selected($filters['status'] === \App\Models\SupportConversation::STATUS_WAITING_OPERATOR)>{{ __('admin_support.filter_status_waiting_operator') }}</option>
                    <option value="{{ \App\Models\SupportConversation::STATUS_WAITING_VISITOR }}" @selected($filters['status'] === \App\Models\SupportConversation::STATUS_WAITING_VISITOR)>{{ __('admin_support.filter_status_waiting_visitor') }}</option>
                    <option value="{{ \App\Models\SupportConversation::STATUS_CLOSED }}" @selected($filters['status'] === \App\Models\SupportConversation::STATUS_CLOSED)>{{ __('admin_support.filter_status_closed') }}</option>
                    <option value="{{ \App\Models\SupportConversation::STATUS_OPEN }}" @selected($filters['status'] === \App\Models\SupportConversation::STATUS_OPEN)>{{ __('admin_support.filter_status_open_legacy') }}</option>
                </select>
            </label>
            <label>
                {{ __('admin_support.filter_sort') }}
                <select name="sort">
                    <option value="waiting" @selected(($filters['sort'] ?? 'waiting') === 'waiting')>{{ __('admin_support.filter_sort_waiting') }}</option>
                    <option value="recent" @selected(($filters['sort'] ?? 'waiting') === 'recent')>{{ __('admin_support.filter_sort_recent') }}</option>
                </select>
            </label>
            <button type="submit" class="primary">{{ __('admin_support.filter_submit') }}</button>
        </form>

        <div class="muted" style="margin-bottom:0.75rem;">
            @if(($filters['sort'] ?? 'waiting') === 'recent')
                {{ __('admin_support.sort_hint_recent', ['count' => $conversations->total()]) }}
            @else
                {{ __('admin_support.sort_hint', ['count' => $conversations->total()]) }}
            @endif
        </div>

        <div style="overflow-x:auto;">
            <table class="data">
                <thead>
                <tr>
                    <th>{{ __('admin_support.th_support_id') }}</th>
                    <th>{{ __('admin_support.th_attention') }}</th>
                    <th>{{ __('admin_support.th_wait_time') }}</th>
                    <th>{{ __('admin_support.th_waiting_on') }}</th>
                    <th>{{ __('admin_support.th_visitor') }}</th>
                    <th>{{ __('admin_support.th_email') }}</th>
                    <th>{{ __('admin_support.th_locale') }}</th>
                    <th>{{ __('admin_support.th_last_activity') }}</th>
                    <th>{{ __('admin_support.th_closed_at') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($conversations as $c)
                    @php
                        $wait = $c->waitingOn();
                        $st = $c->status;
                        $badgeClass = 'badge';
                        $chipLabel = '';
                        $rawChip = '';
                        $waitPriority = $c->operatorWaitPriority();
                        $rowClass = '';
                        if ($waitPriority !== null) {
                            $rowClass = 'row-wait-'.$waitPriority;
                        } elseif ($c->needsOperatorReply()) {
                            $rowClass = 'row-needs-action';
                        }
                        if ($st === \App\Models\SupportConversation::STATUS_CLOSED) {
                            $badgeClass .= ' closed';
                            $chipLabel = __('admin_support.badge_closed');
                        } elseif ($c->needsOperatorReply()) {
                            $badgeClass .= ' wait-op';
                            $chipLabel = __('admin_support.badge_needs_reply');
                            if ($st === \App\Models\SupportConversation::STATUS_OPEN) {
                                $rawChip = __('admin_support.badge_legacy_open');
                            }
                        } elseif ($st === \App\Models\SupportConversation::STATUS_WAITING_VISITOR) {
                            $badgeClass .= ' wait-vis';
                            $chipLabel = __('admin_support.badge_waiting_visitor');
                        } else {
                            $chipLabel = $st;
                        }
                        $waitLabel = $c->operatorWaitLabel();
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td>
                            <a href="{{ route('admin.support-conversations.show', $c) }}"><strong>{{ $c->public_support_id ?? '—' }}</strong></a>
                            <div class="muted" style="font-size:11px;">{{ \Illuminate\Support\Str::limit($c->uuid, 13) }}…</div>
                        </td>
                        <td>
                            <div class="chip-row">
                                <span class="{{ $badgeClass }}">{{ $chipLabel }}</span>
                                @if($rawChip !== '')
                                    <span class="chip subtle">{{ $rawChip }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($waitLabel !== null && $waitPriority !== null)
                                <span class="wait-badge {{ $waitPriority }}" title="{{ $c->last_visitor_message_at?->timezone(config('app.timezone'))->toIso8601String() }}">{{ $waitLabel }}</span>
                            @else
                                <span class="muted">{{ __('admin_support.wait_none') }}</span>
                            @endif
                        </td>
                        <td>{{ $wait ?? '—' }}</td>
                        <td>{{ $c->visitor_name }}</td>
                        <td class="cell-email">{{ $c->visitor_email }}</td>
                        <td>{{ $c->locale ?? '—' }}</td>
                        <td class="muted">
                            @if($c->last_message_at)
                                @php
                                    $ts = $c->last_message_at->timezone(config('app.timezone'));
                                @endphp
                                <strong title="{{ $ts->toIso8601String() }}">{{ $ts->diffForHumans() }}</strong>
                                <div class="muted" style="font-size:11px;">{{ $ts->format('Y-m-d H:i') }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="muted">
                            @if($c->closed_at)
                                {{ $c->closed_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="muted">{{ __('admin_support.empty_list') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $conversations->links('vendor.pagination.bootstrap-5') }}
        </div>
    </div>
@endsection
