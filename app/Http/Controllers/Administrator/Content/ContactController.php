<?php

namespace App\Http\Controllers\Administrator\Content;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{

    /**
     * Нормализация bool/строковых значений к 0|1.
     */
    private function toIntFlag(mixed $v, int $default = 0): int
    {
        if ($v === null) return $default;
        if ($v === true) return 1;
        if ($v === false) return 0;

        $s = trim((string) $v);
        if ($s === '') return $default;

        // '1', '0', 'true', 'false'
        if ($s === '1' || strcasecmp($s, 'true') === 0) return 1;
        if ($s === '0' || strcasecmp($s, 'false') === 0) return 0;

        return (int) ((int) $s === 1);
    }

    /**
     * Публичный путь к иконке контакта.
     */
    private function iconPath(?string $icon): ?string
    {
        if (!$icon) return null;
        return '/storage/contact/' . ltrim($icon, '/');
    }

    /**
     * Список контактов (группировано по группам).
     */
    public function index(Request $request): JsonResponse
    {
        // Настройки страницы
        if ($request->get('showPage') === 'settings') {
            return response()->json([
                'description_contact' => iEXContentLanguage('description_contact', raw: true),
            ]);
        }

        // Группы (стабильный порядок)
        $categoryModels = ContactGroup::query()
            ->orderBy('sorting')
            ->orderBy('id')
            ->get();

        // Плоский список категорий для форм
        $categories = $categoryModels->map(static function (ContactGroup $item) {
            return [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'status' => (int) ($item->status ?? 0),
                'sorting' => (int) ($item->sorting ?? 0),
                'locales' => $item->getTranslations(),
            ];
        })->values();

        // Контакты (стабильный порядок)
        $contactsAll = Contact::query()
            ->with('contact_group')
            ->orderBy('id_group')
            ->orderBy('sorting')
            ->orderBy('id')
            ->get();

        $total = $contactsAll->count();

        $contactsByGroupId = $contactsAll->groupBy(static fn (Contact $c) => (int) ($c->id_group ?? 0));

        $groups = $categoryModels->map(function (ContactGroup $cat) use ($contactsByGroupId) {
            $items = ($contactsByGroupId->get((int) $cat->id) ?? collect())
                ->map(function (Contact $item) use ($cat) {
                    return [
                        'id' => (int) $item->id,
                        'attributes' => [
                            'name' => $item->name,
                            'value' => $item->value,
                            'url' => $item->url,
                            'status' => (int) ($item->status ?? 1),
                            'is_home' => (int) ($item->is_home ?? 0),
                            'created_at' => $item->created_at?->toIso8601String(),
                            'updated_at' => $item->updated_at?->toIso8601String(),
                            'category' => [
                                'id' => (int) $cat->id,
                                'name' => (string) $cat->name,
                            ],
                        ],
                    ];
                })
                ->values();

            return [
                'id' => (int) $cat->id,
                'name' => (string) $cat->name,
                'sorting' => (int) ($cat->sorting ?? 0),
                'status' => (int) ($cat->status ?? 0),
                'items' => $items,
            ];
        })->values();

        return response()->json([
            'groups' => $groups,
            'total' => $total,
            'categories' => $categories,
        ]);
    }

    /**
     * Создание контакта.
     */
    public function store(Request $request): JsonResponse
    {
        // Режим сохранения настроек страницы
        if ((int) $request->input('is_update') === 1 && $request->has('showPage')) {
            if ($request->get('showPage') === 'settings') {
                $optionsLocale = [
                    'description_contact' => $request->input('description_contact'),
                ];

                $localeData = collect($optionsLocale)->only(['description_contact'])->all();
                iEXContentLanguage($localeData);

                return response()->json([
                    'status' => 0,
                    'message' => __('Настройки успешно сохранены'),
                ]);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены'),
            ]);
        }

