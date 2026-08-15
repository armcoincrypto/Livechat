<?php

namespace App\Http\Controllers\Administrator\Account;

use App\Http\Controllers\Controller;
use App\Models\ExtraField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ExtraFieldsController extends Controller
{
    /**
     * Доступные scope для доп. полей.
     * Сейчас используется только user_order,
     * позже можно добавить: order, kyc, partner_order и т.д.
     */
    private const SCOPES = [
        'user_order',
    ];

    /**
     * Текущий (основной) scope, который используем для create/update.
     * Пока один — берём первый. В будущем можно сделать выбор на UI.
     */
    private function currentScope(): string
    {
        return self::SCOPES[0];
    }

    /**
     * Список доп. полей.
     *
     * Автоматически группируем по scope и возвращаем:
     * - data.fields{StudlyScope} (например fieldsUserOrder)
     * - total{StudlyScope}      (например totalUserOrder)
     */
    public function index(Request $request): JsonResponse
    {
        $items = ExtraField::query()
            ->whereIn('scope', self::SCOPES)
            ->orderBy('scope')
            ->orderBy('sorting')
            ->get();

        $grouped = $items->groupBy('scope');

        $data = [];
        $totals = [];

        foreach (self::SCOPES as $scope) {
            $list = ($grouped->get($scope) ?? collect())
                ->map(fn(ExtraField $item) => $this->mapListItem($item))
                ->values();

            $suffix = Str::studly($scope); // user_order -> UserOrder

            $data['fields' . $suffix] = $list;
            $totals['total' . $suffix] = $list->count();
        }

        return response()->json([
            'data' => $data,
            ...$totals,
        ]);
    }

    /**
     * Добавление поля + быстрые настройки (status)
     */
    public function store(Request $request): JsonResponse
    {
        // быстрый апдейт статуса (как у тебя)
        if ($request->has('updateField') && $request->get('updateField') === 'status') {
            ExtraField::query()
                ->whereKey((int)$request->id)
                ->update(['status' => (int)$request->status]);

            return response()->json(['status' => 0]);
        }

        $validator = Validator::make($request->all(), [
            'name.' . config('iexexchanger.default_locale') => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        // Генерация key_id
        $nameSlug = Str::slug($request->input('name.' . config('iexexchanger.default_locale')), '_');
        $nameSlug = preg_replace('/[^a-z0-9_]/', '', Str::lower((string)$nameSlug));
        $nameSlug = trim((string)$nameSlug, '_');

        // если вдруг пусто — fallback
        if ($nameSlug === '') {
            $nameSlug = 'field';
        }

        $keyId = $nameSlug;

        $originalKeyId = $keyId;
        $counter = 1;
        while (ExtraField::query()->where('key_id', $keyId)->exists()) {
            $keyId = $originalKeyId . '_' . $counter;
            $counter++;
        }

        $options = [
            // scope фиксируем текущим (пока только user_order)
            'scope' => $this->currentScope(),
            'name'  => $request->name,

            'key_id' => $keyId,

            // У тебя: status=1 выключено, status=0 включено.
            // В старом CurrencyFields store ты ставил status=1, значит "выключено по умолчанию".
            'status' => 1,

            // дефолты правил
            'sorting' => 0,
            'min_char' => 0,
            'max_char' => 0,
            'obligatory_field' => 1, // nullable по умолчанию (0 => required)
            'remove_spaces' => 0,
            'start_with' => null,
            'end_with' => null,
            'validator_type' => null,
            'attach_to_order' => true,
        ];

        $field = ExtraField::create($options);

        return response()->json([
            'status'  => 0,
            'message' => $field->getTranslation('name', config('iexexchanger.default_locale')) . ' успешно добавлен',
        ]);
    }

    /**
     * Форма редактирования
     */
    public function edit(int $id): JsonResponse
    {
        $item = ExtraField::findOrFail($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->getTranslations('name'),

                'scope' => (string)$item->scope,
                'key_id' => (string)$item->key_id,

                'min_char' => (int)$item->min_char,
                'max_char' => (int)$item->max_char,

                'obligatory_field' => (string)$item->obligatory_field,
                'remove_spaces'    => (string)$item->remove_spaces,

                'start_with' => (string)($item->start_with ?? ''),
                'end_with'   => (string)($item->end_with ?? ''),

                'validator_type' => (string)($item->validator_type ?? ''),

                'status' => (int)$item->status,
                'sorting' => (int)$item->sorting,

                'attach_to_order' => (bool)$item->attach_to_order,
            ],
        ]);
    }

    /**
     * Обработка и обновление данных
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $field = ExtraField::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'key_id' => 'required',
            'name.' . config('iexexchanger.default_locale') => 'required|max:255',
            'min_char' => ['nullable', 'integer', 'min:0'],
            'max_char' => ['nullable', 'integer', 'min:0'],
            'obligatory_field' => ['nullable', 'in:0,1'],
            'remove_spaces' => ['nullable', 'in:0,1'],
            'status' => ['nullable', 'in:0,1'],
            'sorting' => ['nullable', 'integer', 'min:0'],
            'attach_to_order' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $keyId = preg_replace('/[^a-z0-9_]/', '', Str::lower((string)$request->key_id));
        $keyId = trim((string)$keyId, '_');

        if ($keyId === '') {
            return response()->json([
                'status'  => 1,
                'message' => 'Ключ не может быть пустым.',
            ]);
        }

        if (ExtraField::query()->where('key_id', $keyId)->where('id', '!=', $id)->exists()) {
            return response()->json([
                'status'  => 1,
                'message' => 'Ключ ' . $keyId . ' уже существует и не может быть дублировано.',
            ]);
        }

        $options = [
            'name'  => $request->name,

            // scope фиксируем текущим (пока только user_order)
            'scope' => $this->currentScope(),

            'key_id' => $keyId,

            'min_char' => (int)($request->get('min_char', 0)),
            'max_char' => (int)($request->get('max_char', 0)),

            'obligatory_field' => (int)($request->get('obligatory_field', 1)),
            'remove_spaces'    => (int)($request->get('remove_spaces', 0)),

            'start_with' => $request->has('start_with') ? (string)$request->get('start_with') : null,
            'end_with'   => $request->has('end_with')   ? (string)$request->get('end_with')   : null,

            'validator_type' => $request->has('validator_type') ? (string)$request->get('validator_type') : null,

            'sorting' => (int)($request->get('sorting', 0)),
            'status' => (int)($request->get('status', 0)),

            'attach_to_order' => (bool)$request->get('attach_to_order', true),
        ];

        $field->update($options);

        return response()->json([
            'status'  => 0,
            'message' => $field->getTranslation('name', config('iexexchanger.default_locale')) . ' успешно обновлен',
        ]);
    }

    /**
     * Удаляем поле
     */
    public function destroy(int $id): JsonResponse
    {
        $field = ExtraField::findOrFail($id);
        $oldName = $field->getTranslation('name', config('iexexchanger.default_locale')) ?? (string)$field->key_id;

        $field->delete();

        return response()->json([
            'status'  => 0,
            'message' => $oldName . ' успешно удален',
        ]);
    }

    /**
     * Маппер для списка (index).
     */
    private function mapListItem(ExtraField $item): array
    {
        return [
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name,
                'key_id' => $item->key_id,
                'min_char' => (int)$item->min_char,
                'max_char' => (int)$item->max_char,
                'obligatory_field' => (int)$item->obligatory_field,
                // status в UI как boolean (true=включено), а в базе 0=включено, 1=выключено
                'status' => (int)$item->status === 0,
                'sorting' => (int)$item->sorting,
                'scope' => (string)$item->scope,
                'attach_to_order' => (bool)$item->attach_to_order,
            ],
        ];
    }
}
