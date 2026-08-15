<?php

namespace App\Http\Controllers\Administrator\Bonuses;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PartnerBannersController extends Controller
{
    /**
     * Дополнительные фильтры
     *
     * @var array
     */
    protected $allowFiltered = [
        'is_view_logotype_partner',
    ];

    /**
     * Список партнеров
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $partners = Partner::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'link' => $item->link,
                    'logo' => $item->logo,
                    'logo_path' => '/storage/'. $item->logo
                ]
            ];
        });

        return response()->json([
            'data' => $partners,
            'total' => count($partners)
        ]);
    }

    /**
     * Обработка и добавление партнера
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'link.'.config('iexexchanger.default_locale') => 'required|max:255',
            'logo' => 'required|image|mimes:jpg,jpeg,png,gif,svg',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $logo = $request->file('logo');
        $filename = sprintf('%s.%s', Str::random(13), $logo->getClientOriginalExtension());

        $destinationPath = public_path('/storage');
        $logo->move($destinationPath, $filename);

        $options = [
            'logo' => $filename,
            'name' => $request->name,
            'link' => $request->link
        ];

        $findById = Partner::create($options);

        return response()->json([
            'status' => 0,
            'message' => sprintf('Партнер %s успешно добавлен', $findById->name)
        ]);

    }

    /**
     * Форма, редактирования партнера
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = Partner::find($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'name' => $item->name,
                'link' => $item->link,
                'logo' => $item->logo,
                'logo_path' => '/storage/'. $item->logo
            ]
        ]);
    }

    /**
     * Обработка и обновление партнера
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'link.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item = Partner::find($id);

        $filename = $item->logo;
        if ($request->file('logo') != null) {
            ///Удаление предыдущих фото
            File::delete(public_path('storage/'.$item->logo));

            $logo = $request->file('logo');
            $filename = sprintf('%s.%s', Str::random(13), $logo->getClientOriginalExtension());

            $destinationPath = public_path('/storage');
            $logo->move($destinationPath, $filename);
        }

        $options = [
            'logo' => $filename,
            'name' => $request->name,
            'link' => $request->link
        ];

        $item->update($options);

        return response()->json([
            'status' => 0,
            'message' => $item->name .' успешно обновлен'
        ]);
    }

    /**
     * Удаление партнера
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $item = Partner::find($id);
        File::delete(public_path('storage/'.$item->logo));
        $oldItem = $item;

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name.' успешно удален'
        ]);
    }
}
