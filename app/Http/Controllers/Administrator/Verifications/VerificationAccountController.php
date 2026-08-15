<?php

namespace App\Http\Controllers\Administrator\Verifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Verifications\VerificationAccountResources;
use App\Models\User;
use App\Models\UserVerification;
use iEXPackages\SmartMailer\Facades\SmartMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VerificationAccountController extends Controller
{
    /**
     * Дополнительные фильтры
     */
    protected array $allowFiltered = [
        'is_enabled_exchange_verify_account',
        'type_kyc_service',
        'sumsub_access_token',
        'sumsub_level_name',
        'sumsub_access_secret'
    ];

    /**
     * Список записей на верификацию счета
     *
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if($request->has('showPage') == 'settings') {
            return response()->json([
                'is_enabled_exchange_verify_account' => (int)iEXSetting('is_enabled_exchange_verify_account'),
                'type_kyc_service' => (int)iEXSetting('type_kyc_service'),
                'sumsub_access_token' => $this->maskSecret((string)iEXSetting('sumsub_access_token')),
                'sumsub_level_name' => (string)iEXSetting('sumsub_level_name'),
                'sumsub_access_secret' => $this->maskSecret((string)iEXSetting('sumsub_access_secret')),
                'sumsub_access_token_set' => strlen((string)iEXSetting('sumsub_access_token')) > 0,
                'sumsub_access_secret_set' => strlen((string)iEXSetting('sumsub_access_secret')) > 0,
            ]);
        }

        $accounts = UserVerification::orderBy('id', 'desc')->paginate(20);

        return response()->json([
            'items' => new VerificationAccountResources($accounts)
        ]);
    }

    /**
     * Обновление данные
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Если включена возможность обновления данных
        if($request->is_update == 1 and $request->has('showPage'))
        {
            // Обновление колонок
            if($request->showPage == 'settings')
            {
                $array = [];
                foreach ($this->allowFiltered as $item) {
                    if ($request->has($item)) {
                        $value = $request->get($item);
                        // Do not clobber secrets when UI posts masked placeholders.
                        if (in_array($item, ['sumsub_access_token', 'sumsub_access_secret'], true)
                            && $this->isMaskedSecretPlaceholder($value)) {
                            continue;
                        }
                        $array[$item] = is_numeric($value) && preg_match('/^\d+$/', $value)
                            ? (int)$value
                            : (string)$value;
                    } else {
                        if (!in_array($item, ['sumsub_access_token', 'sumsub_access_secret'], true)) {
                            $array[$item] = 0;
                        }
                    }
                }

                if ($array !== []) {
                    iEXSetting($array);
                }

                Log::info('verification_account_admin_settings', [
                    'admin_id' => auth()->id(),
                    'keys' => array_keys($array),
                    'ip' => $request->ip(),
                ]);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }
    }

    public function update(int $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 0 pending, 1 approved, 2 rejected
            'status' => ['required', 'numeric', 'in:0,1,2'],
        ]);

        // Перед добавлением новой валюты проверяем на ошибки
        if ($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $verification = UserVerification::find($id);
        if (!$verification) {
            return response()->json([
                'status' => 1,
                'message' => 'Not found',
            ], 404);
        }

        $previousStatus = (int) $verification->status;
        $newStatus = (int) $request->status;

        if ($previousStatus === $newStatus) {
            return response()->json([
                'status' => 0,
                'message' => $newStatus === 1 ? 'Аккаунт успешно верифицирован' : 'Верификация отклонена',
            ]);
        }

        $verification->update([
            'status' => $newStatus,
        ]);

        $user = User::find($verification->user_id);
        if ($user) {
            $user->update([
                'is_verify_account' => $newStatus === 1 ? 1 : 0,
            ]);
        }

        Log::info('verification_account_admin_review', [
            'verification_id' => $verification->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'admin_id' => auth()->id(),
            'target_user_id' => (int) $verification->user_id,
            'ip' => $request->ip(),
        ]);

        // Уведомление о верификации личности
        try {
            if ($newStatus !== 1 && (int) iEXSetting('is_email_fail_verification_account') == 1)
            {
                SmartMailer::dispatch(
                    sendable: 'verification_account_fail',
                    model: $verification,
                    email: $verification->email,
                    delaySeconds: 10,
                    queue: 'low'
                );
            }

            if ($newStatus === 1 && (int) iEXSetting('is_email_success_verification_account') == 1) {
                SmartMailer::dispatch(
                    sendable: 'verification_account_success',
                    model: $verification,
                    email: $verification->email,
                    delaySeconds: 10,
                    queue: 'low'
                );
            }
        } catch (\Exception $exception) {
            Log::error('(VerificationAccountSuccessMail, VerificationAccountFailMail) - '.$exception->getMessage());
        }

        return response()->json([
            'status' => 0,
            'message' => $newStatus === 1 ? 'Аккаунт успешно верифицирован' : 'Верификация отклонена'
        ]);
    }

    /**
     * Удаление счета
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $verification = UserVerification::find($id);
        if (!$verification) {
            return response()->json([
                'status' => 1,
                'message' => 'Not found',
            ], 404);
        }

        $verificationId = $verification->id;
        $targetUserId = (int) $verification->user_id;

        $user = User::find($verification->user_id);
        if ($user) {
            $user->update([
                'is_verify_account' => 0,
            ]);
        }

        $verification->delete();

        Log::info('verification_account_admin_delete', [
            'verification_id' => $verificationId,
            'admin_id' => auth()->id(),
            'target_user_id' => $targetUserId,
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Верификация успешно удалена'
        ]);
    }

    private function maskSecret(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= 4) {
            return '********';
        }

        return '********' . substr($value, -4);
    }

    private function isMaskedSecretPlaceholder(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $value = trim($value);
        if ($value === '') {
            return true;
        }

        return str_starts_with($value, '********');
    }
}
