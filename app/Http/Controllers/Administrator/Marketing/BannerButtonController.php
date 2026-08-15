<?php

namespace App\Http\Controllers\Administrator\Marketing;

use App\Http\Controllers\Controller;
use App\Models\BannerButton;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BannerButtonController extends Controller
{
    public function index()
    {

        $banners = BannerButton::get()->map(function($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'locales' => $item->getTranslations(),
                    'link' => $item->link,
                    'color_text_button' => $item->color_text_button,
                    'color_bg_button' => $item->color_bg_button
                ]
            ];
        });

        return response()->json([
            'data' => $banners,
            'total' => count($banners)
        ]);
    }

    public function store(Request $request)
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

            $options = [
                'name' => $request->name,
                'link' => $request->link
            ];

            $options['color_text_button'] = $request->has('color_text_button') ? $request->get('color_text_button') : null;
            $options['color_bg_button'] = $request->has('color_bg_button') ? $request->get('color_bg_button') : null;

            BannerButton::create($options);

        $buttons = BannerButton::get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'locales' => $item->getTranslations()
            ];
        });
        return response()->json([
            'status' => 0,
            'message' => 'Кнопка успешно добавлена',
            'updated' => $buttons
        ]);
    }

    public function edit(int $id)
    {
        $item = BannerButton::find($id);

        return view('admin.tools.banners.button.edit', [
            'item' => $item,
        ]);
    }

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

        $options = [
            'name' => $request->name,
            'link' => $request->link
        ];

        $options['color_text_button'] = $request->has('color_text_button') ? $request->get('color_text_button') : null;
        $options['color_bg_button'] = $request->has('color_bg_button') ? $request->get('color_bg_button') : null;

        $banner = BannerButton::findOrFail($id);
        $banner->update($options);
        return response()->json([
            'status' => 0,
            'message' => 'Кнопка %s успешно обновлена', $banner->name,
        ]);
    }

    /**
     * Удалить
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $item = BannerButton::findOrFail($id);
        $oldItem = $item;
        $item->delete();


        return response()->json([
            'status' => 0,
            'message' => $oldItem->name .' успешно удален'
        ]);
    }
}