        /**
         * @deprecated legacy API
         * updateField=is_home используется фронтом для переключения пункта "в шапке",
         * но фактически меняет колонку is_home.
         */
        if ($request->has('updateField')) {
            if ($request->get('updateField') === 'is_home') {
                $validator = Validator::make($request->all(), [
                    'id' => ['required', 'integer', 'exists:contacts,id'],
                    'is_home' => ['required', 'integer', Rule::in([0, 1])],
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'status' => 1,
                        'message' => $validator->messages()->first(),
                    ]);
                }

                Contact::query()->whereKey((int) $request->integer('id'))->update([
                    'is_home' => (int) $request->integer('is_home'),
                ]);

                return response()->json([
                    'status' => 0,
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name.' . config('iexexchanger.default_locale') => ['required', 'max:255'],
            'value.' . config('iexexchanger.default_locale') => ['required', 'max:255'],
            'id_group' => ['required', 'integer', 'exists:contacts_groups,id'],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
            'is_home' => ['nullable', 'integer', Rule::in([0, 1])],
            'url' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $options = [
            'name' => $request->input('name', []),
            'value' => $request->input('value', []),
            'url' => $request->input('url', []),
            'is_home' => $this->toIntFlag($request->input('is_home'), 0),
            'status' => $this->toIntFlag($request->input('status'), 1),
            'id_group' => (int) $request->integer('id_group'),
        ];

        if ($request->hasFile('icon')) {
            if (!\File::isDirectory(public_path('storage/contact/'))) {
                \File::makeDirectory(public_path('storage/contact/'), 0777, true, true);
            }

            $logoIcon = $request->file('icon');
            $filename = sprintf('%s.%s', Str::random(10), $logoIcon->getClientOriginalExtension());
            $destinationPath = public_path('/storage/contact');
            $logoIcon->move($destinationPath, $filename);
            $options['icon'] = $filename;
        }

        Contact::query()->create($options);

        return response()->json([
            'status' => 0,
            'message' => 'Контакт успешно добавлен',
        ]);
    }

    /**
     * Данные для формы редактирования контакта.
     */
    public function edit(int $id): JsonResponse
    {
        /** @var Contact $item */
        $item = Contact::query()->with('contact_group')->findOrFail($id);

        // Группы для селектора
        $groups = ContactGroup::query()
            ->orderBy('sorting')
            ->orderBy('id')
            ->get()
            ->map(static function (ContactGroup $g) {
                return [
                    'id' => (int) $g->id,
                    'name' => (string) $g->name,
                    'locales' => $g->getTranslations(),
                ];
            })
            ->values();

        return response()->json([
            'id' => (int) $item->id,
            'attributes' => [
                // важно: совместимость с текущим фронтом (locales[name|value|url])
                'locales' => $item->getTranslations(),
                'name' => $item->name,
                'value' => $item->value,
                'url' => $item->url,
                'is_home' => (bool) ($item->is_home ?? 0),
                'status' => (int) ($item->status ?? 1),
                'icon' => $item->icon,
                'icon_path' => $this->iconPath($item->icon),
                'group' => [
                    'id' => (int) ($item->contact_group?->id ?? $item->id_group ?? 0),
                    'name' => (string) ($item->contact_group?->name ?? ''),
                ],
            ],
            'groups' => $groups,
        ]);
    }

    /**
     * Обновление контакта.
     */
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var Contact $contact */
        $contact = Contact::query()->findOrFail($id);
        $oldFilename = $contact->icon;

        $validator = Validator::make($request->all(), [
            'name.' . config('iexexchanger.default_locale') => ['required', 'max:255'],
            'value.' . config('iexexchanger.default_locale') => ['required', 'max:255'],
            'id_group' => ['nullable', 'integer', 'exists:contacts_groups,id'],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
            'is_home' => ['nullable', 'integer', Rule::in([0, 1])],
            'url' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $options = [
            'name' => $request->input('name', []),
            'value' => $request->input('value', []),
            'url' => $request->input('url', []),
            'is_home' => $this->toIntFlag($request->input('is_home'), (int) ($contact->is_home ?? 0)),
            'status' => $this->toIntFlag($request->input('status'), (int) ($contact->status ?? 1)),
            'id_group' => (int) ($request->has('id_group') ? $request->integer('id_group') : ($contact->id_group ?? 0)),
        ];

        if ($request->hasFile('icon')) {
            if (!\File::isDirectory(public_path('storage/contact/'))) {
                \File::makeDirectory(public_path('storage/contact/'), 0777, true, true);
            }

            // Удаляем старый файл
            if ($oldFilename) {
                iex_file_delete(public_path('storage/contact/' . $oldFilename));
            }

            $logoIcon = $request->file('icon');
            $filename = sprintf('%s.%s', Str::random(10), $logoIcon->getClientOriginalExtension());
            $destinationPath = public_path('/storage/contact');
            $logoIcon->move($destinationPath, $filename);
            $options['icon'] = $filename;
        }

        $contact->update($options);

        return response()->json([
            'status' => 0,
            'message' => "Контакт {$contact->name} успешно обновлен",
        ]);
    }

    /**
     * Удаление контакта.
     */
    public function destroy(int $id): JsonResponse
    {
        /** @var Contact $item */
        $item = Contact::query()->findOrFail($id);
        $oldName = (string) ($item->name ?? '');

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => ($oldName !== '' ? ($oldName . ' успешно удален') : 'Успешно удалено'),
        ]);
    }

    /**
     * Обновить статус (вкл/выкл) для контакта
     * POST: id, status (0|1)
     */
    public function updateItemStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:contacts,id'],
            'status' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $id = (int) $request->integer('id');
        $status = (int) $request->integer('status');

        Contact::query()->whereKey($id)->update(['status' => $status]);

        return response()->json([
            'status' => 0,
            'message' => 'Статус обновлен',
        ]);
    }

    /**
     * Обновить статус (вкл/выкл) для группы контактов
     * POST: id, status (0|1)
     */
    public function updateGroupStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:contacts_groups,id'],
            'status' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $id = (int) $request->integer('id');
        $status = (int) $request->integer('status');

        ContactGroup::query()->whereKey($id)->update(['status' => $status]);

        return response()->json([
            'status' => 0,
            'message' => 'Статус обновлен',
        ]);
    }
}
