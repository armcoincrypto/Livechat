<?php
namespace iEXPackages\ExchangerClient\Http\Controller\SendImage;

use App\Mail\Admin\AdminNewVerificationCard;
use App\Models\NotificationEvent;
use App\Models\Task;
use App\Models\TaskField;
use App\Models\VerificationCard;
use App\Support\Facades\iEXApp;
use iEXPackages\ExchangerClient\Support\AssertOrderVerificationAccess;
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

class ImageVerificationCardController
{
    /** Order statuses where card verification upload is still meaningful. */
    private const UPLOAD_ALLOWED_ORDER_STATUSES = [2, 3, 7, 8, 9, 12];

    /**
     * Загрузка фото для верификации в заявке
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function store(Request $request): JsonResponse
    {
        // Do NOT use exists:tasks,public_id — that enumerates valid order IDs.
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpg,png,jpeg|max:10000',
            'order_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            Log::warning('card_verification_upload_validation_failed', [
                'errors' => $validator->errors()->keys(),
                'ip' => $request->ip(),
            ]);

            return Response::json([
                'message' => $validator->messages()->first(),
            ], 422);
        }

        $order_id = (int) $request->order_id;
        $order = Task::wherePublicId($order_id)->first();

        if (!$order || !AssertOrderVerificationAccess::allows($request, $order)) {
            return AssertOrderVerificationAccess::denyResponse();
        }

        if (!$this->orderAllowsVerificationUpload($order)) {
            return AssertOrderVerificationAccess::denyResponse();
        }

        $cardAlreadyExists = VerificationCard::where('hash_id', $order_id)
            ->where('status', 0)
            ->exists();

        if ($cardAlreadyExists) {
            return response()->json([
                'message' => 'Вы уже подавали заявку на верификацию.'
            ], 422);
        }

        $ext = Str::lower((string) $request->file('image')->guessExtension()
            ?: $request->file('image')->getClientOriginalExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return Response::json(['message' => 'Invalid image type'], 422);
        }

        $fullFileName = sprintf('%s-%s.%s',
            Str::random(5), Str::uuid()->toString(),
            $ext
        );

        $previewFileName = sprintf('preview-%s-%s.%s',
            Str::random(5), Str::uuid()->toString(),
            $ext
        );

        if ($request->hasFile('image')) {
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
        }

        $otherFields = TaskField::where([
            ['id_task', $order->id],
            ['type_field', 'in'],
            ['alias', 'currency']
        ])->get()->pluck('field_value', 'field_key');

        $from_shot = preg_replace('/\s/', '', security_xss($order->from_shot));

        $status = 0;
        if ((int) iEXSetting('enabled_auto_verification_card') === 1) {
            // Auto-approve only after authorized create on a verification-required order.
            $status = 1;
        }

        $item = VerificationCard::create(array_merge(
            VerificationCard::identifierAttributes($from_shot, (string) $order->from_shot),
            [
                'id_user' => (int) $order->id_user,
                'id_order' => $order->id,
                'email' => $order->email,
                'id_currency' => $order->direction_exchange->id_currency1,
                'name' => (isset($otherFields) and isset($otherFields['sender_fullname'])) ? $otherFields['sender_fullname'] : '',
                'image' => $fullFileName,
                'image_preview' => $previewFileName,
                'status' => $status,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'language' => app()->getLocale(),
                'hash_id' => $order->public_id
            ]
        ));

        if ($status === 1 && isset($order)) {
            $order->update(['is_from_verification_card' => 0]);
        }

        try {
            iEXApp::telegramNotificationForChannel('verification_card', $item);
        } catch (\Exception $exception) {
            Log::error('TelegramVerificationCardJob: '.$exception->getMessage());
        }

        if ((int) iEXSetting('is_admin_mail_verification_card') == 1) {
            try {
                foreach (get_admin_recipient_email() as $value) {
                    Mail::to($value)
                        ->send(new AdminNewVerificationCard($item));
                }
            } catch (\Exception $exception) {
                Log::error(sprintf('AdminNewVerificationCard-%s', $exception->getMessage()));
            }
        }

        iEXApp::reverbEvent([
            'is_notice' => 1,
            'message' => __('Верификация карты по заявке №') . $item->id_order,
        ]);

        NotificationEvent::create([
            'is_read' => 0,
            'type_event' => 3,
            'title' => __('Верификация карты'),
            'id_value' => $item->id,
            'message' => [
                'Верификация' => __('Верификация карты по заявке №') . $item->id_order
            ]
        ]);

        return Response::json([
            'status' => 0,
            'verify_id' => $item->hash_id,
        ]);
    }

    /**
     * Проверяем верификацию
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request, $order_id = null): JsonResponse
    {
        // Prefer route param; accept body/query for legacy callers.
        $rawId = $order_id ?? $request->route('order_id') ?? $request->input('order_id');
        if ($rawId === null || $rawId === '' || !is_numeric($rawId)) {
            return AssertOrderVerificationAccess::denyResponse();
        }

        $orderPublicId = (int) $rawId;
        $order = Task::wherePublicId($orderPublicId)->first();

        if (!$order || !AssertOrderVerificationAccess::allows($request, $order)) {
            return AssertOrderVerificationAccess::denyResponse();
        }

        $card_find = VerificationCard::whereHashId($orderPublicId)->orderBy('id', 'desc')->first();

        if (! $card_find) {
            return Response::json([
                'status' => 2,
            ]);
        }

        // Scope verification row to the order's user and order id (defense in depth).
        if ((int) $card_find->id_user !== (int) $order->id_user) {
            return AssertOrderVerificationAccess::denyResponse();
        }
        if ($card_find->id_order !== null && (int) $card_find->id_order !== (int) $order->id) {
            return AssertOrderVerificationAccess::denyResponse();
        }

        if ($card_find['status'] == 3) {
            return Response::json([
                'status' => 2,
                'text_message' => $card_find['text_message'],
            ]);
        }

        return Response::json([
            'status' => $card_find->status,
            'text_message' => $card_find->text_message,
        ]);
    }

    private function orderAllowsVerificationUpload(Task $order): bool
    {
        if ((int) ($order->is_from_verification_card ?? 0) !== 1) {
            return false;
        }

        $status = (int) ($order->status ?? 0);
        if (!in_array($status, self::UPLOAD_ALLOWED_ORDER_STATUSES, true)) {
            return false;
        }

        return true;
    }
}
