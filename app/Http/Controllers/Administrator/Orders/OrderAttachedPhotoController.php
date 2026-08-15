<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Orders\OrderAttachedFileResources;
use App\Models\TaskFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class OrderAttachedPhotoController extends Controller
{
    /**
     * Получаем список прикрепленных фотографий
     *
     * @param int $id
     * @return OrderAttachedFileResources
     */
    public function index(int $id)
    {
        $order = TaskFile::where('id_task', $id)->get();
        return new OrderAttachedFileResources($order);
    }

    /**
     * Прикрепляем новый файл
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function store(int $id, Request $request)
    {
        $validator = Validator::make($request->all(),  [
            'file_order' => ['required', 'image']
        ]);

        if($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $file_value = $request->file('file_order');
        $filename = $request->get('id').'__'.Str::uuid()->toString().'.'.$file_value->getClientOriginalExtension();

        // Загружаем фотографию
        if ($request->hasFile('file_order')) {
            $img = Image::read($request->file('file_order')->getRealPath());
            $img->save(public_path('storage/orders/'.$filename));
        }

        $item = TaskFile::create([
            'id_task' => $id,
            'id_manager' => \auth()->id(),
            'text' => $request->has('file_message') ? $request->file_message : null,
            'file' => $filename,
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Файл успешно добавлен',
            'item' => [
                'id' => $item->id,
                'file' => $item->file,
                'file_path' => '/storage/orders/' . $item->file,
                'text' => $item->text,
                'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                'user' => [
                    'id' => $item->user->id,
                    'name' => $item->user->name
                ]
            ]
        ]);
    }

    /**
     * Удаляем прикрепленный элемент
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id)
    {
        $item = TaskFile::findOrFail($id);
        iex_file_delete(public_path('storage/orders/'.$item->file));
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Фото успешно удалена'
        ]);
    }
}
