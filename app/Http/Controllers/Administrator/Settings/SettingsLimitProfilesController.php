<?php

namespace App\Http\Controllers\Administrator\Settings;

use App\Http\Controllers\Controller;
use App\Models\SettingsLimitProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SettingsLimitProfilesController extends Controller
{
    /**
     * Список профилей лимитов.
     *
     * Поддерживаем:
     *  - ?order=id или ?order=-id (по умолчанию -id)
     *  - ?only_default=1 — только профиль по умолчанию
     */
    public function index(Request $request)
    {
        $orderParam   = (string) $request->input('order', '-id');
        $onlyDefault  = $request->boolean('only_default', false);
        $perPage      = (int) 20;

        $query = SettingsLimitProfile::query()
            ->when($onlyDefault, fn ($q) => $q->where('is_default', true))
            ->when(
                $orderParam === 'id',
                fn ($q) => $q->orderBy('id'),
                fn ($q) => $q->orderByDesc('id')
            )
            ->withCount(['users', 'directions']);

        $paginator = $query->paginate($perPage)->appends($request->query());

        $items = $paginator->getCollection()->map(static function (SettingsLimitProfile $profile) {
            return [
                'id' => (int) $profile->id,
                'attributes' => [
                    'name'                              => $profile->name,
                    'slug'                              => $profile->slug,
                    'description'                       => $profile->description,
                    'max_num_order_user_hour'           => (int) $profile->max_num_order_user_hour,
                    'max_num_order_user_day'            => (int) $profile->max_num_order_user_day,
                    'order_limit_count'                 => (int) $profile->order_limit_count,
                    'order_limit_minutes'               => (int) $profile->order_limit_minutes,
                    'min_interval_between_orders_seconds' => (int) $profile->min_interval_between_orders_seconds,
                    'first_orders_window_count'         => (int) $profile->first_orders_window_count,
                    'first_orders_max_amount'           => $profile->first_orders_max_amount,
                    'is_default'                        => (bool) $profile->is_default,
                    'users_count'                       => (int) $profile->users_count,
                    'directions_count'                  => (int) $profile->directions_count,
                    'created_at'                        => $profile->created_at?->translatedFormat('d M Y H:i'),
                    'updated_at'                        => $profile->updated_at?->translatedFormat('d M Y H:i'),
                ],
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Создаёт профиль лимитов.
     *
     * Поля:
     *  • name — обязательное название
     *  • slug — обязательный уникальный код (newbie, regular, vip, strict_direction и т.п.)
     *  • description — необязательное описание
     *  • max_num_order_user_hour / day — лимиты в час и сутки (0 = без лимита)
     *  • order_limit_count / minutes — N заявок за M минут (0 = без лимита)
     *  • is_default — сделать ли профиль дефолтным (при true остальные сбрасываются)
     */
    public function store(Request $request)
    {
        // Быстрый апдейт is_default (switch)
        if ($request->get('updateField') === 'is_default') {
            $data = Validator::make($request->all(), [
                'id'         => ['required', 'integer', 'exists:settings_limit_profiles,id'],
                'is_default' => ['required'],
            ])->validate();

            $bool = filter_var($data['is_default'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                return response()->json([
                    'status'  => 1,
                    'message' => 'Некорректное значение is_default',
                ], 422);
            }

            /** @var SettingsLimitProfile $profile */
            $profile = SettingsLimitProfile::findOrFail((int) $data['id']);

            DB::transaction(function () use ($profile, $bool) {
                if ($bool) {
                    SettingsLimitProfile::query()->update(['is_default' => false]);
                }

                $profile->is_default = $bool;
                $profile->save();
            });

            return response()->json([
                'status' => 0,
                'data'   => [
                    'id'         => (int) $profile->id,
                    'is_default' => (bool) $profile->is_default,
                ],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name'                              => ['required', 'string', 'max:191'],
            'slug'                              => ['required', 'string', 'max:191', 'unique:settings_limit_profiles,slug'],
            'description'                       => ['nullable', 'string', 'max:500'],
            'max_num_order_user_hour'           => ['nullable'],
            'max_num_order_user_day'            => ['nullable'],
            'order_limit_count'                 => ['nullable'],
            'order_limit_minutes'               => ['nullable'],
            'min_interval_between_orders_seconds' => ['nullable'],
            'first_orders_window_count'         => ['nullable'],
            'first_orders_max_amount'           => ['nullable', 'string', 'max:50'],
            'is_default'                        => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ], 422);
        }

        $profileData = [
            'name'                              => $request->input('name'),
            'slug'                              => $request->input('slug'),
            'description'                       => $request->input('description') ?: null,
            'max_num_order_user_hour'           => $this->toNonNegativeInt($request->input('max_num_order_user_hour')),
            'max_num_order_user_day'            => $this->toNonNegativeInt($request->input('max_num_order_user_day')),
            'order_limit_count'                 => $this->toNonNegativeInt($request->input('order_limit_count')),
            'order_limit_minutes'               => $this->toNonNegativeInt($request->input('order_limit_minutes')),
            'min_interval_between_orders_seconds' => $this->toNonNegativeInt($request->input('min_interval_between_orders_seconds')),
            'first_orders_window_count'         => $this->toNonNegativeInt($request->input('first_orders_window_count')),
            'first_orders_max_amount'           => $this->normalizeDecimalOrNull($request->input('first_orders_max_amount')),
            'is_default'                        => (bool) $request->boolean('is_default'),
        ];

        /** @var SettingsLimitProfile $profile */
        $profile = null;

        DB::transaction(function () use (&$profile, $profileData) {
            if ($profileData['is_default']) {
                SettingsLimitProfile::query()->update(['is_default' => false]);
            }

            $profile = SettingsLimitProfile::create($profileData);
        });

        return response()->json([
            'status'  => 0,
            'message' => 'Профиль лимитов создан',
            'data'    => [
                'id' => (int) $profile->id,
            ],
        ]);
    }

    /**
     * Данные одного профиля для формы редактирования.
     */
    public function edit(int $id)
    {
        /** @var SettingsLimitProfile $profile */
        $profile = SettingsLimitProfile::withCount(['users', 'directions'])->findOrFail($id);

        return response()->json([
            'id' => (int) $profile->id,
            'attributes' => [
                'name'                              => $profile->name,
                'slug'                              => $profile->slug,
                'description'                       => $profile->description,
                'max_num_order_user_hour'           => (int) $profile->max_num_order_user_hour,
                'max_num_order_user_day'            => (int) $profile->max_num_order_user_day,
                'order_limit_count'                 => (int) $profile->order_limit_count,
                'order_limit_minutes'               => (int) $profile->order_limit_minutes,
                'min_interval_between_orders_seconds' => (int) $profile->min_interval_between_orders_seconds,
                'first_orders_window_count'         => (int) $profile->first_orders_window_count,
                'first_orders_max_amount'           => $profile->first_orders_max_amount,
                'is_default'                        => (bool) $profile->is_default,
                'users_count'                       => (int) $profile->users_count,
                'directions_count'                  => (int) $profile->directions_count,
                'created_at'                        => $profile->created_at?->translatedFormat('d M Y H:i'),
                'updated_at'                        => $profile->updated_at?->translatedFormat('d M Y H:i'),
            ],
        ]);
    }

    /**
     * Обновляет профиль лимитов.
     */
    public function update(Request $request, int $id)
    {
        /** @var SettingsLimitProfile $profile */
        $profile = SettingsLimitProfile::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'                              => ['required', 'string', 'max:191'],
            'slug'                              => ['required', 'string', 'max:191', 'unique:settings_limit_profiles,slug,' . $profile->id],
            'description'                       => ['nullable', 'string', 'max:500'],
            'max_num_order_user_hour'           => ['nullable'],
            'max_num_order_user_day'            => ['nullable'],
            'order_limit_count'                 => ['nullable'],
            'order_limit_minutes'               => ['nullable'],
            'min_interval_between_orders_seconds' => ['nullable'],
            'first_orders_window_count'         => ['nullable'],
            'first_orders_max_amount'           => ['nullable', 'string', 'max:50'],
            'is_default'                        => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ], 422);
        }

        $profileData = [
            'name'                              => $request->input('name'),
            'slug'                              => $request->input('slug'),
            'description'                       => $request->input('description') ?: null,
            'max_num_order_user_hour'           => $this->toNonNegativeInt($request->input('max_num_order_user_hour')),
            'max_num_order_user_day'            => $this->toNonNegativeInt($request->input('max_num_order_user_day')),
            'order_limit_count'                 => $this->toNonNegativeInt($request->input('order_limit_count')),
            'order_limit_minutes'               => $this->toNonNegativeInt($request->input('order_limit_minutes')),
            'min_interval_between_orders_seconds' => $this->toNonNegativeInt($request->input('min_interval_between_orders_seconds')),
            'first_orders_window_count'         => $this->toNonNegativeInt($request->input('first_orders_window_count')),
            'first_orders_max_amount'           => $this->normalizeDecimalOrNull($request->input('first_orders_max_amount')),
            'is_default'                        => (bool) $request->boolean('is_default'),
        ];

        DB::transaction(function () use ($profile, $profileData) {
            if ($profileData['is_default']) {
                SettingsLimitProfile::query()->where('id', '!=', $profile->id)->update(['is_default' => false]);
            }

            $profile->update($profileData);
        });

        return response()->json([
            'status'  => 0,
            'message' => 'Профиль лимитов обновлён',
        ]);
    }

    /**
     * Удаляет профиль лимитов.
     *
     * Если профиль привязан к пользователям/направлениям,
     * из-за FK с nullOnDelete() связи обнулятся.
     */
    public function destroy(int $id)
    {
        /** @var SettingsLimitProfile $profile */
        $profile = SettingsLimitProfile::findOrFail($id);

        if ($profile->is_default) {
            // На всякий случай защищаемся от удаления единственного дефолтного профиля,
            // чтобы не оставить систему без дефолта.
            $hasAnotherDefault = SettingsLimitProfile::query()
                ->where('id', '!=', $profile->id)
                ->where('is_default', true)
                ->exists();

            if (!$hasAnotherDefault) {
                return response()->json([
                    'status'  => 1,
                    'message' => 'Нельзя удалить единственный профиль по умолчанию. Сначала назначьте другой профиль дефолтным.',
                ], 422);
            }
        }

        $profile->delete();

        return response()->json([
            'status'  => 0,
            'message' => 'Профиль лимитов удалён',
        ]);
    }

    /**
     * Нормализует неотрицательное целое число из строки.
     * Пустая строка или некорректное значение → 0.
     */
    private function toNonNegativeInt($value): int
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return 0;
        }

        // Оставляем только цифры
        if (!preg_match('/^\d+$/', $raw)) {
            return 0;
        }

        $int = (int) $raw;

        return $int < 0 ? 0 : $int;
    }

    /**
     * Нормализует десятичную строку для суммы или возвращает null.
     * Допускает формат с запятой/точкой, убирает пробелы.
     */
    private function normalizeDecimalOrNull($value): ?string
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return null;
        }

        // Убираем обычные и неразрывные пробелы
        $raw = str_replace(["\u{00A0}", ' '], '', $raw);
        // Заменяем запятую на точку
        $raw = str_replace(',', '.', $raw);
        // Убираем точку на конце, если она одна
        $raw = preg_replace('/\.$/', '', $raw);

        if ($raw === null || $raw === '') {
            return null;
        }

        // Проверяем формат числа: -?\d+(\.\d+)?
        if (!preg_match('/^-?\d+(\.\d+)?$/', $raw)) {
            return null;
        }

        return $raw;
    }
}
