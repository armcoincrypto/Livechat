<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Models\SocialReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SocialReviewsController extends Controller
{
    protected array $listSocialReviews = [
        'vk' => 'В контакте',
        'facebook' => 'Facebook',
        'telegram' => 'Telegram',
        'whatsapp' => 'WhatsApp',
        'twitter' => 'Twitter',
        'instagram' => 'Instagram',
    ];

    /**
     * Список ссылок на соц.сети
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $links = SocialReview::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'type' => $item->type,
                    'link' => $item->link,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                    'status' => (bool)$item->status
                ]
            ];
        });

        return response()->json([
            'data' => $links,
            'total' => count($links)
        ]);
    }

    /**
     * Обработка и добавление ссылки на соц.сети
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                SocialReview::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }


        $validator = \Validator::make($request->all(), [
            'name' => 'required',
            'link' => 'required',
            'type' => 'required',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

            SocialReview::create([
                'name' => $request->get('name'),
                'link' => ($request->has('link') ? $request->get('link') : null),
                'type' => ($request->has('type') ? $request->get('type') : null),
                'status' => ($request->has('status') ? (int)$request->get('status') : 0),
            ]);


        return response()->json([
            'status' => 0,
            'message' => __('Ссылка успешно добавлена')
        ]);
    }

    /**
     * Форма редактирования ссылки на соц.сети
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = SocialReview::findOrFail($id);

        return response()->json(
            [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'type' => $item->type,
                    'link' => $item->link,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                    'status' => (bool)$item->status
                ]
            ]
        );
    }

    /**
     * Обработка и обновление ссылки на соц.сети
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required',
            'link' => 'required',
            'type' => 'required',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $item = SocialReview::findOrFail($id);
        $item->update([
            'name' => $request->get('name'),
            'link' => ($request->has('link') ? $request->get('link') : null),
            'type' => ($request->has('type') ? $request->get('type') : null),
            'status' => ($request->has('status') ? (int)$request->get('status') : 0),
        ]);


        return response()->json([
            'status' => 0,
            'message' => __('Ссылка успешно обновлена')
        ]);
    }

    /**
     * Удаление ссылки на соц. ети
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $item = SocialReview::findOrFail($id);
        $oldItem = $item;
        $item->delete();


        return response()->json([
            'status' => 0,
            'message' => $oldItem->name . ' успешно удален'
        ]);
    }
}
