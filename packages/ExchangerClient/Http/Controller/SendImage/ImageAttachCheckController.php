<?php
namespace iEXPackages\ExchangerClient\Http\Controller\SendImage;

use App\Mail\Admin\AdminNewVerificationCard;
use App\Models\NotificationEvent;
use App\Models\Task;
use App\Models\TaskField;
use App\Models\TasksCheckImage;
use App\Models\VerificationCard;
use App\Support\Facades\iEXApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ImageAttachCheckController
{
    /**
     * Проверка загружаемого чека
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimetypes:image/jpeg,image/png,application/pdf|max:10000'
        ]);

        // Если есть ошибки, то дальше не пускаем
        if ($validator->fails()) {
            return Response::json([
                'message' => $validator->messages()->first(),
            ], 422);
        }

        return \response()->json([
            'status' => 0
        ]);
    }

    /**
     * Прикрепить чек к заявке
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimetypes:image/jpeg,image/png,application/pdf|max:10000',
            'order_id' => 'required|numeric|exists:tasks,public_id',
        ]);

        // Если есть ошибки, то дальше не пускаем
        if ($validator->fails()) {
            return Response::json([
                'message' => $validator->messages()->first(),
            ], 422);
        }


        // Получаем по заявке
        $order = Task::wherePublicId((int) $request->order_id)->firstOrFail();

        // Получаем новые имена для файлов
        $newFileName = sprintf('%s-%s.%s',
            Str::random(5), Str::uuid()->toString(),
            Str::lower($request->file('file')->getClientOriginalExtension())
        );


        if($request->file('file')->getMimeType() == 'application/pdf')
        {
            $request->file('file')
                ->storeAs('/order_check/', $newFileName, 'iexexchanger-disk');
        } else {
            Image::read($request->file('file')->getRealPath())
                ->scaleDown(width: config('image.resizes.order_check'))
                ->save(
                    sprintf('%s/order_check/%s',
                        config('filesystems.disks.iexexchanger-disk.root'),
                        $newFileName
                    )
                );
        }

        // Записываем чек в лог
        TasksCheckImage::create([
            'id_order' => (int) $order->id,
            'image' => $newFileName,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'mimetype' => $request->file('file')->getMimeType(),
            'size' => $request->file('file')->getSize(),
        ]);

        $order->update([
            'is_file_check' => 1,
        ]);

        return response()->json([
            'status' => 0,
        ]);
    }
}
