<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Models\Currency;
use App\Models\ExtraOutProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExtraOutProfilesController
{
    /**
     * Список профилей доп. реквизитов.
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

        // Основной список профилей
        $orderParam = (string) $request->input('order', '-id');
        $enabled    = $request->has('enabled') ? (int) $request->boolean('enabled') : null;

        $q = ExtraOutProfile::query()
            ->when($enabled !== null, fn ($qq) => $qq->where('is_enabled', $enabled))
            ->when($orderParam === 'id', fn ($qq) => $qq->orderBy('id'), fn ($qq) => $qq->orderByDesc('id'));

        $items = $q->get()->map(function (ExtraOutProfile $p) {
            return [
                'id' => (int) $p->id,
                'attributes' => [
                    'field_label'        => $p->field_label,
                    'is_enabled'         => (bool) $p->is_enabled,
                    'min_payout_amount'  => (string) $p->min_payout_amount,
                    'min_trigger_amount' => (string) $p->min_trigger_amount,
                    'max_fields'         => (int) $p->max_fields,
                    'description'        => $p->description,
                    'button_name'        => $p->button_name,
                    'created_at'         => $p->created_at?->translatedFormat('d M Y H:i'),
                    'updated_at'         => $p->updated_at?->translatedFormat('d M Y H:i'),
                ],
            ];
        });

        return response()->json([
            'data'  => $items,
            'total' => $items->count(),
        ]);
    }

    /**
     * Создаёт профиль доп. реквизитов (extra_out) и привязывает к нему выбранные валюты.
     *
     * Правила валидации:
     *  • field_label.[default_locale] и button_name.[default_locale] — обязательные строки (Spatie Translatable)
     *  • is_enabled — обязательный булев флаг
     *  • min_payout_amount / min_trigger_amount — строковые числа; нормализуются (запятая→точка, пробелы удаляются)
     *  • max_fields — целое 1..200
     *  • currency_ids[] — существующие ID из таблицы currencies
     *
     * Бизнес-ограничение:
     *  • Одна валюта может быть привязана только к одному профилю.
     *
     * Ответ: JSON { status: 0|1, message: string }
     */
    public function store(Request $request)
    {
        if ($request->get('updateField') === 'status') {
            $data = Validator::make($request->all(), [
                'id'     => ['required', 'integer', 'exists:extra_out_profiles,id'],
                'status' => ['required'],
            ])->validate();

            $bool = filter_var($data['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                return response()->json([
                    'status'  => 1,
                    'message' => 'Некорректное значение status',
                ], 422);
            }

            $profile = ExtraOutProfile::findOrFail((int) $data['id']);
            // если поле is_enabled не в $fillable — используйте forceFill()
            $profile->is_enabled = $bool;
            $profile->save();

            return response()->json([
                'status'     => 0,
                'is_enabled' => (bool) $profile->is_enabled,
            ]);
        }

        $defaultLocale = (string) config('iexexchanger.default_locale');

        $validator = Validator::make($request->all(), [
            'field_label.' . $defaultLocale => ['required', 'string'],
            'button_name.' . $defaultLocale => ['required', 'string'],
            'is_enabled'         => ['required', 'boolean'],
            'min_payout_amount'  => ['nullable', 'string'],
            'min_trigger_amount' => ['nullable', 'string'],
            'max_fields'         => ['required', 'integer', 'min:1', 'max:200'],

            'currency_ids'   => ['nullable', 'array'],
            'currency_ids.*' => ['integer', 'exists:currencies,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        // Нормализуем числовые строки
        $minPayout  = $this->normalizeDecimalString($request->input('min_payout_amount'));
        $minTrigger = $this->normalizeDecimalString($request->input('min_trigger_amount'));

        // Данные профиля для записи
        $profileData = [
            'field_label'        => $request->field_label,
            'button_name'        => $request->button_name,
            'description'        => $request->description,
            'is_enabled'         => (bool) $request->boolean('is_enabled'),
            'min_payout_amount'  => $minPayout,
            'min_trigger_amount' => $minTrigger,
            'max_fields'         => (int) $request->input('max_fields', 20),
        ];

        // Валюты на привязку (очистка, уникализация)
        $currencyIds = collect($request->input('currency_ids', []))
            ->map(static fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();

        // Проверка: каждая валюта может принадлежать только одному профилю (выводим названия)
        if ($currencyIds->isNotEmpty()) {
            $busyNames = Currency::query()
                ->select('currencies.id', 'currencies.tech_name')
                ->join('extra_out_profile_currencies as eopc', 'eopc.currency_id', '=', 'currencies.id')
                ->whereIn('currencies.id', $currencyIds->all())
                ->get()
                ->map(static fn ($c) => $c->tech_name ?: (string) $c->id)
                ->unique()
                ->values();

            if ($busyNames->isNotEmpty()) {
                return response()->json([
                    'status'  => 1,
                    'message' => 'Некоторые валюты уже привязаны к другому профилю: ' . $busyNames->implode(', '),
                ]);
            }
        }

        // Создаём профиль и привязываем валюты (без транзакции — по требованию)
        $profile = ExtraOutProfile::create($profileData);
        if ($currencyIds->isNotEmpty()) {
            $profile->currencies()->sync($currencyIds->all());
        }

        return response()->json([
            'status'  => 0,
            'message' => 'Профиль создан',
        ]);
    }

    /**
     * Возвращает данные профиля для формы редактирования.
     *
     * Отдаём:
     *  - attributes: сырые значения полей и локализованные тексты для трёх переводимых атрибутов
     *  - currency_ids: массив ID привязанных валют (без загрузки лишних полей)
     */
    public function edit(int $id)
    {
        $profile = ExtraOutProfile::findOrFail($id);

        // Только ID валют, без лишней нагрузки на память
        $currencyIds = $profile->currencies()->pluck('currencies.id')->values()->all();

        return response()->json([
            'id' => (int) $profile->id,
            'attributes' => [
                'locales' => [
                    'field_label' => $profile->getTranslations('field_label'),
                    'button_name' => $profile->getTranslations('button_name'),
                    'description' => $profile->getTranslations('description'),
                ],
                'field_label'        => $profile->field_label,
                'button_name'        => $profile->button_name,
                'description'        => $profile->description,
                'is_enabled'         => (bool) $profile->is_enabled,
                'min_payout_amount'  => (string) ($profile->min_payout_amount ?? '0'),
                'min_trigger_amount' => (string) ($profile->min_trigger_amount ?? '0'),
                'max_fields'         => (int) ($profile->max_fields ?? 20),
                'created_at'         => $profile->created_at?->translatedFormat('d M Y H:i'),
                'updated_at'         => $profile->updated_at?->translatedFormat('d M Y H:i'),
            ],
            'currency_ids' => $currencyIds,
        ]);
    }

    /**
     * Обновляет профиль доп. реквизитов и его привязки валют.
     *
     * Валидация/правила идентичны store():
     *  • field_label.[default_locale], button_name.[default_locale] — обязательные строки
     *  • is_enabled — boolean
     *  • min_* — строковые числа; нормализуются (запятая→точка, обрезка пробелов, защита от мусора)
     *  • max_fields — 1..200
     *  • currency_ids[] — существующие ID из currencies
     *  • каждая валюта может быть привязана только к одному профилю (исключая текущий $id)
     */
    public function update(Request $request, int $id)
    {
        $defaultLocale = (string) config('iexexchanger.default_locale');

        $validator = Validator::make($request->all(), [
            'field_label.' . $defaultLocale => ['required', 'string'],
            'button_name.' . $defaultLocale => ['required', 'string'],
            'is_enabled'         => ['required', 'boolean'],
            'min_payout_amount'  => ['nullable', 'string'],
            'min_trigger_amount' => ['nullable', 'string'],
            'max_fields'         => ['required', 'integer', 'min:1', 'max:200'],

            'currency_ids'   => ['nullable', 'array'],
            'currency_ids.*' => ['integer', 'exists:currencies,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        // Нормализуем числовые строки
        $minPayoutAmount  = $this->normalizeDecimalString($request->input('min_payout_amount'));
        $minTriggerAmount = $this->normalizeDecimalString($request->input('min_trigger_amount'));

        // Данные для апдейта
        $profileData = [
            'field_label'        => $request->field_label,
            'button_name'        => $request->button_name,
            'description'        => $request->description,
            'is_enabled'         => (bool) $request->boolean('is_enabled'),
            'min_payout_amount'  => $minPayoutAmount,
            'min_trigger_amount' => $minTriggerAmount,
            'max_fields'         => (int) $request->input('max_fields', 20),
        ];

        // Набор валют к привязке
        $currencyIds = collect($request->input('currency_ids', []))
            ->map(static fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();

        // Проверка уникальности привязки валют (исключая текущий профиль)
        if ($currencyIds->isNotEmpty()) {
            $busyNames = Currency::select('currencies.id', 'currencies.tech_name')
                ->join('extra_out_profile_currencies as eopc', 'eopc.currency_id', '=', 'currencies.id')
                ->where('eopc.profile_id', '!=', $id)
                ->whereIn('currencies.id', $currencyIds->all())
                ->get()
                ->map(static fn ($c) => $c->tech_name ?: (string) $c->id)
                ->unique()
                ->values();

            if ($busyNames->isNotEmpty()) {
                return response()->json([
                    'status'  => 1,
                    'message' => 'Некоторые валюты уже привязаны к другому профилю: ' . $busyNames->implode(', '),
                ]);
            }
        }

        $profile = ExtraOutProfile::findOrFail($id);
        $profile->update($profileData);

        // Привязки валют: если массив не пуст — синхронизируем; если пустой — отвяжем все
        $profile->currencies()->sync($currencyIds->all());

        return response()->json([
            'status'  => 0,
            'message' => 'Профиль обновлён',
        ]);
    }

    /**
     * Удаляет профиль доп. реквизитов (extra_out).
     *
     * Примечание: строки связей в pivot-таблице `extra_out_profile_currencies`
     * удаляются каскадно (FK `profile_id` → ON DELETE CASCADE), поэтому отдельный
     * detach() не требуется. Транзакция также не нужна: одна операция delete().
     * На случай отсутствия каскада добавлен fallback с detach().
     */
    public function destroy(int $id)
    {
        $profile = ExtraOutProfile::findOrFail($id);

        try {
            $profile->delete();
        } catch (\Throwable $e) {
            // Fallback: если каскад по какой-то причине не сработал
            try {
                $profile->currencies()->detach();
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
     * Преобразования:
     *  • обрезает пробелы
     *  • запятые заменяет на точки
     *  • допускает формы вида "123", "123.45", ".45" (превращается в "0.45")
     *  • некорректный ввод → '0'
     *  • убирает хвостовые нули и точку
     */
    private function normalizeDecimalString($value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '0';
        }
        $raw = str_replace([',', ' '], ['.', ''], $raw);
        if (str_starts_with($raw, '.')) {
            $raw = '0' . $raw;
        }
        if (!preg_match('/^\d+(?:\.\d+)?$/', $raw)) {
            return '0';
        }
        $raw = rtrim(rtrim($raw, '0'), '.');
        return $raw === '' ? '0' : $raw;
    }
}
