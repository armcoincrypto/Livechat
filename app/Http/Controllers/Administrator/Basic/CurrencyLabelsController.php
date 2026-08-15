<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\CurrencyLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CurrencyLabelsController extends Controller
{
    /**
     * Список меток
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Вспомогательная загрузка валют для массового назначения
        if ($request->has('is_loading_currencies')) {
            $items = Currency::query()
                ->select('id', 'tech_name')
                ->orderBy('tech_name')
                ->get()
                ->map(fn ($item) => ['id' => $item->id, 'value' => $item->tech_name])
                ->values()
                ->all();

            return response()->json([
                'items' => $items,
            ]);
        }

        // Основной список меток
        $items = CurrencyLabel::orderBy('id', 'desc')
            ->get()
            ->map(function (CurrencyLabel $label) {
                return [
                    'id' => $label->id,
                    'attributes' => [
                        'title' => $label->title,
                        'label' => $label->text_color ? ('#' . ltrim((string)$label->text_color, '#')) : null,
                        'bg_color' => $label->bg_color ? ('#' . ltrim((string)$label->bg_color, '#')) : null,
                        'image' => $label->image,
                        'image_path'  => $label->image ? '/storage/currency-labels/' . $label->image : null,
                        'created_at' => $label->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $label->created_at->diffForHumans(),
                        'updated_at' => $label->updated_at->translatedFormat('d M Y H:i'),
                        'updated_at_human' => $label->updated_at->diffForHumans(),
                    ],
                ];
            });

        return response()->json([
            'data' => $items,
            'total' => $items->count(),
        ]);
    }

    /**
     * Обработка и добавления новой метки
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title.' . config('iexexchanger.default_locale') => 'required|string|max:255',
            'bg_color'    => 'required|string|max:32|regex:/^#?[0-9a-fA-F]{3,8}$/',
            'text_color'  => 'required|string|max:32|regex:/^#?[0-9a-fA-F]{3,8}$/',
            'image'       => 'nullable|image|max:5048',

            // выбор валют сразу при создании
            'in_currency_ids'    => 'nullable|array',
            'in_currency_ids.*'  => 'integer|exists:currencies,id',
            'out_currency_ids'   => 'nullable|array',
            'out_currency_ids.*' => 'integer|exists:currencies,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $options = [
            'title'      => $request->title,
            'bg_color'   => $request->filled('bg_color') ? ltrim((string)$request->string('bg_color'), '#') : null,
            'text_color' => $request->filled('text_color') ? ltrim((string)$request->string('text_color'), '#') : null,
        ];

        // Загрузка и сохранение изображения
        if ($request->hasFile('image')) {
            if (! File::isDirectory(public_path('storage/currency-labels/'))) {
                File::makeDirectory(public_path('storage/currency-labels/'), 0777, true, true);
            }

            $file = $request->file('image');
            $filename = sprintf('%s.%s', Str::random(10), $file->getClientOriginalExtension());
            $file->move(public_path('/storage/currency-labels'), $filename);
            $options['image'] = $filename;
        }

        $item = CurrencyLabel::create($options);

        // Привязки валют сразу при создании метки
        $inIds  = collect($request->input('in_currency_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();

        $outIds = collect($request->input('out_currency_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();

        \DB::transaction(function () use ($item, $inIds, $outIds) {
            if ($inIds->isNotEmpty()) {
                $item->currencies()->syncWithPivotValues(
                    $inIds->all(),
                    ['side' => 'give', 'priority' => 0, 'is_active' => 1],
                    false // не отцепляем другие стороны
                );
            }

            if ($outIds->isNotEmpty()) {
                $item->currencies()->syncWithPivotValues(
                    $outIds->all(),
                    ['side' => 'receive', 'priority' => 0, 'is_active' => 1],
                    false
                );
            }
        });

        return response()->json([
            'status'  => 0,
            'message' => $item->title . ' успешно добавлен',
            'usage'   => [
                'in_currency_ids'  => $item->currencies()->wherePivot('side','give')->pluck('currencies.id')->values()->all(),
                'out_currency_ids' => $item->currencies()->wherePivot('side','receive')->pluck('currencies.id')->values()->all(),
            ],
        ]);
    }

    /**
     * Форма изменения метки
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request, int $id)
    {
        $label = CurrencyLabel::findOrFail($id);

        // Удаление фото
        if ($request->has('is_delete_image')) {
            // Удаляем актуальное фото
            iex_file_delete(public_path('storage/currency-labels/'.$label->image));
            $label->image = null;
            $label->save();

            return response()->json([
                'status' => 0,
                'message' => 'Иконка удалена'
            ]);
        }

        return response()->json([
            'id' => $label->id,
            'attributes' => [
                'title' => $label->getTranslations('title'),
                'label' => $label->text_color,
                'bg_color' => $label->bg_color,
                'text_color' => $label->text_color,
                'image' => $label->image,
                'image_path'  => $label->image ? '/storage/currency-labels/' . $label->image : null,
                'created_at' => $label->created_at->translatedFormat('d M Y H:i'),
                'updated_at' => $label->updated_at->translatedFormat('d M Y H:i'),
            ],
            'usage' => [
                'in_currency_ids'  => $label->currencies()->wherePivot('side','give')->pluck('currencies.id')->values()->all(),
                'out_currency_ids' => $label->currencies()->wherePivot('side','receive')->pluck('currencies.id')->values()->all(),
            ],
        ]);
    }

    /**
     * Обработчик обновления группы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $label = CurrencyLabel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title.' . config('iexexchanger.default_locale') => 'required|string|max:255',
            'bg_color'    => 'required|string|max:32|regex:/^#?[0-9a-fA-F]{3,8}$/',
            'text_color'  => 'required|string|max:32|regex:/^#?[0-9a-fA-F]{3,8}$/',
            'image' => 'nullable|image|max:5048',
            'is_delete_image' => 'nullable|boolean',

            'in_currency_ids'    => 'nullable|array',
            'in_currency_ids.*'  => 'integer|exists:currencies,id',
            'out_currency_ids'   => 'nullable|array',
            'out_currency_ids.*' => 'integer|exists:currencies,id',
        ]);


        if ($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        // Удаление текущего изображения
        if ($request->boolean('is_delete_image') && $label->image) {
            iex_file_delete(public_path('storage/currency-labels/' . $label->image));
            $label->image = null;
            $label->save();
        }

        $update = [
            'title'      => $request->title,
            'bg_color'   => $request->filled('bg_color') ? ltrim((string)$request->string('bg_color'), '#') : null,
            'text_color' => $request->filled('text_color') ? ltrim((string)$request->string('text_color'), '#') : null,
        ];

        if ($request->hasFile('image')) {
            if (! File::isDirectory(public_path('storage/currency-labels/'))) {
                File::makeDirectory(public_path('storage/currency-labels/'), 0777, true, true);
            }

            // Удаляем старый файл, если есть
            if ($label->image) {
                iex_file_delete(public_path('storage/currency-labels/' . $label->image));
            }

            $file = $request->file('image');
            $filename = sprintf('%s.%s', Str::random(10), $file->getClientOriginalExtension());
            $file->move(public_path('/storage/currency-labels'), $filename);
            $update['image'] = $filename;
        }

        // Привязки валют сразу при создании метки
        $inIds  = collect($request->input('in_currency_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();

        $outIds = collect($request->input('out_currency_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();

        \DB::transaction(function () use ($label, $inIds, $outIds) {
            if ($inIds->isNotEmpty()) {
                $label->currencies()->syncWithPivotValues(
                    $inIds->all(),
                    ['side' => 'give', 'priority' => 0, 'is_active' => 1],
                    false // не отцепляем другие стороны
                );
            }

            if ($outIds->isNotEmpty()) {
                $label->currencies()->syncWithPivotValues(
                    $outIds->all(),
                    ['side' => 'receive', 'priority' => 0, 'is_active' => 1],
                    false
                );
            }
        });

        $label->update($update);

        return response()->json([
            'status' => 0,
            'message'=> $label->title . ' успешно обновлен',
        ]);
    }

    /**
     * Удалить метку
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $label = CurrencyLabel::findOrFail($id);

        // Сколько связей было — для информации
        $attachedCount = $label->currencies()->count();

        DB::transaction(function () use ($label) {
            // 1) Удаляем файл-иконку (если есть)
            if ($label->image) {
                iex_file_delete(public_path('storage/currency-labels/' . $label->image));
                $label->image = null; // на всякий случай, чтобы не остался мусор
                $label->save();
            }

            // 2) Отвязываем все валюты (pivot currency_label_currency)
            $label->currencies()->detach();

            // 3) Удаляем саму метку
            $label->delete();
        });

        return response()->json([
            'status'  => 0,
            'message' => $label->title . ' успешно удалена',
            'detached_currencies' => $attachedCount,
        ]);
    }
}
