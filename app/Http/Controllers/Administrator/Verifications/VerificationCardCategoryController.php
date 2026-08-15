<?php

namespace App\Http\Controllers\Administrator\Verifications;

use App\Http\Controllers\Controller;
use App\Models\VerificationCardCategory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VerificationCardCategoryController extends Controller
{
    /**
     * Список требований
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $items = VerificationCardCategory::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'status' => (bool)$item->status,
                    'name_locale' => $item->getTranslations('name')
                ]
            ];
        });

        return response()->json([
            'data' => $items,
            'total' => count($items),
        ]);
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
                VerificationCardCategory::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item = VerificationCardCategory::create([
            'name' => $request->name,
            'status' => $request->has('status') ? $request->get('status') : 0
        ]);

        return response()->json([
            'status' => 0,
            'message' => $item->name . ' успешно добавлен'
        ]);

    }

    /**
     * Форма редактирования
     *
     * @return Application|Factory|View|\Illuminate\Foundation\Application
     */
    public function edit(int $id)
    {
        $item = VerificationCardCategory::findOrFail($id);
        return view('admin.verifications.cards.category.edit', compact('item'));
    }

    /**
     * Обработка и обновление
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
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

        $item = VerificationCardCategory::findOrFail($id);
        $item->update([
            'name' => $request->name,
            'status' => $request->has('status') ? $request->get('status') : 0
        ]);

        return response()->json([
            'status' => 0,
            'message' => $item->name . ' успешно обновлен'
        ]);
    }

    /**
     * Удаление категорий
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $item = VerificationCardCategory::findOrFail($id);
        $oldItem = $item;
        if (isset($item->instructions) and $item->instructions->count() > 0) {
            foreach ($item->instructions as $instruction) {
                $instruction->delete();
            }
        }
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name . ' успешно удален'
        ]);
    }
}
