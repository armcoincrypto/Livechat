<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\ProfitProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DirectionProfitProfilesController extends Controller
{
    /**
     * Список профилей прибыли для направлений.
     *
     * Параметры:
     *  - order: "id" или "-id" (по умолчанию -id, от новых к старым)
     *  - enabled: 0/1 — фильтр по статусу
     *  - per_page: количество элементов на странице (по умолчанию 20)
     */
    public function index(Request $request)
    {
        $orderParam = (string) $request->input('order', '-id');
        $enabled    = $request->has('enabled') ? (int) $request->boolean('enabled') : null;
        $perPage    = (int) ($request->integer('per_page') ?: 20);

        $query = ProfitProfile::query()
            ->when($enabled !== null, fn ($qq) => $qq->where('is_active', $enabled))
            ->when(
                $orderParam === 'id',
                fn ($qq) => $qq->orderBy('id'),
                fn ($qq) => $qq->orderByDesc('id')
            );

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * Создаёт профиль прибыли.
     *
     * Поля:
     *  • name     — обязательное название профиля
     *  • profit   — строка-число, нормализуется
     *  • profit_s — строка-число, нормализуется
     *  • is_active — bool (включён/выключен)
     *  • scope    — необязательный текстовый тип области (например: direction / global)
     *
     * Доп. режим:
     *  • updateField=is_active — быстрый апдейт статуса профиля по переключателю.
     */
    public function store(Request $request)
    {
        // Быстрый апдейт статуса профиля (switch)
        if ($request->get('updateField') === 'is_active') {
            $data = Validator::make($request->all(), [
                'id'        => ['required', 'integer', 'exists:profit_profiles,id'],
                'is_active' => ['required'],
            ])->validate();

            $bool = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                return response()->json([
                    'status'  => 1,
                    'message' => 'Некорректное значение is_active',
                ], 422);
            }

            $profile          = ProfitProfile::findOrFail((int) $data['id']);
            $profile->is_active = $bool;
            $profile->save();

            return response()->json([
                'status' => 0,
                'data'   => [
                    'id'        => (int) $profile->id,
                    'is_active' => (bool) $profile->is_active,
                ],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:191'],
            'profit'    => ['nullable', 'string'],
            'profit_s'  => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'scope'     => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        // Нормализация чисел
        $profit   = $this->normalizeDecimalString($request->input('profit'));
        $profit_s = $this->normalizeDecimalString($request->input('profit_s'));

        // Формируем данные профиля
        $profileData = [
            'name'      => $request->input('name'),
            'profit'    => $profit,
            'profit_s'  => $profit_s,
            'is_active' => (bool) $request->boolean('is_active'),
        ];

        if ($request->filled('scope')) {
            $profileData['scope'] = $request->input('scope');
        }

        $profile = ProfitProfile::create($profileData);

        return response()->json([
            'status'  => 0,
            'message' => 'Профиль прибыли создан',
            'data'    => [
                'id' => (int) $profile->id,
            ],
        ]);
    }

    /**
     * Возвращает данные профиля для формы редактирования.
     */
    public function edit(int $id)
    {
        $profile = ProfitProfile::findOrFail($id);

        return response()->json([
            'id'         => (int) $profile->id,
            'attributes' => [
                'name'       => $profile->name,
                'profit'     => $profile->profit !== null ? (string) $profile->profit : '0',
                'profit_s'   => $profile->profit_s !== null ? (string) $profile->profit_s : '0',
                'is_active'  => (bool) $profile->is_active,
                'scope'      => $profile->scope ?? null,
                'created_at' => $profile->created_at?->translatedFormat('d M Y H:i'),
                'updated_at' => $profile->updated_at?->translatedFormat('d M Y H:i'),
            ],
        ]);
    }

    /**
     * Обновляет профиль прибыли.
     */
    public function update(Request $request, int $id)
    {
        $profile = ProfitProfile::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:191'],
            'profit'    => ['nullable', 'string'],
            'profit_s'  => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'scope'     => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $profit   = $this->normalizeDecimalString($request->input('profit'));
        $profit_s = $this->normalizeDecimalString($request->input('profit_s'));

        $profileData = [
            'name'      => $request->input('name'),
            'profit'    => $profit,
            'profit_s'  => $profit_s,
            'is_active' => (bool) $request->boolean('is_active'),
        ];

        if ($request->filled('scope')) {
            $profileData['scope'] = $request->input('scope');
        } else {
            // Если scope не передали, не трогаем существующее значение
            unset($profileData['scope']);
        }

        // Обновляем профиль
        $profile->update($profileData);

        return response()->json([
            'status'  => 0,
            'message' => 'Профиль обновлён',
        ]);
    }

    /**
     * Удаляет профиль прибыли.
     */
    public function destroy(int $id)
    {
        $profile = ProfitProfile::findOrFail($id);

        try {
            $profile->delete();
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 1,
                'message' => 'Не удалось удалить профиль',
            ], 500);
        }

        return response()->json([
            'status'  => 0,
            'message' => 'Профиль удалён',
        ]);
    }

    /**
     * Нормализует десятичное число, переданное строкой.
     * Логика аналогична normalizeDecimalString из ExtraOutProfilesController.
     */
    private function normalizeDecimalString($value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null; // тут можно вернуть '0', если хочешь хранить нули вместо NULL
        }

        $raw = str_replace([',', ' '], ['.', ''], $raw);

        if (str_starts_with($raw, '.')) {
            $raw = '0' . $raw;
        }

        if (!preg_match('/^\d+(?:\.\d+)?$/', $raw)) {
            return null;
        }

        $raw = rtrim(rtrim($raw, '0'), '.');

        return $raw === '' ? null : $raw;
    }
}
