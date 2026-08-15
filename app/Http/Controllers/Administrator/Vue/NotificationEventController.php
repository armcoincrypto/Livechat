<?php

namespace App\Http\Controllers\Administrator\Vue;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\NotificationEventsResources;
use App\Models\NotificationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class NotificationEventController extends Controller
{
    /**
     * Получаем уведомления
     *
     * @return JsonResponse
     */
    public function index()
    {
        $notifications = NotificationEvent::orderByDesc('id', 'desc')->paginate(5);
        return response()->json([
            'items' => new NotificationEventsResources($notifications)
        ]);
    }

    /**
     * Отмечаем все уведомления как прочитанные
     *
     * @return void
    */
    public function store()
    {
        \DB::table('notification_events')->where('is_read', 0)
            ->update(['is_read' => 1]);
    }

    /**
     * Обновляем уведомление по ID
     *
     * @param int $id
     */
    public function update(int $id)
    {
        NotificationEvent::find($id)->update(['is_read' => 1]);
    }
}
