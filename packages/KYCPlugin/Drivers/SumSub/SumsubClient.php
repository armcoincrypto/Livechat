<?php
declare(strict_types = 1);

namespace iEXPackages\KYCPlugin\Drivers\SumSub;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Лёгкий клиент Sumsub на базе Laravel HTTP-клиента.
 *
 * Особенности:
 * - Подписывает каждый запрос (X-App-Token, X-App-Access-Ts, X-App-Access-Sig);
 * - Работает с externalUserId с единым префиксом (по умолчанию: "u-");
 * - Предоставляет методы для создания аппликанта, получения статуса и генерации SDK-токена.
 */
class SumsubClient
{
    private string $sumSubPrefix = 'user-';

    /**
     * Вернуть префикс для externalUserId (например, "u-").
     */
    public function getPrefix(): string
    {
        return $this->sumSubPrefix;
    }

    protected const BASE_URL = 'https://api.sumsub.com';


    public function __construct(
        protected string $appToken,
        protected string $secretKey
    )
    {
        //
    }


    /**
     * Создаёт аппликанта или возвращает ID существующего при ответе 409.
     *
     * @param string $externalUserId Внешний ID пользователя (без префикса) либо уже с префиксом
     * @param string $levelName Имя уровня проверки Sumsub
     * @param array{type?:'individual'|'company',email?:string,extra?:array} $options Доп. опции:
     *        - type: тип заявителя (по умолчанию 'individual');
     *        - email: e-mail пользователя;
     *        - extra: произвольные поля, которые должны попасть в тело запроса.
     * @return string ID аппликанта
     * @throws RuntimeException При ошибке HTTP-запроса или некорректном ответе API
     */
    public function createApplicant(string $externalUserId, string $levelName, array $options = []): string
    {
        $extId = $this->getPrefix() . $externalUserId;

        $requestBody = array_merge([
            'externalUserId' => $extId,
            'type' => $options['type'] ?? 'individual',
        ], $options['extra'] ?? []);

        if (!empty($options['email'])) {
            $requestBody['email'] = $options['email'];
        }

        $url = '/resources/applicants?' . http_build_query(['levelName' => $levelName]);

        $response = $this->makeRequest('post', $url, $requestBody);

        // Если applicant уже существует — достаём его id
        if ($response->status() === 409) {


            $existingId = $this->getApplicantIdByExternalUserId($extId);

            if ($existingId) {
                return $existingId;
            }
            throw new RuntimeException('Sumsub вернул 409, но аппликант по externalUserId не найден: ' . $response->body());
        }

        if ($response->failed()) {
            throw new RuntimeException('Ошибка запроса к Sumsub: ' . $response->body());
        }

        $body = $response->json();

        return $body['id'];
    }


    /**
     * Возвращает ID аппликанта по externalUserId.
     * Поддерживает оба формата ответа Sumsub: одиночный объект с ключом 'id' или список в 'list.items'.
     *
     * @param string $externalUserId Внешний ID (без префикса или с ним)
     * @return string|null ID аппликанта или null, если не найден
     */
    public function getApplicantIdByExternalUserId(string $externalUserId): ?string
    {
        $url = '/resources/applicants/-;externalUserId=' . $externalUserId;
        $response = $this->makeRequest('get', $url);

        if ($response->failed()) {
            return null;
        }

        $body = $response->json();

        // 1) Если API вернул одиночный объект с ключом 'id' — используем его
        $singleId = Arr::get($body, 'id');
        if (is_string($singleId) && $singleId !== '') {
            return $singleId;
        }

        // 2) Иначе ищем первый элемент в list.items и возвращаем его id — без фильтрации по статусам
        $first = collect(Arr::get($body, 'list.items', []))->first();

        return is_array($first) ? (Arr::get($first, 'id') ?: null) : null;
    }

