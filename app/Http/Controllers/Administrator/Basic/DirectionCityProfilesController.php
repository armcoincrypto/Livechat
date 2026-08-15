<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Basic\DirectionCityProfileResource;
use App\Models\DirectionCityProfile;
use App\Models\DirectionExchangeCity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DirectionCityProfilesController extends Controller
{
    /**
     * Список профилей для городов направлений.
     *
     * Доп. режим:
     *  - ?is_loading_cities=1 — отдаёт список direction_exchange_cities для массовой привязки профилей.
     */
    public function index(Request $request)
    {
        $orderParam = (string) $request->input('order', '-id');
        $enabled    = $request->has('enabled') ? (int) $request->boolean('enabled') : null;
        $perPage    = (int) 20;

        $query = DirectionCityProfile::query()
            ->when($enabled !== null, fn ($qq) => $qq->where('status', $enabled))
            ->when(
                $orderParam === 'id',
                fn ($qq) => $qq->orderBy('id'),
                fn ($qq) => $qq->orderByDesc('id')
            )
            ->withCount('pivotCities');

        $paginator = $query->paginate($perPage)->appends($request->query());

        return new DirectionCityProfileResource($paginator);
    }

    /**
     * Создаёт профиль и (опционально) привязывает его к direction_exchange_cities.
     *
     * Правила:
     *  • name — обязательная строка
     *  • code — необязательный, но уникальный (если передан)
     *  • profit / profit_s — строковые числа, нормализуются
     *  • status — bool
     *  • direction_city_ids[] — существующие ID из direction_exchange_cities
     *
     * Бизнес-ограничение:
     *  • Одна запись direction_exchange_cities может быть привязана только к одному профилю.
     */
    public function store(Request $request)
    {
        // Быстрый апдейт статуса профиля (switch)
        if ($request->get('updateField') === 'status') {
            $data = Validator::make($request->all(), [
                'id'     => ['required', 'integer', 'exists:direction_city_profiles,id'],
                'status' => ['required'],
            ])->validate();

            $bool = filter_var($data['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                return response()->json([
                    'status'  => 1,
                    'message' => 'Некорректное значение status',
                ], 422);
            }

            $profile = DirectionCityProfile::findOrFail((int) $data['id']);
            $profile->status = $bool;
            $profile->save();

            return response()->json([
                'status' => 0,
                'data'   => [
                    'id'     => (int) $profile->id,
                    'status' => (bool) $profile->status,
                ],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name'    => ['required', 'string', 'max:191'],
            'code'    => ['nullable', 'string', 'max:191', 'unique:direction_city_profiles,code'],
            'profit'  => ['nullable', 'string'],
            'profit_s'=> ['nullable', 'string'],
            'add_comm'=> ['nullable', 'string', 'max:191'],
            'status'  => ['required', 'boolean'],
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

        // add_comm допускает значения вида: -1, -1%, 1, 1% и т.п. — сохраняем как есть (строкой)
        $addCommRaw = trim((string) $request->input('add_comm', ''));
        $add_comm   = $addCommRaw !== '' ? $addCommRaw : null;

        // Формируем данные профиля
        $profileData = [
            'name'     => $request->input('name'),
            'code'     => $request->input('code') ?: null,
            'profit'   => $profit,
            'profit_s' => $profit_s,
            'add_comm' => $add_comm,
            'status'   => (bool) $request->boolean('status'),
        ];

        // Создаём профиль
        DirectionCityProfile::create($profileData);

        return response()->json([
            'status'  => 0,
            'message' => 'Профиль для городов направлений создан',
        ]);
    }

    /**
     * Возвращает данные профиля для формы редактирования.
     *
     * Отдаём:
     *  - attributes: сырые значения полей
     *  - direction_city_ids: массив ID привязанных direction_exchange_cities
     */
    public function edit(int $id)
    {
        $profile = DirectionCityProfile::findOrFail($id);

        $directionCityIds = DB::table('directions_city_profile_pivot')
            ->where('direction_city_profile_id', $profile->id)
            ->pluck('id_direction_exchange_city')
            ->map(static fn ($v) => (int) $v)
            ->values()
            ->all();

        return response()->json([
            'id' => (int) $profile->id,
            'attributes' => [
                'name'       => $profile->name,
                'code'       => $profile->code,
                'profit'     => $profile->profit !== null ? (string) $profile->profit : '0',
                'profit_s'   => $profile->profit_s !== null ? (string) $profile->profit_s : '0',
                'add_comm'   => $profile->add_comm !== null ? (string) $profile->add_comm : '',
                'status'     => (bool) $profile->status,
                'created_at' => $profile->created_at?->translatedFormat('d M Y H:i'),
                'updated_at' => $profile->updated_at?->translatedFormat('d M Y H:i'),
            ],
            'direction_city_ids' => $directionCityIds,
        ]);
    }

    /**
     * Обновляет профиль и его привязки к direction_exchange_cities.
     */
    public function update(Request $request, int $id)
    {
        $profile = DirectionCityProfile::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'    => ['required', 'string', 'max:191'],
            'code'    => ['nullable', 'string', 'max:191', 'unique:direction_city_profiles,code,' . $profile->id],
            'profit'  => ['nullable', 'string'],
            'profit_s'=> ['nullable', 'string'],
            'add_comm'=> ['nullable', 'string', 'max:191'],
            'status'  => ['required', 'boolean']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $profit   = $this->normalizeDecimalString($request->input('profit'));
        $profit_s = $this->normalizeDecimalString($request->input('profit_s'));

        // add_comm — строка, может содержать %, отрицательные значения и т.п.
        $addCommRaw = trim((string) $request->input('add_comm', ''));
        $add_comm   = $addCommRaw !== '' ? $addCommRaw : null;

        $profileData = [
            'name'     => $request->input('name'),
            'code'     => $request->input('code') ?: null,
            'profit'   => $profit,
            'profit_s' => $profit_s,
            'add_comm' => $add_comm,
            'status'   => (bool) $request->boolean('status'),
        ];

        // Обновляем профиль
        $profile->update($profileData);

        return response()->json([
            'status'  => 0,
            'message' => 'Профиль обновлён',
        ]);
    }

    /**
     * Удаляет профиль.
     *
     * Pivot-строки удалятся каскадно (ON DELETE CASCADE в FK на direction_city_profiles).
     */
    public function destroy(int $id)
    {
        $profile = DirectionCityProfile::findOrFail($id);

        try {
            $profile->delete();
        } catch (\Throwable $e) {
            // Fallback: если где-то не настроен каскад
            try {
                DB::table('directions_city_profile_pivot')
                    ->where('direction_city_profile_id', $profile->id)
                    ->delete();

                $profile->delete();
            } catch (\Throwable $e2) {
                return response()->json([
                    'status'  => 1,
                    'message' => 'Не удалось удалить профиль',
                ], 500);
            }
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
