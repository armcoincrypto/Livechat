<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Contracts;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Collection;

interface SupportChatServiceInterface
{
    /**
     * @param  array{name: string, email: string, message: string, locale?: string|null, page_url?: string|null}  $validated
     * @return array{
     *     conversation: SupportConversation,
     *     access_token: string,
     *     messages: Collection<int, SupportMessage>
     * }
     */
    public function createConversation(array $validated, ?string $ip, ?string $userAgent): array;

    public function addVisitorMessage(SupportConversation $conversation, string $body): SupportMessage;

    /**
     * @return array{messages: Collection<int, SupportMessage>, has_more: bool}
     */
    public function getMessagesSince(SupportConversation $conversation, int $afterId, int $limit): array;

    public function sanitizeMessageBody(string $body, int $maxLength): string;
}
