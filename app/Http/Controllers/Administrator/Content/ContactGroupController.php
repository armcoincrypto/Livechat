<?php

namespace App\Http\Controllers\Administrator\Content;

use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactGroupController extends Controller
{
    /**
     * Список групп
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $categories = ContactGroup::orderBy('sorting')->get()->map(function($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'locales' => $item->getTranslations(),
                    'count_contacts' => $item->contacts->count()
                ]
            ];
        });

        return response()->json([
            'data' => $categories,
            'total' => count($categories)
        ]);
    }

    /**
     * Обработка и добавление новой группы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        ContactGroup::create([
            'name' => $request->name
        ]);

        $categories = ContactGroup::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'locales' => $item->getTranslations()
            ];
        });

        return response()->json([
            'status' => 0,
            'message' => 'Группа успешно добавлена',
            'updated' => $categories
        ]);

    }

    /**
     * Форма редактирования группы
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(int $id)
    {
        $group = ContactGroup::find($id);

        return view('admin.tools.contact.groups.edit', compact('group'));
    }

    /**
     * Обработка и обновление контактов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $group = ContactGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $group->update([
            'name' => $request->name
        ]);

        $categories = ContactGroup::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'locales' => $item->getTranslations()
            ];
        });


        return response()->json([
            'status' => 0,
            'message' => "Группа {$group->name} успешно обновлена",
            'updated' => $categories
        ]);
    }

    /**
     * Удаление контактов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        if (config('iexexchanger.is_reading_mode')) {
            return response()->json([
                'status' => 1,
                'message' => 'В demo версии данная функция недоступна'
            ]);
        }
        $item = ContactGroup::findOrFail($id);
        $oldItem = $item;

        if ($item->contacts->count() > 0) {
            return response()->json([
                'status' => 1,
                'message' => 'К группе привязаны ссылки, удаление невозможно'
            ]);
        }

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name .' успешно удален'
        ]);
    }
}
