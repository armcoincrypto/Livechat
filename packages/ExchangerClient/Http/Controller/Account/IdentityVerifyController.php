<?php
namespace iEXPackages\ExchangerClient\Http\Controller\Account;

use App\Http\Controllers\Controller;
use App\Mail\Admin\AdminNewVerificationAccount;
use App\Models\SumsubId;
use App\Models\UserVerification;
use iEXPackages\ExchangerClient\Support\AssertIdentityApplicantAccess;
use iEXPackages\KYCPlugin\Drivers\SumSub\SumSubDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class IdentityVerifyController extends Controller
{
    private const DENY_MESSAGE = 'Forbidden';

    /**
     * Загружаем все верификация
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Throwable
     */
    public function index(Request $request): JsonResponse
    {
        // Данные пользователя
        $user = $request->user();
        $typeService = (int)iEXSetting('type_kyc_service', 0);


        $options = [];
        if($typeService == 0)
        {
            $is_check = UserVerification::where('user_id', (int) $user->id)->where('status', 0)->exists();
            $histories = UserVerification::where('user_id', (int) $user->id)->get()->map(function ($item) {
                return [
                    'created_at' => Carbon::parse($item->created_at)->diffForHumans(),
                    'status' => $item->status,
                ];
            });

            $options = [
                'histories' => $histories,
                'is_check' => $is_check,
            ];
        } else {
            // Если верификация уже завершена (по пользователю или по SumsubId) — не отдаём sumsub вообще
            $isAlreadyVerified = ((int) $user->is_verify_account === 1)
                || (bool) SumsubId::query()->where('user_id', (int) $user->id)->value('is_completed');

            if (!$isAlreadyVerified) {
                // Token + applicant always bound to authenticated user (driver enforces).
                $options['sum_sub'] = (new SumSubDriver())->getAccessToken((string) $user->id, [
                    'email' => $user->email,
                    'type' => 'individual',
                    'lang' => $user->language,
                ]);

                $options['sum_sub']['is_callback'] = true;
                $ownedApplicant = AssertIdentityApplicantAccess::ownedApplicantId((int) $user->id)
                    ?: (string) ($options['sum_sub']['applicant_id'] ?? '');
                $getStatus = $ownedApplicant !== ''
                    ? (new SumSubDriver())->getStatus($ownedApplicant)
                    : [];
                // Если статус существует и reviewStatus = completed — не требуется колбэк
                if (!empty($getStatus['reviewStatus']) && $getStatus['reviewStatus'] === 'completed') {
                    $options['sum_sub']['is_callback'] = false;
                }
            }
        }


        $is_verified = (int) $user->is_verify_account == 1;

        return Response::json([
            'type_service' => (int)iEXSetting('type_kyc_service', 0),
            'is_verified' => $is_verified,
            ...$options
        ]);
    }

    /**
     * Добавить и отправить на проверку
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_one' => 'required|file|mimes:jpg,jpeg,png|max:2048',
            'file_two' => 'required|file|mimes:jpg,jpeg,png|max:2048',
            'fio_user' => 'required|regex:/^[a-z A-Zа-яА-Я]+$/u',
        ]);


        // Если есть ошибки, то дальше не пускаем
        if ($validator->fails()) {
            return response()->json(['message' => $validator->messages()->first()], 422);
        }

        $fileOneCheck = $this->assertSafeIdentityImage($request->file('file_one'));
        if ($fileOneCheck !== null) {
            return response()->json(['message' => $fileOneCheck], 422);
        }
        $fileTwoCheck = $this->assertSafeIdentityImage($request->file('file_two'));
        if ($fileTwoCheck !== null) {
            return response()->json(['message' => $fileTwoCheck], 422);
        }

        // Если вы ранее подавали заявку на верификацию личности
        if (UserVerification::where('user_id', auth()->id())->where('status', 0)->exists()) {
            return response()->json([
                'error' => [
                    'message' => 'Вы уже подавали заявку на верификацию'
                ],
            ], 422);
        }

        $fileOneNames = $this->storeVerificationFiles($request->file('file_one'));
        $fileTwoNames = $this->storeVerificationFiles($request->file('file_two'));


        // Получаем из списка ФИО
        $verification = UserVerification::create([
            'user_id'          => auth()->id(),
            'file_one'         => $fileOneNames['original'],
            'file_two'         => $fileTwoNames['original'],
            'file_one_preview' => $fileOneNames['preview'],
            'file_two_preview' => $fileTwoNames['preview'],
            'fio_user'         => security_xss($request->input('fio_user')),
            'ip_address'       => $request->ip(),
            'user_agent'       => $request->userAgent(),
            'status'           => 0,
            'hash_id'          => Str::random(30),
        ]);

        $this->notifyAdmins($verification);

        return response()->json(['verify_id' => $verification->hash_id]);
    }

    private function notifyAdmins(UserVerification $verification): void
    {
        try {
            foreach (get_admin_recipient_email() as $email) {
                Mail::to($email)->send(new AdminNewVerificationAccount($verification));
            }
        } catch (\Exception $exception) {
            Log::error('AdminNewVerificationAccount: ' . $exception->getMessage());
        }
    }

    /**
     * @return string|null error message or null when OK
     */
    private function assertSafeIdentityImage(?UploadedFile $file): ?string
    {
        if (!$file) {
            return 'Invalid image';
        }

        $original = (string) $file->getClientOriginalName();
        if ($original === '' || str_contains($original, '..') || str_contains($original, '/') || str_contains($original, '\\')) {
            return 'Invalid image name';
        }

        $ext = Str::lower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return 'Invalid image type';
        }

        $mime = Str::lower((string) ($file->getMimeType() ?: ''));
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/jpg'], true)) {
            return 'Invalid image type';
        }

        return null;
    }

    private function storeVerificationFiles($file): array
    {
        $ext = Str::lower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $ext = 'jpg';
        }

        $originalName = sprintf('%s-%s.%s', Str::random(5), Str::uuid(), $ext);
        $previewName = 'preview-' . $originalName;

        $diskPath = config('filesystems.disks.iexexchanger-disk.root') . '/' . config('image.folders.user_verification');

        Image::read($file->getRealPath())
            ->scaleDown(width: config('image.resizes.user_verification_preview'))
            ->save("$diskPath/$previewName");

        Image::read($file->getRealPath())
            ->scaleDown(width: config('image.resizes.user_verification'))
            ->save("$diskPath/$originalName");

        return [
            'original' => $originalName,
            'preview' => $previewName,
        ];
    }


    public function status(string $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => self::DENY_MESSAGE], 403);
        }

        // Client-supplied applicantId is metadata only — must exact-match server binding.
        if (!AssertIdentityApplicantAccess::allows((int) $user->id, $id)) {
            return response()->json(['message' => self::DENY_MESSAGE], 403);
        }

        $ownedApplicantId = AssertIdentityApplicantAccess::ownedApplicantId((int) $user->id);
        $response = (new SumSubDriver())->getStatus((string) $ownedApplicantId);

        // Текущий признак верификации из профиля
        $isVerified = (int) $user->is_verify_account === 1;

        // Нормализуем основные поля статуса из ответа Sumsub
        $reviewStatus = $response['reviewStatus'] ?? null;                    // init | pending | completed | ...
        $reviewAnswer = Arr::get($response, 'reviewResult.reviewAnswer');     // GREEN | RED | ... (только при completed)
        $rejectLabels = Arr::get($response, 'reviewResult.rejectLabels', []);
        $moderationComment = Arr::get($response, 'reviewResult.moderationComment');

        // Подтверждаем пользователя ТОЛЬКО когда проверка завершена И ответ GREEN
        if ($reviewStatus === 'completed' && $reviewAnswer === 'GREEN') {
            if ((int) $user->is_verify_account !== 1) {
                $user->is_verify_account = 1;
                $user->save();
            }

            // Обновляем запись SumsubId через Eloquent-модель
            SumsubId::query()
                ->where('user_id', $user->id)
                ->where('applicant_id', $ownedApplicantId)
                ->update([
                    'status'       => 'completed',
                    'is_completed' => 1,
                    'updated_at'   => now(),
                ]);

            $isVerified = 1;
        }

        // Если проверка завершена и ответ RED — НЕ подтверждаем, можно отдать причины на фронт
        $verdict = [
            'status' => $reviewStatus,
            'answer' => $reviewAnswer,
            'rejectLabels' => $rejectLabels,
            'moderationComment' => $moderationComment,
        ];

        // Минимальный лог: без полного SumSub payload / PII
        try {
            $userId = (int) $user->id;
            $lastStatus = DB::table('kyc_logs')
                ->where('provider', 'sumsub')
                ->where('event', 'status')
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->value('status');

            if ($reviewStatus !== null && $lastStatus !== $reviewStatus) {
                DB::table('kyc_logs')->insert([
                    'provider'      => 'sumsub',
                    'event'         => 'status',
                    'status'        => $reviewStatus,
                    'user_id'       => $userId,
                    'response_data' => json_encode([
                        'reviewStatus' => $reviewStatus,
                        'reviewAnswer' => $reviewAnswer,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'occurred_at'   => now(),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // не ломаем основной ответ, если лог не записался
        }

        return response()->json([
            'is_verified' => (int) $isVerified,
            'verdict' => $verdict,
        ]);
    }
}
