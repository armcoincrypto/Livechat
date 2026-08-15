<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Models\NoticeExchange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    /**
     * Список уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $notifications = NoticeExchange::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'text' => $item->text,
                    'status' => (bool)$item->status,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                ]
            ];
        });

        return response()->json([
            'data' => $notifications,
            'total' => count($notifications)
        ]);
    }

    /**
     * Обработка и добавление уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'status' => ($request->has('status') ? (int)filter_var($request->get('status'), FILTER_VALIDATE_BOOLEAN ) : 0),
            'link' => ($request->has('link') ? $request->get('link') : null),
            'is_enabled_schedule' => ($request->has('is_enabled_schedule') ? (int)filter_var($request->get('is_enabled_schedule'), FILTER_VALIDATE_BOOLEAN ) : 0),
            'from_time' => ($request->has('from_time') ? $request->get('from_time') : null),
            'to_time' => ($request->has('to_time') ? $request->get('to_time') : 0),
            'is_blank' => ($request->has('is_blank') ? $request->get('is_blank') : 0),
            'bg_color' => $request->has('bg_color') ? $request->get('bg_color') : null,
            'text_color' => $request->has('text_color') ? $request->get('text_color') : null,
            'text_size' => $request->has('text_size') ? $request->get('text_size') : null,
            'text' => $request->text
        ];

        if ($request->hasFile('icon_notice')) {
            if (! \File::isDirectory(public_path('storage/notices/'))) {
                \File::makeDirectory(public_path('storage/notices/'), 0777, true, true);
            }

            $logo_icon = $request->file('icon_notice');
            $filename = sprintf('%s.%s', Str::random(10), $logo_icon->getClientOriginalExtension());

            $destinationPath = public_path('/storage/notices');
            $logo_icon->move($destinationPath, $filename);
            $options['icon_notice'] = $filename;
        }

        NoticeExchange::create($options);

        return response()->json([
            'status' => 0,
            'message' => __('Уведомление успешно добавлено')
        ]);
    }

    /**
     * Форма редактирования уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request, int $id)
    {
        $item = NoticeExchange::findOrFail($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'text' => $item->text,
                'status' => (bool)$item->status,
                'text_color' => (string)$item->text_color,
                'text_size' => $item->text_size,
                'bg_color' => (string)$item->bg_color,
                'link' => $item->link,
                'is_enabled_schedule' => (bool)$item->is_enabled_schedule,
                'from_time' => (string)$item->from_time,
                'to_time' => (string)$item->to_time,
                'is_blank' => (bool)$item->is_blank,
                'icon' => $item->icon_notice,
                'icon_path' => '/storage/notices/'.$item->icon_notice,
                'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                'created_at_human' => $item->created_at->diffForHumans(),
                'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                'updated_at_human' => $item->updated_at->diffForHumans()
            ]
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'text.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $notification = NoticeExchange::findOrFail($id);
        $filename = $notification->icon_notice;

        $options = [
            'status' => ($request->has('status') ? (int)filter_var($request->get('status'), FILTER_VALIDATE_BOOLEAN ) : 0),
            'link' => $request->filled('link') && $request->get('link') !== 'null'
                ? $request->get('link')
                : null,
            'is_enabled_schedule' => ($request->has('is_enabled_schedule') ? (int)filter_var($request->get('is_enabled_schedule'), FILTER_VALIDATE_BOOLEAN ) : 0),
            'from_time' => ($request->has('from_time') ? $request->get('from_time') : null),
            'to_time' => ($request->has('to_time') ? $request->get('to_time') : 0),
            'is_blank' => $request->filled('link') && $request->get('link') !== 'null'
                ? ($request->has('is_blank') ? $request->get('is_blank') : 0)
                : 0,
            'bg_color' => $request->has('bg_color') ? $request->get('bg_color') : null,
            'text_color' => $request->has('text_color') ? $request->get('text_color') : null,
            'text_size' => $request->has('text_size') ? $request->get('text_size') : null,
            'text' => $request->text
        ];

        if ($request->hasFile('icon_notice')) {
            if (! \File::isDirectory(public_path('storage/notices/'))) {
                \File::makeDirectory(public_path('storage/notices/'), 0777, true, true);
            }

            // Удаляем актуальное фото
            iex_file_delete(public_path('storage/notices/'.$filename));

            $logo_icon = $request->file('icon_notice');
            $filename = sprintf('%s.%s', Str::random(10), $logo_icon->getClientOriginalExtension());

            $destinationPath = public_path('/storage/notices');
            $logo_icon->move($destinationPath, $filename);
            $options['icon_notice'] = $filename;
        }

        $notification->update($options);

        return response()->json([
            'status' => 0,
            'message' => 'Уведомление успешно обновлена'
        ]);
    }

    /**
     * Удаление уведомлений
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $notice = NoticeExchange::findOrFail($id);
        $notice->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Уведомление успешно удалено'
        ]);
    }
}
