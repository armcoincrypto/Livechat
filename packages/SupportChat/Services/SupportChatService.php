<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAttachment;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use iEXPackages\SupportChat\Contracts\SupportChatServiceInterface;
use iEXPackages\SupportChat\Jobs\SendSupportVisitorMessageToTelegramJob;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use iEXPackages\SupportChat\Services\SupportChatDiagnosticsLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class SupportChatService implements SupportChatServiceInterface
{
    public function __construct(
        private readonly SupportConversationLifecycleService $lifecycle,
        private readonly SupportTelegramForumTopicService $forumTopics,
    ) {}

    public function createConversation(array $validated, ?string $ip, ?string $userAgent): array
    {
        $maxLength = (int) config('support_chat.message_max_length', 8000);
        $body = $this->sanitizeMessageBody((string) $validated['message'], $maxLength);
        if ($body === '') {
            throw new HttpException(422, 'Message cannot be empty.');
        }

        $this->assertVisitorMessagePassesSpamGuards($body, null);

        $plainToken = $this->generateAccessToken();
        $tokenHash = $this->hashAccessToken($plainToken);

        $result = DB::transaction(function () use ($validated, $ip, $userAgent, $body, $plainToken, $tokenHash): array {
            $conversation = new SupportConversation;
            $conversation->uuid = (string) Str::uuid();
            $conversation->status = SupportConversation::STATUS_WAITING_OPERATOR;
            $conversation->visitor_name = $this->truncateUtf8((string) $validated['name'], 191);
            $conversation->visitor_email = $this->normalizeEmail((string) $validated['email']);
            $conversation->visitor_ip = $ip;
            $conversation->user_agent = $userAgent !== null ? $this->truncateUtf8($userAgent, 512) : null;
            $conversation->page_url = isset($validated['page_url']) ? $this->truncateUtf8((string) $validated['page_url'], 2048) : null;
            $conversation->locale = isset($validated['locale']) ? $this->truncateUtf8((string) $validated['locale'], 16) : null;
            $conversation->access_token_hash = $tokenHash;
            $conversation->access_token_version = 1;
            $conversation->save();

            $message = $this->persistVisitorMessage($conversation, $body);
            $this->attachVisitorTimezoneMetadataIfPresent($message, $validated);
            $this->touchConversationAfterVisitorMessage($conversation);

            Log::info('support-chat lifecycle: conversation_created', [
                'conversation_uuid' => $conversation->uuid,
                'public_support_id' => $conversation->public_support_id,
                'status' => $conversation->status,
            ]);

            return [
                'conversation' => $conversation->fresh(),
                'access_token' => $plainToken,
                'messages' => $conversation->messages()->with(['attachments'])->get(),
                'first_message' => $message,
            ];
        });

        unset($result['first_message']);

        return $result;
    }

    public function addVisitorMessage(SupportConversation $conversation, string $body): SupportMessage
    {
        $maxLength = (int) config('support_chat.message_max_length', 8000);
        $body = $this->sanitizeMessageBody($body, $maxLength);
        if ($body === '') {
            throw new HttpException(422, 'Message cannot be empty.');
        }

        $this->assertVisitorMessagePassesSpamGuards($body, (int) $conversation->id);

        $reopenedByVisitor = false;

        $message = DB::transaction(function () use ($conversation, $body, &$reopenedByVisitor): SupportMessage {
            $conversation->refresh();

            $reopenedByVisitor = $this->lifecycle->reopenIfClosedForVisitor($conversation);
            if ($reopenedByVisitor) {
                $this->scheduleTelegramForumTopicReopenAfterCommitIfApplicable((int) $conversation->id);
            }

            $message = $this->persistVisitorMessage($conversation, $body);
            $this->touchConversationAfterVisitorMessage($conversation);

            return $message;
        });

        return $message;
    }

    public function getMessagesSince(SupportConversation $conversation, int $afterId, int $limit): array
    {
        if (filter_var(config('support_chat.message_states_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markOperatorMessagesSeenByVisitor((int) $conversation->id);
        }

        $fetchLimit = $limit + 1;
        $query = $conversation->messages()
            ->with(['attachments'])
            ->where('id', '>', $afterId)
            ->orderBy('id');

        $rows = $query->limit($fetchLimit)->get();

        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        return [
            'messages' => $rows,
            'has_more' => $hasMore,
        ];
    }

    public function sanitizeMessageBody(string $body, int $maxLength): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        $stripped = strip_tags($trimmed);
        if (mb_strlen($stripped) > $maxLength) {
            $stripped = mb_substr($stripped, 0, $maxLength);
        }

        return trim($stripped);
    }

    /**
     * Phase-1 spam hardening: optional duplicate cooldown and conservative repetition checks.
     * Telegram dispatch is unchanged; rejects happen before persistence.
     */
    private function assertVisitorMessagePassesSpamGuards(string $body, ?int $conversationId, bool $isAttachmentKind = false): void
    {
        if (! filter_var(config('support_chat.spam.hardening_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $ip = Request::ip();

        if (filter_var(config('support_chat.spam.repeated_character_guard_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            if ($this->isUniformSingleCharacterMessage($body)) {
                $this->rejectSpamJson(
                    422,
                    'This message cannot be sent. Please use readable text.',
                    'uniform_repeated_characters',
                    'uniform_single_character_message',
                    $conversationId,
                    $ip
                );
            }

            if ($this->hasExcessiveRepeatedCharacterRun($body)) {
                $this->rejectSpamJson(
                    422,
                    'This message cannot be sent. Please shorten repeated characters.',
                    'excessive_repeated_characters',
                    'excessive_repeated_character_run',
                    $conversationId,
                    $ip
                );
            }
        }

        if ($conversationId === null) {
            return;
        }

        if ($isAttachmentKind && $body === '') {
            return;
        }

        if (! filter_var(config('support_chat.spam.duplicate_cooldown_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $seconds = (int) config('support_chat.spam.duplicate_cooldown_seconds', 15);
        $threshold = now()->subSeconds($seconds);

        $lastVisitor = SupportMessage::query()
            ->where('support_conversation_id', $conversationId)
            ->where('sender_type', SupportMessage::SENDER_VISITOR)
            ->orderByDesc('id')
            ->first(['id', 'body', 'created_at']);

        if ($lastVisitor === null) {
            return;
        }

        if ($lastVisitor->body !== $body) {
            return;
        }

        if ($lastVisitor->created_at === null || $lastVisitor->created_at->lt($threshold)) {
            return;
        }

        $this->rejectSpamJson(
            429,
            'Please wait before sending the same message again.',
            'duplicate_message_cooldown',
            'duplicate_message_within_cooldown',
            $conversationId,
            $ip
        );
    }

    private function isUniformSingleCharacterMessage(string $body): bool
    {
        $minLen = (int) config('support_chat.spam.uniform_single_char_min_length', 64);
        $len = mb_strlen($body, 'UTF-8');
        if ($len < $minLen) {
            return false;
        }

        $chars = preg_split('//u', $body, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false || $chars === []) {
            return false;
        }

        $first = $chars[0];
        foreach ($chars as $ch) {
            if ($ch !== $first) {
                return false;
            }
        }

        return true;
    }

    private function hasExcessiveRepeatedCharacterRun(string $body): bool
    {
        $minMessageLen = (int) config('support_chat.spam.repeated_char_message_min_length', 120);
        $len = mb_strlen($body, 'UTF-8');
        if ($len < $minMessageLen) {
            return false;
        }

        $threshold = (int) config('support_chat.spam.max_repeated_char_run', 200);
        $chars = preg_split('//u', $body, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false || $chars === []) {
            return false;
        }

        $maxRun = 1;
        $run = 1;
        $prev = $chars[0];
        $slice = array_slice($chars, 1);
        foreach ($slice as $ch) {
            if ($ch === $prev) {
                $run++;
                if ($run > $maxRun) {
                    $maxRun = $run;
                }
            } else {
                $run = 1;
            }
            $prev = $ch;
        }

        return $maxRun >= $threshold;
    }

    private function rejectSpamJson(
        int $httpStatus,
        string $clientMessage,
        string $clientCode,
        string $logReason,
        ?int $conversationId,
        ?string $ip,
    ): never {
        SupportChatDiagnosticsLog::spamRejected([
            'reason' => $logReason,
            'error_code' => $clientCode,
            'conversation_id' => $conversationId,
        ]);

        throw new HttpResponseException(response()->json([
            'message' => $clientMessage,
            'code' => $clientCode,
        ], $httpStatus));
    }

    private function generateAccessToken(): string
    {
        $length = (int) config('support_chat.access_token.length', 64);
        $length = max(32, min(128, $length));

        return Str::password(length: $length, symbols: false);
    }

    private function hashAccessToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Persist a visitor attachment upload as a message row so GET /messages can render it after refresh.
     * Telegram file forward uses SendSupportAttachmentToTelegramJob only (no duplicate text notify).
     */
    public function persistVisitorAttachmentMessage(
        SupportConversation $conversation,
        SupportAttachment $attachment,
    ): SupportMessage {
        $maxLength = (int) config('support_chat.message_max_length', 8000);
        $body = $this->sanitizeMessageBody((string) ($attachment->caption ?? ''), $maxLength);

        if ($body !== '') {
            $this->assertVisitorMessagePassesSpamGuards($body, (int) $conversation->id, true);
        }

        return DB::transaction(function () use ($conversation, $attachment, $body): SupportMessage {
            $conversation->refresh();

            $reopenedByVisitor = $this->lifecycle->reopenIfClosedForVisitor($conversation);
            if ($reopenedByVisitor) {
                $this->scheduleTelegramForumTopicReopenAfterCommitIfApplicable((int) $conversation->id);
            }

            $message = new SupportMessage;
            $message->support_conversation_id = (int) $conversation->id;
            $message->sender_type = SupportMessage::SENDER_VISITOR;
            $message->body = $body;
            $message->metadata = ['kind' => 'attachment'];
            $message->save();

            $attachment->support_message_id = $message->id;
            $attachment->save();

            $message->load(['attachments', 'conversation']);

            $this->touchConversationAfterVisitorMessage($conversation);

            SupportChatDiagnosticsLog::messageSent([
                'conversation_id' => $message->support_conversation_id,
                'public_support_id' => $message->conversation?->public_support_id,
                'support_message_id' => $message->id,
                'channel' => 'website_attachment',
            ]);

            return $message;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function attachVisitorTimezoneMetadataIfPresent(SupportMessage $message, array $validated): void
    {
        if (! isset($validated['timezone'])) {
            return;
        }

        $timezone = $this->truncateUtf8(trim((string) $validated['timezone']), 64);
        if ($timezone === '') {
            return;
        }

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $metadata['visitor_timezone'] = $timezone;
        $message->metadata = $metadata;
        $message->save();
    }

    private function persistVisitorMessage(SupportConversation $conversation, string $body): SupportMessage
    {
        $message = new SupportMessage;
        $message->support_conversation_id = (int) $conversation->id;
        $message->sender_type = SupportMessage::SENDER_VISITOR;
        $message->body = $body;
        $message->save();

        $message->loadMissing('conversation');
        SupportChatDiagnosticsLog::messageSent([
            'conversation_id' => $message->support_conversation_id,
            'public_support_id' => $message->conversation?->public_support_id,
            'support_message_id' => $message->id,
            'channel' => 'website',
        ]);

        $this->scheduleVisitorTelegramNotifyAfterCommitIfApplicable($message);

        return $message;
    }

    private function scheduleVisitorTelegramNotifyAfterCommitIfApplicable(SupportMessage $message): void
    {
        if ($message->sender_type !== SupportMessage::SENDER_VISITOR) {
            return;
        }

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        if (($metadata['kind'] ?? null) === 'attachment') {
            return;
        }

        if (! filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $token = trim((string) config('support_chat.telegram.bot_token', ''));
        $groupRaw = config('support_chat.telegram.group_id');
        $groupId = $groupRaw === null ? '' : trim((string) $groupRaw);

        if ($token === '' || $groupId === '') {
            return;
        }

        DB::afterCommit(function () use ($message): void {
            SendSupportVisitorMessageToTelegramJob::dispatch($message->id)->afterResponse();
        });
    }

    /**
     * After a visitor message reopens a previously closed conversation, reopen the Telegram forum topic
     * before the visitor Telegram notification job runs (afterCommit order: this callback first).
     */
    private function scheduleTelegramForumTopicReopenAfterCommitIfApplicable(int $supportConversationId): void
    {
        if (! filter_var(config('support_chat.telegram.auto_close_topics', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (! filter_var(config('support_chat.telegram.use_forum_topics', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        DB::afterCommit(function () use ($supportConversationId): void {
            $conversation = SupportConversation::query()->find($supportConversationId);
            if ($conversation === null) {
                return;
            }

            if ($conversation->telegram_forum_topic_id === null || (int) $conversation->telegram_forum_topic_id < 1) {
                return;
            }

            if ($conversation->telegram_topic_closed_at === null) {
                return;
            }

            $this->forumTopics->reopenForumTopic($conversation);
        });
    }

    private function markOperatorMessagesSeenByVisitor(int $conversationId): void
    {
        $now = now();
        SupportMessage::query()
            ->where('support_conversation_id', $conversationId)
            ->whereIn('sender_type', [SupportMessage::SENDER_OPERATOR, SupportMessage::SENDER_SYSTEM])
            ->whereNull('seen_by_visitor_at')
            ->update(['seen_by_visitor_at' => $now]);
    }

    private function touchConversationAfterVisitorMessage(SupportConversation $conversation): void
    {
        $now = now();
        $conversation->forceFill([
            'last_message_at' => $now,
            'last_message_sender_type' => SupportMessage::SENDER_VISITOR,
            'last_visitor_message_at' => $now,
            'status' => SupportConversation::STATUS_WAITING_OPERATOR,
        ])->save();
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Truncate to a maximum number of UTF-8 characters without splitting codepoints.
     * Matches Laravel string length semantics for varchar limits (e.g. max:191).
     */
    private function truncateUtf8(string $value, int $maxCharacters): string
    {
        if ($maxCharacters < 1) {
            return '';
        }

        if (mb_strlen($value, 'UTF-8') <= $maxCharacters) {
            return $value;
        }

        return mb_substr($value, 0, $maxCharacters, 'UTF-8');
    }
}
