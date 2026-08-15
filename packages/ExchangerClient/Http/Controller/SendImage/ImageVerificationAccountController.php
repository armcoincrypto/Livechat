<?php
namespace iEXPackages\ExchangerClient\Http\Controller\SendImage;

use App\Mail\Admin\AdminNewVerificationCard;
use App\Models\NotificationEvent;
use App\Models\Task;
use App\Models\TaskField;
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

class ImageVerificationAccountController
{
    /**
     * Верификация карты через личный кабинет
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        // Валидатор для проверки входных данных
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpg,png,jpeg|max:10000',
            'card_number' => 'required|regex:/^[a-zA-Z 0-9]+$/u',
            'fio_account' => 'required|regex:/^[a-zA-Zа-яА-Я 0-9]+$/u',
            'payment_system' => 'required|numeric',
        ]);

        // Если есть ошибки, то дальше не пускаем
        if ($validator->fails()) {
            return Response::json([
                'message' => $validator->messages()->first(),
            ], 422);
        }

        $card_number = security_xss(remove_all_spaces($request->card_number));

        // Если ранее подавали заявку на верификацию
        $card_exists = VerificationCard::query()
            ->whereIdentifier($card_number)
            ->where('id_user', auth()->id())
            ->where('status', 0)
            ->exists();

        if ($card_exists == 1) {
            return response()->json([
                'message' => 'Вы уже подавали заявку на верификацию'
            ], 422);
        }

        $ext = Str::lower((string) $request->file('image')->guessExtension()
            ?: $request->file('image')->getClientOriginalExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return Response::json(['message' => 'Invalid image type'], 422);
        }

        // Получаем новые имена для файлов (never trust original basename)
        $fullFileName = sprintf('%s-%s.%s',
            Str::random(5), Str::uuid()->toString(),
            $ext
        );

        $previewFileName = sprintf('preview-%s-%s.%s',
            Str::random(5), Str::uuid()->toString(),
            $ext
        );

        Image::read($request->file('image')->getRealPath())
            ->scaleDown(width: config('image.resizes.card_verification_preview'))
            ->save(
                sprintf('%s/card_verification/%s',
                    config('filesystems.disks.iexexchanger-disk.root'),
                    $previewFileName
                )
            );

        Image::read($request->file('image')->getRealPath())
            ->scaleDown(width: config('image.resizes.card_verification'))
            ->save(
                sprintf('%s/card_verification/%s',
                    config('filesystems.disks.iexexchanger-disk.root'),
                    $fullFileName
                )
            );

        $item = VerificationCard::create(array_merge(
            VerificationCard::identifierAttributes($card_number, $card_number),
            [
                'id_user'       =>  auth()->id(),
                'id_currency'   =>  (int)$request->payment_system,
                'name'          =>  security_xss($request->fio_account),
                'image'         =>  $fullFileName,
                'image_preview' =>  $previewFileName,
                'status'        =>  0,
                'ip_address'    =>  $request->ip(),
                'user_agent'    =>  $request->userAgent(),
                'hash_id'       =>  Str::random(30)
            ]
        ));

        // Уведомлять о новых заявках на верификацию счетов (Telegram)
        try {
            iEXApp::telegramNotificationForChannel('verification_card', $item);
        } catch (\Exception $exception) {
            Log::error('TelegramVerificationCardJob: '.$exception->getMessage());
        }

        return Response::json([
            'status' => 0,
            'verify_id' => $item->hash_id,
        ]);
    }
}
