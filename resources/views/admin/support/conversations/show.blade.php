@extends('admin.support.layout')

@section('title', $conversation->public_support_id ?? $conversation->uuid)
@section('heading', 'Support: ' . ($conversation->public_support_id ?? $conversation->uuid))

@section('content')
    <p class="muted"><a href="{{ route('admin.support-conversations.index') }}">← Back to list</a></p>

    <div class="panel">
        <h2 style="margin-top:0;font-size:1rem;">Conversation</h2>

        @php $ctx = $visitorContext ?? []; @endphp
        <div class="visitor-context" aria-label="Visitor context">
            <div class="visitor-context-grid">
                <div>
                    <dt>Country</dt>
                    <dd>{{ $ctx['country_display'] ?? 'Unknown' }}</dd>
                </div>
                <div>
                    <dt>Language / locale</dt>
                    <dd>{{ $ctx['locale'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Page URL</dt>
                    <dd>
                        @if(!empty($ctx['page_url']))
                            <a href="{{ $ctx['page_url'] }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($ctx['page_url'], 80) }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Timezone</dt>
                    <dd>{{ $ctx['timezone'] ?? '—' }}</dd>
                </div>
            </div>
        </div>

        <dl class="meta-grid" style="margin-top:1rem">
            <div>
                <dt>Public support ID</dt>
                <dd>{{ $conversation->public_support_id ?? '—' }}</dd>
            </div>
            <div>
                <dt>UUID</dt>
                <dd style="word-break:break-all;">{{ $conversation->uuid }}</dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd>{{ $conversation->status }}</dd>
            </div>
            <div>
                <dt>Waiting on</dt>
                <dd>{{ $conversation->waitingOn() ?? '—' }}</dd>
            </div>
            <div>
                <dt>Visitor name</dt>
                <dd>{{ $conversation->visitor_name }}</dd>
            </div>
            <div>
                <dt>Visitor email</dt>
                <dd>{{ $conversation->visitor_email }}</dd>
            </div>
            <div>
                <dt>Locale</dt>
                <dd>{{ $conversation->locale ?? '—' }}</dd>
            </div>
            <div>
                <dt>Page URL</dt>
                <dd style="word-break:break-all;">
                    @if($conversation->page_url)
                        <a href="{{ $conversation->page_url }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($conversation->page_url, 96) }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt>Created</dt>
                <dd>{{ $conversation->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '—' }}</dd>
            </div>
            <div>
                <dt>Updated</dt>
                <dd>{{ $conversation->updated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '—' }}</dd>
            </div>
            <div>
                <dt>Closed at</dt>
                <dd>{{ $conversation->closed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '—' }}</dd>
            </div>
        </dl>

        @if($conversation->last_operator_display_name || $conversation->last_operator_telegram_username || $conversation->last_operator_telegram_user_id)
            <h3 style="font-size:0.95rem;margin-top:1.25rem;">Operator snapshot (last Telegram reply)</h3>
            <dl class="meta-grid">
                <div>
                    <dt>Display name</dt>
                    <dd>{{ $conversation->last_operator_display_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Telegram @username</dt>
                    <dd>{{ $conversation->last_operator_telegram_username ? '@'.$conversation->last_operator_telegram_username : '—' }}</dd>
                </div>
                <div>
                    <dt>Telegram user id</dt>
                    <dd>{{ $conversation->last_operator_telegram_user_id ?? '—' }}</dd>
                </div>
            </dl>
        @endif

        <div class="actions">
            @if($conversation->isClosed())
                <form method="post" action="{{ route('admin.support-conversations.reopen', $conversation) }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="waiting" value="operator">
                    <button type="submit" class="primary">Reopen (waiting operator)</button>
                </form>
                <form method="post" action="{{ route('admin.support-conversations.reopen', $conversation) }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="waiting" value="visitor">
                    <button type="submit">Reopen (waiting visitor)</button>
                </form>
            @else
                <form method="post" action="{{ route('admin.support-conversations.close', $conversation) }}" style="display:inline;" onsubmit="return confirm('Close this conversation? Telegram replies will be ignored until the visitor messages again.');">
                    @csrf
                    <button type="submit">Close conversation</button>
                </form>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2 style="margin-top:0;font-size:1rem;">Transcript</h2>
        <div class="transcript">
            @foreach($conversation->messages as $msg)
                @php
                    $senderLabel = match ($msg->sender_type) {
                        \App\Models\SupportMessage::SENDER_VISITOR => 'Visitor',
                        \App\Models\SupportMessage::SENDER_OPERATOR => 'Support (operator)',
                        \App\Models\SupportMessage::SENDER_SYSTEM => 'System',
                        default => $msg->sender_type,
                    };
                    $rowClass = match ($msg->sender_type) {
                        \App\Models\SupportMessage::SENDER_VISITOR => 'visitor',
                        \App\Models\SupportMessage::SENDER_OPERATOR => 'operator',
                        \App\Models\SupportMessage::SENDER_SYSTEM => 'system',
                        default => 'system',
                    };
                @endphp
                <article class="msg {{ $rowClass }}">
                    <div class="msg-head">
                        <span>
                            <strong>{{ $senderLabel }}</strong>
                            @if($msg->sender_type === \App\Models\SupportMessage::SENDER_VISITOR && ($supportChatDiagnosticsAvailable ?? false))
                                @php $badge = $deliveryBadges[$msg->id] ?? null; @endphp
                                @if(!empty($badge['label']))
                                    <span class="msg-head-badges">
                                        <span class="badge {{ $badge['badge_class'] }}" @if(!empty($badge['title'])) title="{{ $badge['title'] }}" @endif>{{ $badge['label'] }}</span>
                                    </span>
                                @endif
                            @endif
                        </span>
                        <span>{{ $msg->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '—' }}</span>
                    </div>
                    <div class="body">{{ $msg->body }}</div>
                    @if($msg->telegram_delivery_failed_at)
                        <div class="telegram-meta" style="color:#b45309">
                            Telegram delivery failed {{ $msg->telegram_delivery_failed_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            @if($msg->telegram_delivery_error) — {{ $msg->telegram_delivery_error }} @endif
                            @if($msg->sender_type === \App\Models\SupportMessage::SENDER_VISITOR && ($supportChatDiagnosticsAvailable ?? true))
                                <form method="post" action="{{ route('admin.support-chat.retry-message', $msg) }}" style="display:inline;margin-left:8px">
                                    @csrf
                                    <button type="submit" class="btn" style="padding:2px 8px;font-size:11px">Retry Telegram</button>
                                </form>
                            @endif
                        </div>
                    @endif
                    @if($msg->telegram_outbound_message_id || $msg->telegram_inbound_message_id || $msg->telegram_reply_to_message_id)
                        <div class="telegram-meta">
                            Telegram refs:
                            @if($msg->telegram_outbound_message_id) outbound #{{ $msg->telegram_outbound_message_id }} @endif
                            @if($msg->telegram_inbound_message_id) inbound #{{ $msg->telegram_inbound_message_id }} @endif
                            @if($msg->telegram_reply_to_message_id) reply-to #{{ $msg->telegram_reply_to_message_id }} @endif
                        </div>
                    @endif
                </article>
            @endforeach
            @if($conversation->messages->isEmpty())
                <p class="muted">No messages yet.</p>
            @endif
        </div>
    </div>
@endsection
