<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Settings\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TelegramNotificationResources;
use App\Models\TelegramNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TelegramNotificationController extends Controller
{
    /**
     * Список всех чатов/каналов
     *
     * @param Request $request
     * @return TelegramNotificationResources
     */
    public function index(Request $request): TelegramNotificationResources
    {
        $items = TelegramNotification::orderBy('id', 'desc')->get();

        return new TelegramNotificationResources($items);
    }

    /**
     * Добавление новой записи Telegram уведомлений
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->has('updateField')) {
            $updateField = (string) $request->get('updateField');

            if ($updateField === 'status') {
                $item = TelegramNotification::find((int) $request->id);
                if (!$item) {
                    return response()->json([
                        'status' => 1,
                        'message' => 'Запись не найдена',
                    ], 404);
                }

                $item->update([
                    'status' => (int) $request->status,
                ]);

                return response()->json(['status' => 0]);
            }

            if ($updateField === 'send_to_bot') {
                $item = TelegramNotification::find((int) $request->id);
                if (!$item) {
                    return response()->json([
                        'status' => 1,
                        'message' => 'Запись не найдена',
                    ], 404);
                }

                $item->update([
                    'send_to_bot' => (bool) $request->boolean('send_to_bot'),
                ]);

                return response()->json(['status' => 0]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'token_access' => ['required', 'regex:/^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$/'],
            'send_to_bot' => ['nullable', 'boolean'],
        ]);

        $validator->setAttributeNames([
            'name' => __('Название'),
            'token_access' => __('Токен доступа Telegram-бота'),
            'send_to_bot' => __('Отправлять только в бота'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $item = TelegramNotification::create([
            'name' => (string) $request->name,
            'token_access' => (string) $request->token_access,
            'status' => (int) $request->status,
            'send_to_bot' => (bool) $request->boolean('send_to_bot'),
        ]);

        return response()->json([
            'status' => 0,
            'message' => $item->name . ' успешно добавлен',
        ]);
    }

    public function edit(int $id, Request $request): JsonResponse
    {
        $item = TelegramNotification::find($id);
        if (!$item) {
            return response()->json([
                'status' => 1,
                'message' => 'Запись не найдена',
            ], 404);
        }

        // Получение / проверка привязки чата
        if ($request->has('get_id_channel')) {
            // 1) Если чат уже привязан — пробуем подтвердить и подтянуть актуальные данные
            if (!empty($item->id_channel)) {
                try {
                    $chat = $this->telegramGetChat($item->token_access, (string) $item->id_channel);

                    $title = $chat['title']
                        ?? $chat['username']
                        ?? $chat['first_name']
                        ?? $item->channel_name;

                    if (!empty($title) && $title !== $item->channel_name) {
                        $item->update(['channel_name' => $title]);
                    }

                    return response()->json([
                        'status' => 0,
                        'message' => 'Чат уже привязан',
                        'id_channel' => $item->id_channel,
                        'channel_name' => $title,
                    ]);
                } catch (\Throwable $e) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Чат привязан, но не удалось подтвердить доступ бота. Проверьте токен/права и что бот имеет доступ к этому чату.',
                        'id_channel' => $item->id_channel,
                        'channel_name' => $item->channel_name,
                    ]);
                }
            }

            // 2) Чат не привязан — пытаемся найти по getUpdates
            try {
                $updates = $this->telegramGetUpdates($item->token_access);
                $webhook = $this->telegramGetWebhookInfo($item->token_access);
                $webhookUrl = (string) ($webhook['url'] ?? '');

                $updatesSummary = $this->summarizeUpdates($updates, 20);

                $resolved = $this->resolveChannelFromUpdates($updates);
                if ($resolved !== null) {
                    $item->update([
                        'id_channel' => $resolved['id'],
                        'channel_name' => $resolved['title'],
                    ]);

                    return response()->json([
                        'status' => 0,
                        'message' => 'Чат подключен',
                        'id_channel' => $resolved['id'],
                        'channel_name' => $resolved['title'],
                    ]);
                }

                if ($webhookUrl !== '') {
                    return response()->json([
                        'status' => 1,
                        'message' => 'Чат не привязан. У бота включён webhook, поэтому getUpdates обычно не видит сообщения. Отключите webhook (setWebhook с пустым url) или привязывайте через отдельный endpoint, затем снова нажмите «Получить ID».',
                        'debug' => [
                            'webhook_url' => $webhookUrl,
                            'updates_count' => is_array($updates['result'] ?? null) ? count($updates['result']) : 0,
                            'updates_summary' => $updatesSummary,
                        ],
                    ], 200, [], JSON_UNESCAPED_UNICODE);
                }

                return response()->json([
                    'status' => 1,
                    'message' => 'Чат не привязан. Мы получили последние обновления бота, но среди них нет подходящих событий. Если вы привязываете канал — добавьте бота в канал как администратора и опубликуйте новое сообщение. Если вы привязываете бота — просто напишите боту любое сообщение, затем нажмите «Получить ID» ещё раз.',
                    'debug' => [
                        'updates_count' => is_array($updates['result'] ?? null) ? count($updates['result']) : 0,
                        'updates_summary' => $updatesSummary,
                    ],
                ], 200, [], JSON_UNESCAPED_UNICODE);
            } catch (\Throwable $e) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Не удалось получить данные от Telegram API. Проверьте токен бота и доступность Telegram.',
                ], 422);
            }
        }

        $configs = [
            'is_send_statistics' => 'Отсылать ежедневную статистику обменов',
            'is_process_created_order' => 'Отсылать уведомление о новых заявках (после нажатия кнопки "Создать заявку")',
            'is_new_order_for_operator' => 'Отсылать уведомление о новых заявках (после подтверждения)',
            'is_failed_for_pay' => 'Отсылать уведомление о сбоях при авто-выплате',
            'is_order_verification_card' => 'Отсылать уведомление на верификацию счета',
            'is_order_withdrawal' => 'Отсылать уведомление о новых заявках на выплату бонусов',
            'is_first_confirm_blockchain' => 'Отсылать уведомление при получении 1-го подтверждения от сети',
            'is_order_pay' => 'Отсылать уведомление об авто-выплате',
            'is_google2fa' => 'Отсылать уведомление о действиях Google2fa',
            'is_allowed_admin' => 'Отсылать уведомление об успешных авторизациях в админ-панели',
            'is_admin_ip_change' => 'Отсылать уведомление если у менеджером меняется IP адрес',
        ];

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name,
                'token_access' => $item->token_access,
                'status' => (bool) $item->status,
                'send_to_bot' => (bool) $item->send_to_bot,
                'id_channel' => $item->id_channel,
                'channel_name' => $item->channel_name,
                'ext_params' => collect((array) $item->ext_params)->keys(),
            ],
            'notifications' => collect($configs)->map(function ($value, $key) {
                return [
                    'id' => $key,
                    'label' => $value,
                ];
            })->values(),
        ]);
    }

    /**
     * Диагностика привязки Telegram-канала/чата для конкретной записи.
     *
     * GET /admin/settings/telegram-notifications/{id}/check
     */
    public function check(int $id): JsonResponse
    {
        $item = TelegramNotification::find($id);
        if (!$item) {
            return response()->json([
                'status' => 1,
                'message' => 'Запись не найдена',
            ], 404);
        }

        $binding = [
            'is_bound' => !empty($item->id_channel),
            'id_channel' => $item->id_channel,
            'channel_name' => $item->channel_name,
            'verified' => false,
            'verification_error' => null,
            'chat' => null,
        ];

        if (!empty($item->id_channel)) {
            try {
                $chat = $this->telegramGetChat($item->token_access, (string) $item->id_channel);
                $binding['verified'] = true;
                $binding['chat'] = $this->safeChatInfo($chat);

                $title = $chat['title'] ?? $chat['username'] ?? $chat['first_name'] ?? $item->channel_name;
                if (!empty($title) && $title !== $item->channel_name) {
                    $binding['channel_name'] = $title;
                }
            } catch (\Throwable $e) {
                $binding['verification_error'] = 'Не удалось подтвердить доступ бота к чату. Проверьте токен/права и что бот имеет доступ к этому чату.';
            }
        }

        $webhook = [];
        $webhookUrl = '';
        try {
            $webhook = $this->telegramGetWebhookInfo($item->token_access);
            $webhookUrl = (string) ($webhook['url'] ?? '');
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $updates = $this->telegramGetUpdates($item->token_access);
            $summary = $this->summarizeUpdates($updates, 20);
            $candidates = $this->extractChatCandidatesFromUpdates($updates, 20);
            $recommended = $this->resolveChannelFromUpdates($updates);

            return response()->json([
                'status' => 0,
                'message' => 'Диагностика получена',
                'binding' => $binding,
                'webhook' => [
                    'url' => $webhookUrl,
                    'raw' => $webhook,
                ],
                'updates' => [
                    'updates_count' => is_array($updates['result'] ?? null) ? count($updates['result']) : 0,
                    'summary' => $summary,
                ],
                'candidates' => $candidates,
                'recommended' => $recommended,
                'hints' => $this->buildCheckHints($webhookUrl, $summary),
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 1,
                'message' => 'Не удалось получить данные от Telegram API. Проверьте токен и доступность Telegram.',
                'binding' => $binding,
                'webhook' => [
                    'url' => $webhookUrl,
                ],
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Привязка выбранного Telegram-чата/канала к записи.
     *
     * POST /admin/settings/telegram-notifications/{id}/bind
     * body: { chat_id: -100..., channel_name?: string }
     */
    public function bind(int $id, Request $request): JsonResponse
    {
        $item = TelegramNotification::find($id);
        if (!$item) {
            return response()->json([
                'status' => 1,
                'message' => 'Запись не найдена',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'chat_id' => ['required', 'integer'],
            'channel_name' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->setAttributeNames([
            'chat_id' => 'ID чата',
            'channel_name' => 'Название',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $chatId = (int) $request->get('chat_id');

        $finalName = (string) ($request->get('channel_name') ?? '');
        $verified = false;
        $chatSafe = null;

        try {
            $chat = $this->telegramGetChat($item->token_access, (string) $chatId);
            $verified = true;
            $chatSafe = $this->safeChatInfo($chat);
            $finalName = (string) ($chat['title'] ?? $chat['username'] ?? $chat['first_name'] ?? $finalName);
        } catch (\Throwable $e) {
            // allow save without verification
        }

        if ($finalName === '') {
            $finalName = 'Telegram chat';
        }

        $item->update([
            'id_channel' => $chatId,
            'channel_name' => $finalName,
        ]);

        return response()->json([
            'status' => 0,
            'message' => $verified
                ? 'Чат привязан и доступ подтвержден'
                : 'Чат привязан, но доступ не подтвержден. Убедитесь, что бот имеет доступ к этому чату.',
            'id_channel' => $item->id_channel,
            'channel_name' => $item->channel_name,
            'verified' => $verified,
            'chat' => $chatSafe,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'token_access' => ['required', 'regex:/^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$/'],
            'send_to_bot' => ['nullable', 'boolean'],
        ]);

        $validator->setAttributeNames([
            'name' => __('Название'),
            'token_access' => __('Токен доступа Telegram-бота'),
            'send_to_bot' => __('Отправлять только в бота'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $item = TelegramNotification::find($id);
        if (!$item) {
            return response()->json([
                'status' => 1,
                'message' => 'Запись не найдена',
            ], 404);
        }

        $item->update([
            'name' => (string) $request->name,
            'token_access' => (string) $request->token_access,
            'status' => (int) $request->status,
            'send_to_bot' => (bool) $request->boolean('send_to_bot'),
            'ext_params' => $request->ext_params,
        ]);

        return response()->json([
            'status' => 0,
            'message' => $item->name . ' успешно обновлен',
        ]);
    }

    /**
     * Удаляем Telegram чат
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $item = TelegramNotification::findOrFail($id);
        $oldItem = $item;
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name . ' успешно удален',
        ]);
    }

    /**
     * Базовый запрос к Telegram Bot API.
     *
     * @return array<string, mixed>
     */
    private function telegramRequest(string $token, string $method, array $params = []): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new \RuntimeException('Empty bot token');
        }

        $url = 'https://api.telegram.org/bot' . $token . '/' . ltrim($method, '/');

        $response = Http::timeout(10)
            ->retry(2, 250)
            ->asJson()
            ->get($url, $params);

        if (!$response->ok()) {
            throw new \RuntimeException('Telegram API HTTP error: ' . $response->status());
        }

        $data = $response->json();

        if (!is_array($data) || !array_key_exists('ok', $data)) {
            throw new \RuntimeException('Telegram API invalid response');
        }

        if (($data['ok'] ?? false) !== true) {
            $desc = (string) ($data['description'] ?? 'Unknown error');
            throw new \RuntimeException('Telegram API error: ' . $desc);
        }

        return $data;
    }

    /**
     * Получение обновлений (для сценария "поймать" channel_post или message из лички).
     *
     * @return array<string, mixed>
     */
    private function telegramGetUpdates(string $token): array
    {
        return $this->telegramRequest($token, 'getUpdates', [
            'offset' => -50,
            'limit' => 100,
            'timeout' => 0,
            'allowed_updates' => json_encode(['channel_post', 'message']),
        ]);
    }

    /**
     * Получение информации о чате/канале по id.
     *
     * @return array<string, mixed>
     */
    private function telegramGetChat(string $token, string $chatId): array
    {
        $res = $this->telegramRequest($token, 'getChat', [
            'chat_id' => $chatId,
        ]);

        $result = $res['result'] ?? null;
        if (!is_array($result)) {
            throw new \RuntimeException('Telegram getChat: empty result');
        }

        return $result;
    }

    /**
     * Пытаемся извлечь чат/канал из getUpdates.
     * Возвращает ['id' => <int>, 'title' => <string>] или null.
     */
    private function resolveChannelFromUpdates(array $updates): ?array
    {
        $result = $updates['result'] ?? null;
        if (!is_array($result) || empty($result)) {
            return null;
        }

        foreach (array_reverse($result) as $u) {
            if (!is_array($u)) {
                continue;
            }

            // Каналы
            $channelPost = $u['channel_post'] ?? null;
            if (is_array($channelPost)) {
                $senderChat = $channelPost['sender_chat'] ?? null;
                if (is_array($senderChat) && isset($senderChat['id'])) {
                    return [
                        'id' => (int) $senderChat['id'],
                        'title' => (string) ($senderChat['title'] ?? $senderChat['username'] ?? $senderChat['first_name'] ?? 'Telegram chat'),
                    ];
                }
            }

            // Группы/супергруппы/личка
            $message = $u['message'] ?? null;
            if (is_array($message)) {
                $chat = $message['chat'] ?? null;
                if (is_array($chat) && isset($chat['id']) && in_array(($chat['type'] ?? null), ['supergroup', 'group', 'private'], true)) {
                    return [
                        'id' => (int) $chat['id'],
                        'title' => (string) ($chat['title'] ?? $chat['username'] ?? $chat['first_name'] ?? 'Telegram chat'),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Информация о webhook.
     *
     * @return array<string, mixed>
     */
    private function telegramGetWebhookInfo(string $token): array
    {
        $res = $this->telegramRequest($token, 'getWebhookInfo');

        $result = $res['result'] ?? null;
        if (!is_array($result)) {
            return [];
        }

        return $result;
    }

    /**
     * Короткое резюме последних обновлений Telegram.
     *
     * @return array<string, mixed>
     */
    private function summarizeUpdates(array $updates, int $limit = 20): array
    {
        $result = $updates['result'] ?? null;
        if (!is_array($result)) {
            return [
                'total' => 0,
                'channel_posts' => 0,
                'messages' => 0,
                'last_update_id' => null,
                'last_items' => [],
            ];
        }

        $total = count($result);
        $channelPosts = 0;
        $messages = 0;

        $items = [];
        $slice = array_slice($result, max(0, $total - $limit));

        foreach ($slice as $u) {
            if (!is_array($u)) {
                continue;
            }

            $updateId = $u['update_id'] ?? null;
            $type = null;
            $chat = null;
            $text = null;
            $date = null;

            if (isset($u['channel_post']) && is_array($u['channel_post'])) {
                $type = 'channel_post';
                $channelPosts++;
                $chat = $u['channel_post']['sender_chat'] ?? $u['channel_post']['chat'] ?? null;
                $text = $u['channel_post']['text'] ?? null;
                $date = $u['channel_post']['date'] ?? null;
            } elseif (isset($u['message']) && is_array($u['message'])) {
                $type = 'message';
                $messages++;
                $chat = $u['message']['chat'] ?? null;
                $text = $u['message']['text'] ?? null;
                $date = $u['message']['date'] ?? null;
            } else {
                $type = 'other';
            }

            $chatId = is_array($chat) ? ($chat['id'] ?? null) : null;
            $chatType = is_array($chat) ? ($chat['type'] ?? null) : null;
            $chatTitle = is_array($chat) ? ($chat['title'] ?? null) : null;
            $chatUsername = is_array($chat) ? ($chat['username'] ?? null) : null;

            $items[] = [
                'update_id' => is_numeric($updateId) ? (int) $updateId : $updateId,
                'type' => $type,
                'chat_type' => $chatType,
                'chat_id' => $chatId,
                'chat_title' => $chatTitle,
                'chat_username' => $chatUsername,
                'text' => is_string($text) ? Str::limit($text, 120, '…') : null,
                'date' => $date,
            ];
        }

        $lastUpdateId = null;
        if ($total > 0 && is_array($result[$total - 1])) {
            $lastUpdateId = $result[$total - 1]['update_id'] ?? null;
        }

        return [
            'total' => $total,
            'channel_posts' => $channelPosts,
            'messages' => $messages,
            'last_update_id' => is_numeric($lastUpdateId) ? (int) $lastUpdateId : $lastUpdateId,
            'last_items' => $items,
        ];
    }

    /**
     * Безопасный набор данных по чату.
     *
     * @param array<string, mixed> $chat
     * @return array<string, mixed>
     */
    private function safeChatInfo(array $chat): array
    {
        return [
            'id' => $chat['id'] ?? null,
            'type' => $chat['type'] ?? null,
            'title' => $chat['title'] ?? null,
            'username' => $chat['username'] ?? null,
            'first_name' => $chat['first_name'] ?? null,
            'invite_link' => $chat['invite_link'] ?? null,
            'linked_chat_id' => $chat['linked_chat_id'] ?? null,
        ];
    }

    /**
     * Извлекаем кандидатов (каналы/чаты), которые бот "видел" в последних updates.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractChatCandidatesFromUpdates(array $updates, int $limit = 20): array
    {
        $result = $updates['result'] ?? null;
        if (!is_array($result) || empty($result)) {
            return [];
        }

        $total = count($result);
        $slice = array_slice($result, max(0, $total - $limit));

        $unique = [];

        foreach ($slice as $u) {
            if (!is_array($u)) {
                continue;
            }

            // channel_post -> sender_chat (канал)
            if (isset($u['channel_post']) && is_array($u['channel_post'])) {
                $sender = $u['channel_post']['sender_chat'] ?? null;
                if (is_array($sender) && isset($sender['id'])) {
                    $cid = (int) $sender['id'];
                    $unique[$cid] = [
                        'chat_id' => $cid,
                        'type' => $sender['type'] ?? 'channel',
                        'title' => $sender['title'] ?? null,
                        'username' => $sender['username'] ?? null,
                        'first_name' => $sender['first_name'] ?? null,
                        'source' => 'channel_post',
                    ];
                }
            }

            // message -> group/supergroup/private
            if (isset($u['message']) && is_array($u['message'])) {
                $chat = $u['message']['chat'] ?? null;
                if (is_array($chat) && isset($chat['id'])) {
                    $type = $chat['type'] ?? null;
                    if (in_array($type, ['group', 'supergroup', 'private'], true)) {
                        $cid = (int) $chat['id'];
                        $unique[$cid] = [
                            'chat_id' => $cid,
                            'type' => $type,
                            'title' => $chat['title'] ?? null,
                            'username' => $chat['username'] ?? null,
                            'first_name' => $chat['first_name'] ?? null,
                            'source' => 'message',
                        ];
                    }
                }
            }
        }

        return array_values($unique);
    }

    /**
     * Подсказки для UI.
     *
     * @param string $webhookUrl
     * @param array<string, mixed> $summary
     * @return array<int, string>
     */
    private function buildCheckHints(string $webhookUrl, array $summary): array
    {
        $hints = [];

        if ($webhookUrl !== '') {
            $hints[] = 'У бота включён webhook. В этом режиме getUpdates часто не показывает сообщения. Если вы привязываете через polling — отключите webhook (setWebhook с пустым url).';
        }

        $channelPosts = (int) ($summary['channel_posts'] ?? 0);
        $messages = (int) ($summary['messages'] ?? 0);

        if ($channelPosts === 0) {
            $hints[] = 'Среди последних обновлений нет channel_post. Если привязываете канал — добавьте бота в канал как администратора и опубликуйте новое сообщение.';
        }

        if ($messages === 0) {
            $hints[] = 'Среди последних обновлений нет message. Если привязываете бота (личный чат) — просто напишите боту любое сообщение.';
        }

        if ($messages > 0 && $channelPosts === 0) {
            $hints[] = 'Сейчас бот получает сообщения только из лички/чатов. Это нормально: канал появится только после события из канала.';
        }

        return $hints;
    }
}
