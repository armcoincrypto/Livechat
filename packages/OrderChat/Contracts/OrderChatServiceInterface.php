<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat\Contracts;

use App\Models\Task;
use App\Models\TaskMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface OrderChatServiceInterface
{
    /**
     * Получить сообщения чата для клиента по public_id.
     *
     * @param int $publicId Public ID заявки.
     * @param string $requestIp IP клиента (проверка доступа).
     * @return \Illuminate\Support\Collection<int, TaskMessage>
     */
    public function getClientMessages(int $publicId, string $requestIp);

    /**
     * Клиент отправляет сообщение (текст и/или файл).
     *
     * @param int $publicId Public ID заявки.
     * @param string $requestIp IP клиента (проверка доступа).
     * @param string|null $message Текст сообщения.
     * @param UploadedFile|null $file Файл (png/jpg/svg/pdf).
     * @return TaskMessage
     */
    public function clientSendMessage(int $publicId, string $requestIp, ?string $message, ?UploadedFile $file): TaskMessage;

    /**
     * Админ: получить сообщения по order_id.
     */
    public function getAdminMessages(int $orderId);

    /**
     * Админ: добавить сообщение от менеджера.
     */
    public function adminSendMessage(int $orderId, int $managerId, string $message): TaskMessage;

    /**
     * Админ: список чатов (последние сообщения) с фильтрацией/пагинацией.
     */
    public function getChatsList(array $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * Отметить сообщения чата как прочитанные.
     */
    public function markChatRead(int $orderId): void;

    /**
     * Отметить все сообщения как прочитанные.
     */
    public function markAllChatsRead(): void;

    /**
     * Быстрая проверка — включён ли чат.
     */
    public function isChatEnabled(): bool;
}
