<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat\Repositories;

use App\Http\Resources\Admin\TaskMessageResponse;
use App\Models\TaskMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class TaskMessageRepository
{
    /**
     * Создать сообщение клиента.
     */
    public function createClientMessage(int $taskId, int $userId, ?string $message, ?string $fileName): TaskMessage
    {
        /** @var TaskMessage $created */
        $created = TaskMessage::query()->create([
            'id_task'   => $taskId,
            'user_id'   => $userId,
            'type_user' => 0,
            'message'   => $message,
            'file_path' => $fileName,
            'is_view'   => 1,
            'is_read'   => 0,
        ]);

        return $created;
    }

    /**
     * Создать сообщение менеджера.
     */
    public function createManagerMessage(int $taskId, int $userId, int $managerId, string $message): TaskMessage
    {
        /** @var TaskMessage $created */
        $created = TaskMessage::query()->create([
            'user_id'    => $userId,
            'id_task'    => $taskId,
            'message'    => $message,
            'id_manager' => $managerId,
            'type_user'  => 1,
            'is_view'    => 1,
            'is_read'    => 1,
        ]);

        return $created;
    }

    /**
     * Все сообщения заявки.
     */
    public function getMessagesByTaskId(int $taskId)
    {
        return TaskMessage::query()->where('id_task', $taskId)->get();
    }

    /**
     * Список чатов в админке (как у тебя в OrderChatsVueController::getOrders).
     */
    public function getChatsList(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = TaskMessage::query()
            ->filter($filters)
            ->withCount('unread');

        $searchValue = $filters['searchValue'] ?? null;
        if (is_numeric($searchValue) && (int)$searchValue > 0) {
            $sv = (int)$searchValue;
            $query->whereHas('task', function ($q) use ($sv) {
                $q->whereLike('public_id', $sv)->orWhereLike('id', $sv);
            });
        }

        $query->whereIn('id', function ($sub) {
            $sub->from('tasks_messages')->groupBy('id_task')->selectRaw('MAX(id)');
        })->orderByDesc('unread_count')->orderByDesc('created_at');

        return $query->paginate($perPage);
    }

    public function markChatRead(int $taskId): void
    {
        TaskMessage::query()->where('id_task', $taskId)->update(['is_read' => 1]);
    }

    public function markAllRead(): void
    {
        \DB::table('tasks_messages')->update(['is_read' => 1]);
    }
}
