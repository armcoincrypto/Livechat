<?php

namespace App\Http\Controllers\Administrator\Content;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    /**
     * Список меню
     *
     * @return JsonResponse
     */
    public function index()
    {
        $menus = Menu::query()
            ->with(['children' => fn($q) => $q->orderBy('sorting')])
            ->where('parent_id', 0)
            ->orderBy('sorting')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'name' => $item->name,
                        'slug' => $item->slug,
                        'created_at' => $item->created_at->format('c'),
                        'updated_at' => $item->updated_at->format('c'),
                        'status' => (bool) $item->status,
                        'children' => $item->children->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'attributes' => [
                                    'name' => $item->name,
                                    'slug' => $item->slug,
                                    'status' => (int) $item->status,
                                    'created_at' => $item->created_at->format('c'),
                                    'updated_at' => $item->updated_at->format('c')
                                ],
                            ];
                        }),
                    ],
                ];
            });

        return response()->json([
            'data' => $menus,
            'total' => count($menus)
        ]);
    }

    /**
     * Обработка и добавления меню
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                Menu::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'slug' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'name' => $request->name,
        ];
        $options['slug'] = $request->has('slug') ? $request->get('slug') : Str::slug($request->get('name'));
        $options['parent_id'] = $request->has('parent_id') ? (int)$request->get('parent_id') : 0;
        $options['status'] = $request->has('status') ? (int)$request->get('status') : 0;


        $menu = Menu::create($options);

         return response()->json([
             'status' => 0,
             'message' => __('Меню успешно добавлена'),
             'response' => [
                 'id' => $menu->id,
                 'name' => $menu->name
             ]
         ]);

    }

    /**
     * Форма обновления меню
     *
     * @return JsonResponse
     */
    public function edit(int $id)
    {
        $item = Menu::findOrFail($id);
        $menus = Menu::where('parent_id', 0)->orderBy('sorting')->get()->map(function($item) {
            return [
                'label' => $item->name,
                'value' => $item->id
            ];
        });
        return response()->json([
            'item' => [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'parent_id' => $item->parent_id,
                    'locales' => $item->getTranslations(),
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                    'status' => (bool)$item->status,
                    'children' => $item->children->map(function($item) {
                        return [
                            'id' => $item->id,
                            'attributes' => [
                                'name' => $item->name,
                                'slug' => $item->slug,
                                'status' => (int)$item->status,
                                'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                                'created_at_human' => $item->created_at->diffForHumans(),
                                'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                                'updated_at_human' => $item->updated_at->diffForHumans(),
                            ]
                        ];
                    })
                ]
            ],
            'categories' => $menus
        ]);
    }

    /**
     * Обработка и обновление данных
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'slug' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }



            $options = [
                'name' => $request->name,
            ];
            $options['slug'] = $request->has('slug') ? $request->get('slug') : Str::slug($request->get('name'));
            $options['parent_id'] = $request->has('parent_id') ? (int)$request->get('parent_id') : 0;
            $options['status'] = $request->has('status') ? (int)$request->get('status') : 0;


            $menu->update($options);

            return response()->json([
                'status' => 0,
                'message' => 'Меню успешно обновлена'
            ]);

    }

    /**
     * Удаление меню
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        if (config('iexexchanger.is_reading_mode')) {
            return response()->json([
                'status' => 1,
                'message' => 'В demo версии данная функция недоступна'
            ]);
        }

        $menu = Menu::findOrFail($id);
        $deleteMenu = $menu;

        \DB::table('menu')->where('parent_id', '=', $menu->id)->delete();
        $menu->delete();

        return response()->json([
            'status' => 0,
            'message' => $deleteMenu->name . ' успешно удален'
        ]);
    }
}
