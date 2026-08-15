<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Models\JobSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JobScheduleController extends Controller
{
    /**
     * Дополнительные фильтры
     */
    protected array $allowFiltered = [
        'is_job_schedule_default_status',
    ];

    public function index()
    {
        $items = JobSchedule::get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'status' => (bool)$item->status,
                    'all_day' => (bool)($item->all_day ?? false),
                    'priority' => (int)($item->priority ?? 0),
                    'from_time' => $item->from_time,
                    'to_time' => $item->to_time,
                    'timezone' => $item->timezone,
                    'active_from' => $item->active_from ? $item->active_from->format('Y-m-d') : null,
                    'active_to' => $item->active_to ? $item->active_to->format('Y-m-d') : null,
                    'work_days' => !empty($item->work_days) ? array_map('intval', explode(',', $item->work_days)) : [],
                    'include_dates' => $item->include_dates ?? [],
                    'exclude_dates' => $item->exclude_dates ?? [],
                    'date_ranges' => $item->date_ranges ?? [],
                    'outside_policy' => $item->outside_policy ?? 'inverse',
                    'created_at' => $item->created_at?->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at?->diffForHumans(),
                    'updated_at' => $item->updated_at?->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at?->diffForHumans(),
                ]
            ];
        });
        $dayNames = [
            1 => __('Понедельник'),
            2 => __('Вторник'),
            3 => __('Среда'),
            4 => __('Четверг'),
            5 => __('Пятница'),
            6 => __('Суббота'),
            7 => __('Воскресенье'),
        ];

        return response()->json([
            'data' => $items,
            'dayNames' => collect($dayNames)->map(function ($value, $id) {
                return [
                    'id' => $id,
                    'value' => $value
                ];
            })->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'status' => 'required|in:0,1',
            'all_day' => 'nullable|in:0,1',
            'priority' => 'nullable|integer|min:0|max:32767',
            'from_time' => 'nullable|string|max:5', // HH:MM
            'to_time' => 'nullable|string|max:5',   // HH:MM
            'timezone' => 'nullable|string|max:64',
            'active_from' => 'nullable|date',
            'active_to' => 'nullable|date',
            'work_days' => 'nullable|array',
            'work_days.*' => 'integer|min:1|max:7',
            'include_dates' => 'nullable|array',
            'include_dates.*' => 'date',
            'exclude_dates' => 'nullable|array',
            'exclude_dates.*' => 'date',
            'date_ranges' => 'nullable|array',
            'date_ranges.*.from' => 'required_with:date_ranges|date',
            'date_ranges.*.to' => 'required_with:date_ranges|date',
            'outside_policy' => 'nullable|string|in:inverse,no_change,force_online,force_offline',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }
        // Require time window unless all_day is true
        if (!$request->boolean('all_day') && (!$request->filled('from_time') || !$request->filled('to_time'))) {
            return response()->json(['status' => 1, 'message' => 'Укажите время начала и окончания или включите режим «Весь день».']);
        }

        $options = [
            'status' => (int)$request->input('status', 0),
            'all_day' => (int)$request->boolean('all_day', false),
            'priority' => (int)$request->input('priority', 0),
            'name' => $request->input('name'),
            'from_time' => $request->input('from_time'),
            'to_time' => $request->input('to_time'),
            'timezone' => $request->input('timezone'),
            'active_from' => $request->input('active_from'),
            'active_to' => $request->input('active_to'),
            'work_days' => $request->has('work_days') ? implode(',', (array)$request->input('work_days')) : null,
            'include_dates' => $request->input('include_dates', null), // array|null (casted)
            'exclude_dates' => $request->input('exclude_dates', null),
            'date_ranges' => $request->input('date_ranges', null),
            'outside_policy' => $request->input('outside_policy', 'inverse'),
            'id_user' => auth()->id(),
        ];

        JobSchedule::create($options);

        return response()->json([
            'status' => 0,
            'message' => 'Расписание успешно добавлена'
        ]);
    }

    /**
     * Изменить расписание
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        $item = JobSchedule::findOrFail($id);
        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name,
                'status' => (bool)$item->status,
                'all_day' => (bool)($item->all_day ?? false),
                'priority' => (int)($item->priority ?? 0),
                'from_time' => $item->from_time,
                'to_time' => $item->to_time,
                'timezone' => $item->timezone,
                'active_from' => $item->active_from ? $item->active_from->format('Y-m-d') : null,
                'active_to' => $item->active_to ? $item->active_to->format('Y-m-d') : null,
                'work_days' => !empty($item->work_days) ? array_map('intval', explode(',', $item->work_days)) : [],
                'include_dates' => $item->include_dates ?? [],
                'exclude_dates' => $item->exclude_dates ?? [],
                'date_ranges' => $item->date_ranges ?? [],
                'outside_policy' => $item->outside_policy ?? 'inverse',
                'created_at' => $item->created_at?->translatedFormat('d M Y H:i'),
                'created_at_human' => $item->created_at?->diffForHumans(),
                'updated_at' => $item->updated_at?->translatedFormat('d M Y H:i'),
                'updated_at_human' => $item->updated_at?->diffForHumans(),
            ]
        ]);
    }

    public function update(int $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'status' => 'required|in:0,1',
            'all_day' => 'nullable|in:0,1',
            'priority' => 'nullable|integer|min:0|max:32767',
            'from_time' => 'nullable|string|max:5', // HH:MM
            'to_time' => 'nullable|string|max:5',   // HH:MM
            'timezone' => 'nullable|string|max:64',
            'active_from' => 'nullable|date',
            'active_to' => 'nullable|date',
            'work_days' => 'nullable|array',
            'work_days.*' => 'integer|min:1|max:7',
            'include_dates' => 'nullable|array',
            'include_dates.*' => 'date',
            'exclude_dates' => 'nullable|array',
            'exclude_dates.*' => 'date',
            'date_ranges' => 'nullable|array',
            'date_ranges.*.from' => 'required_with:date_ranges|date',
            'date_ranges.*.to' => 'required_with:date_ranges|date',
            'outside_policy' => 'nullable|string|in:inverse,no_change,force_online,force_offline',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        // Require time window unless all_day is true
        if (!$request->boolean('all_day') && (!$request->filled('from_time') || !$request->filled('to_time'))) {
            return response()->json(['status' => 1, 'message' => 'Укажите время начала и окончания или включите режим «Весь день».']);
        }

        $find = JobSchedule::find($id);

        $options = [
            'status' => (int)$request->input('status', 0),
            'all_day' => (int)$request->boolean('all_day', false),
            'priority' => (int)$request->input('priority', 0),
            'name' => $request->input('name'),
            'from_time' => $request->input('from_time'),
            'to_time' => $request->input('to_time'),
            'timezone' => $request->input('timezone'),
            'active_from' => $request->input('active_from'),
            'active_to' => $request->input('active_to'),
            'work_days' => $request->has('work_days') ? implode(',', (array)$request->input('work_days')) : null,
            'include_dates' => $request->input('include_dates', null),
            'exclude_dates' => $request->input('exclude_dates', null),
            'date_ranges' => $request->input('date_ranges', null),
            'outside_policy' => $request->input('outside_policy', 'inverse'),
        ];

        $find->update($options);

        return response()->json([
            'status' => 0,
            'message' => $find->name . ' '. __('успешно обновлен')
        ]);
    }

    public function destroy(int $id, Request $request)
    {
        $job = JobSchedule::findOrFail($id);
        $oldItem = $job;
        $job->delete();

        return response()->json([
            'status' => 0,
            'message' => "Запись {$oldItem->name} успешно удалена"
        ]);
    }
}
