<?php
declare(strict_types=1);

namespace iEXPackages\KYCPlugin\Drivers\SumSub;

use Illuminate\Support\Facades\Auth;

use App\Models\SumsubId;
use RuntimeException;


/**
 * Драйвер интеграции с Sumsub для получения SDK-токена и статуса верификации.
 * Работает поверх `SumsubClient` и обеспечивает единообразие externalUserId.
 */
class SumSubDriver
{
    /**
     * Получить (или создать) аппликанта и сгенерировать SDK-токен по externalUserId.
     *
     * Client-supplied user identity is ignored — always bound to authenticated user.
     *
     * @param int|string $userId Внутренний ID пользователя (ignored when auth present; must match auth)
     * @param array $options Доп. опции для создания аппликанта (email, type, extra)
     * @return array{applicant_id:string, access_token: array{token:string,userId:string}}
     */
    public function getAccessToken(int|string $userId, array $options = []): array
    {
        $authId = Auth::id();
        if ($authId === null) {
            throw new RuntimeException('SumSub access token requires an authenticated user');
        }

        // Never allow client/caller to choose a different external identity.
        $userId = (string) $authId;

        // Drop client-controlled identity fields; keep only safe contact/lang hints from server user.
        $safeOptions = [
            'type' => 'individual',
        ];
        if (!empty($options['email']) && is_string($options['email'])) {
            $safeOptions['email'] = $options['email'];
        }
        // levelName always from server settings inside createApplicant/getAccessToken below
        unset($options['extra'], $options['levelName'], $options['level'], $options['externalUserId'], $options['userId']);

        $sumsubClient = new SumsubClient(iEXSetting('sumsub_access_token'), iEXSetting('sumsub_access_secret'));

        // Для контроля "свежести" applicant — 10 минут (600 секунд)
        $freshSeconds = 600;

        $levelName = iEXSetting('sumsub_level_name');

        try {
            // 1. Пробуем создать applicant и получить токен
            $applicantId = $sumsubClient->createApplicant($userId, $levelName, $safeOptions);

            // Сохраняем в базу
            $sumsubRecord = SumsubId::updateOrCreate(
                ['user_id' => (int) $authId],
                [
                    'applicant_id' => $applicantId,
                    'status' => 'created',
                    'provider' => 'sumsub',
                    'expire_at' => now()->addSeconds($freshSeconds),
                    'sumsub_data' => [
                        'user_id' => $userId,
                        'with_prefix' => $sumsubClient->getPrefix().$userId
                    ]
                ]
            );
            // Получаем accessToken (новый)
            $accessToken = $sumsubClient->getAccessToken($userId, $levelName);

            return [
                'applicant_id' => $applicantId,
                'access_token' => $accessToken
            ];
        } catch (\Throwable $e) {
            // 2. Ошибка запроса — смотрим, есть ли свежий applicant в базе
            $sumsubRecord = SumsubId::where('user_id', (int) $authId)->first();


            if ($sumsubRecord) {

                $sumsubRecord->expire_at = now()->addSeconds($freshSeconds);
                $sumsubRecord->save();

                // Свежий applicant — выдаём новый токен на него
                try {
                    $accessToken = $sumsubClient->getAccessToken($userId, $levelName);
                    return [
                        'applicant_id' => $sumsubRecord->applicant_id,
                        'access_token' => $accessToken
                    ];
                } catch (\Throwable $ee) {
                    // Если и здесь ошибка — пробуем ниже
                }
            }

            // Если ничего не сработало — пробрасываем последнюю ошибку
            throw $e;
        }
    }

    /**
     * Получить статус верификации по applicant id (caller must authorize ownership).
     *
     * @param int|string $userId Applicant id (legacy param name)
     * @return array Ассоциативный массив статуса Sumsub
     */
    public function getStatus(int|string $userId): array
    {
        $sumsubClient = new SumsubClient(iEXSetting('sumsub_access_token'), iEXSetting('sumsub_access_secret'));
        $response = $sumsubClient->getApplicantStatus((string) $userId);

        return $response ?? [];
    }

    public function getStatusById(int|string $userId): array
    {
        $sumsubClient = new SumsubClient(iEXSetting('sumsub_access_token'), iEXSetting('sumsub_access_secret'));
        $response = $sumsubClient->getApplicantIdByExternalUserId((string) $userId);

        return $response ?? [];
    }
}
