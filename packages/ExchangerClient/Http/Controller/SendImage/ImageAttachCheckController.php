<?php
namespace iEXPackages\ExchangerClient\Http\Controller\SendImage;

use App\Models\Task;
use App\Models\TasksCheckImage;
use iEXPackages\ExchangerClient\Support\AssertOrderVerificationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageAttachCheckController
{
    /**
     * Проверка загружаемого чека
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimetypes:image/jpeg,image/png,application/pdf|max:10000',
        ]);

        if ($validator->fails()) {
            return Response::json([
                'message' => $validator->messages()->first(),
            ], 422);
        }

        return response()->json([
            'status' => 0,
        ]);
    }

    /**
     * Прикрепить чек к заявке (possession-gated — public_id alone is never enough).
     */
    public function store(Request $request): JsonResponse
    {
        // Do NOT use exists:tasks,public_id — that enumerates valid order IDs.
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimetypes:image/jpeg,image/png,application/pdf|max:10000',
            'order_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            Log::warning('attach_check_upload_validation_failed', [
                'errors' => $validator->errors()->keys(),
                'ip' => $request->ip(),
            ]);

            return Response::json([
                'message' => $validator->messages()->first(),
            ], 422);
        }

        $order = Task::wherePublicId((int) $request->order_id)->first();
        if (!$order || !AssertOrderVerificationAccess::allows($request, $order)) {
            return AssertOrderVerificationAccess::denyResponse();
        }

        $uploaded = $request->file('file');
        $mime = strtolower((string) ($uploaded->getMimeType() ?: ''));
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => '',
        };
        if ($ext === '') {
            return Response::json(['message' => 'Invalid file type'], 422);
        }

        $newFileName = sprintf('%s-%s.%s', Str::random(5), Str::uuid()->toString(), $ext);

        if ($mime === 'application/pdf') {
            $uploaded->storeAs('/order_check/', $newFileName, 'iexexchanger-disk');
        } else {
            Image::read($uploaded->getRealPath())
                ->scaleDown(width: config('image.resizes.order_check'))
                ->save(
                    sprintf(
                        '%s/order_check/%s',
                        config('filesystems.disks.iexexchanger-disk.root'),
                        $newFileName
                    )
                );
        }

        TasksCheckImage::create([
            'id_order' => (int) $order->id,
            'image' => $newFileName,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'mimetype' => $mime,
            'size' => $uploaded->getSize(),
        ]);

        $order->update([
            'is_file_check' => 1,
        ]);

        return response()->json([
            'status' => 0,
        ]);
    }
}
