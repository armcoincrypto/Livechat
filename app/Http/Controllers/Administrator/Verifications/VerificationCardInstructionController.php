<?php

namespace App\Http\Controllers\Administrator\Verifications;

use App\Http\Controllers\Controller;
use App\Models\VerificationCardCategory;
use App\Models\VerificationCardInstruction;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class VerificationCardInstructionController extends Controller
{
    /**
     * Список требований
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $items = VerificationCardInstruction::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'status' => (bool)$item->status,
                    'category_name' => $item->category?->name
                ]
            ];
        });

        $categories = VerificationCardCategory::orderBy('id', 'desc')->get();

        return response()->json([
            'data' => $items,
            'total' => count($items),
            'categories' => $categories->map(function($item) {
                return [
                    'id' => $item->id,
                    'value' => $item->name
                ];
            })->values()
        ]);
    }

    /**
     * Добавить требование
     *
     * @return Application|Factory|View|\Illuminate\Foundation\Application
     */
    public function create()
    {
        $categories = VerificationCardCategory::orderBy('id', 'desc')->get();

        return view('admin.verifications.cards.instruction.create', compact('categories'));
    }

    /**
     * Обработка и добавление требований
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                VerificationCardInstruction::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }


        $validator = Validator::make($request->all(), [
            'id_category' => 'required',
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'text.'.config('iexexchanger.default_locale') => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $filename = Str::random(13).'.png';

        Image::read($request->file('image')->getRealPath())
            ->scale(width: 600)
            ->save(public_path('storage/card_inst_'.$filename));

        $options = [
            'id_category' => (int)$request->get('id_category'),
            'image' => 'card_inst_'.$filename,
            'status' => $request->has('status') ? (int)$request->get('status') : 0,
            'name' => $request->name,
            'text' => $request->text,
            'notice_text' => $request->notice_text,
        ];

        $item = VerificationCardInstruction::create($options);

        return response()->json([
            'status' => 0,
            'message' => $item->name . ' успешно добавлен'
        ]);
    }

    /**
     * Форма редактирования
     *
     * @return array
     */
    public function edit(int $id)
    {
        $item = VerificationCardInstruction::findOrFail($id);

        return [
            'id' => $item->id,
            'attributes' => [
                'id_category' => $item->id_category,
                'name' => $item->getTranslations('name'),
                'text' => $item->getTranslations('text'),
                'notice_text' => $item->getTranslations('notice_text'),
                'status' => (bool)$item->status,
                'image' => $item->image,
                'image_path' => '/storage/' .$item->image,
            ]
        ];
    }

    /**
     * Обработка и обновление
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_category' => 'required',
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'text.'.config('iexexchanger.default_locale') => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item = VerificationCardInstruction::findOrFail($id);

        $options = [
            'id_category' => (int)$request->get('id_category'),
            'status' => $request->has('status') ? (int)$request->get('status') : 0,
            'name' => $request->name,
            'text' => $request->text,
            'notice_text' => $request->notice_text,
        ];

        if ($request->hasFile('image')) {
            $filename = Str::random(13).'.png';

            ///Удаление предыдущих фото
            iex_file_delete(public_path('storage/'.$item->image));

            Image::read($request->file('image')->getRealPath())
                ->scale(width: 600)
                ->save(public_path('storage/card_inst_'.$filename));

            $options['image'] = 'card_inst_'.$filename;
        }

        $item->update($options);

        return response()->json([
            'status' => 0,
            'message' => $item->name . ' успешно обновлен'
        ]);
    }

    /**
     * Удаление
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $item = VerificationCardInstruction::findOrFail($id);
        $oldItem = $item;
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name . ' успешно удален'
        ]);
    }
}