    /**
     * Возвращает статус шагов верификации аппликанта.
     *
     * @param string $id ID аппликанта или внешний ID (с префиксом)
     * @param array{by?:'external',only?:string[]} $options Опции выборки:
     *        - by: если 'external' — искать по externalUserId;
     *        - only: вернуть только указанные ключи верхнего уровня из ответа.
     * @return array Ассоциативный массив ответа Sumsub
     * @throws RuntimeException При ошибке HTTP-запроса
     */
    public function getApplicantStatus(string $id, array $options = []): array
    {
        $url = '/resources/applicants/' . urlencode($id) . '/status';

        $response = $this->makeRequest('get', $url);

        if ($response->failed()) {
            throw new RuntimeException('Ошибка получения статуса Sumsub: ' . $response->body());
        }

        $body = $response->json();

        // Позволяем опционально вернуть только выбранные поля: ['only' => ['reviewStatus','levelName',...]]
        if (!empty($options['only']) && is_array($options['only'])) {
            return array_intersect_key($body, array_flip($options['only']));
        }

        return $body;
    }

    /**
     * Генерирует SDK-токен, привязанный к externalUserId (не к applicantId!).
     *
     * @param string $externalUserId Внешний ID (без префикса) или уже с префиксом
     * @param string $levelName Имя уровня проверки Sumsub
     * @return array{token:string,userId:string} Токен и userId, к которому он привязан
     * @throws RuntimeException При ошибке HTTP-запроса
     */
    public function getAccessToken(string $externalUserId, string $levelName): array
    {
        $extId = str_starts_with($externalUserId, $this->getPrefix()) ? $externalUserId : ($this->getPrefix() . $externalUserId);
        $requestBody = [
            'userId'    => $extId,
            'levelName' => $levelName,
        ];

        $url = '/resources/accessTokens/sdk';

        $response = $this->makeRequest('post', $url, $requestBody);

        // Если аппликант деактивирован — пробуем реактировать и повторить 1 раз
        if ($response->status() === 404 && str_contains((string) $response->body(), 'deactivated')) {
            $applicantId = $this->getApplicantIdByExternalUserId($extId);
            if ($applicantId) {
                $this->reactivateApplicant($applicantId);
                // retry once
                $response = $this->makeRequest('post', $url, $requestBody);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('Ошибка генерации SDK-токена Sumsub: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Реактивировать деактивированного аппликанта.
     *
     * @param string $applicantId
     * @return void
     * @throws RuntimeException
     */
    public function reactivateApplicant(string $applicantId): void
    {
        $url = '/resources/applicants/' . urlencode($applicantId) . '/presence/activated';
        // Согласно API Sumsub эндпоинт активации принимает PATCH без тела
        $response = $this->makeRequest('patch', $url, []);
        if ($response->failed()) {
            throw new RuntimeException('Не удалось реактивировать аппликанта: ' . $response->body());
        }
    }

    /**
     * Выполняет подписанный запрос к Sumsub с использованием Laravel HTTP-клиента.
     *
     * @param non-empty-string $method Один из: GET, POST, PATCH, DELETE (без учёта регистра)
     * @param string $url Относительный путь, начинающийся с "/resources/..."
     * @param array $body Тело запроса (для не-GET), будет сериализовано в JSON
     * @return Response Ответ HTTP-клиента
     */
    protected function makeRequest(string $method, string $url, array $body = []): Response
    {
        $now = time();
        $isGet = strtoupper($method) === 'GET';
        $payload = $isGet ? '' : json_encode($body);
        $sig = hash_hmac('sha256', $now . strtoupper($method) . $url . $payload, $this->secretKey);

        $http = Http::withHeaders([
            'X-App-Token'      => $this->appToken,
            'X-App-Access-Ts'  => $now,
            'X-App-Access-Sig' => $sig,
            'Content-Type'     => 'application/json',
        ]);

        if ($isGet) {
            // тело не передаём вовсе
            return $http->get(self::BASE_URL . $url);
        }

        return $http->$method(self::BASE_URL . $url, $body);
    }
}
