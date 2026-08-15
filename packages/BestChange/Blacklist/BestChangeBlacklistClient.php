<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist;

use App\Settings\BestChangeBlacklistConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use iEXPackages\BestChange\Blacklist\DTO\BlacklistCheckResult;
use iEXPackages\BestChange\Blacklist\DTO\BlacklistEntry;
use iEXPackages\BestChange\Blacklist\DTO\BlacklistSearchResult;
use iEXPackages\BestChange\Blacklist\Enums\BlacklistAddType;
use iEXPackages\BestChange\Blacklist\Exceptions\BlacklistApiException;
use iEXPackages\BestChange\Blacklist\Exceptions\BlacklistFeatureDisabledException;
use iEXPackages\BestChange\Blacklist\Exceptions\BlacklistTransportException;
use Throwable;

/**
 * BestChangeBlacklistClient
 *
 * Клиент для работы с BestChange Blacklist API (scamapi.php).
 *
 * Возможности:
 * - Поиск по базе "мошенники/неадекваты" (search)
 * - Добавление новой записи (add) — опционально и крайне осторожно
 * - Комплексная проверка набора полей (checkContext) — для использования в антифроде/валидации заявок
 *
 * Важно (дисклеймер BestChange):
 * - данные базы не гарантируют достоверность
 * - нельзя использовать как единственное основание для автоматического отказа
 * - рекомендуется только маркировать заявку как "нужна ручная проверка"
 * - нельзя раскрывать пользователю факт проверки по базе и любые данные из результатов
 */
final class BestChangeBlacklistClient
{
    private const BASE_URL = 'https://www.bestchange.org';
    private const ENDPOINT = '/member/scamapi.php';

    public function __construct(
        private readonly BestChangeBlacklistConfig $config,
    ) {}

    /**
     * Выполнить поиск по базе BestChange.
     *
     * @param string $query Ключевое слово (кошелёк/email/телефон/IP/ник и т.д.)
     * @param BlacklistSearchOptions|null $options Параметры поиска (where/type/allowEmptyQuery)
     *
     * @return BlacklistSearchResult Результат поиска с массивом найденных записей.
     *
     * @throws BlacklistFeatureDisabledException Если модуль выключен в настройках
     * @throws BlacklistTransportException Если проблемы сети/соединения
     * @throws BlacklistApiException Если API вернул неожиданный формат/ошибку
     */
    public function search(string $query, ?BlacklistSearchOptions $options = null): BlacklistSearchResult
    {
        $this->assertEnabled();

        $options ??= new BlacklistSearchOptions();
        $query = trim($query);

        // Пустой query вернёт всю базу (~тысячи записей). Разрешаем только осознанно.
        if ($query === '' && $options->allowEmptyQuery !== true) {
            throw new BlacklistApiException(
                'Пустой query запрещён: BestChange вернёт всю базу. Разрешайте только осознанно (allowEmptyQuery=true).'
            );
        }

        $params = [
            'format' => 'json',
            'id'     => (string) $this->config->apiId(),
            'key'    => (string) $this->config->apiKey(),
            'where'  => $options->where->value,
            'type'   => $options->type->value,
            'query'  => $query,
        ];

        $json = $this->requestJson($params, safeValueForLogs: $query);

        /** @var array<string,mixed>|null $check */
        $check = isset($json['check']) && is_array($json['check']) ? $json['check'] : null;
        if ($check === null) {
            throw new BlacklistApiException(
                'BestChange: неожиданный формат ответа (нет ключа "check").',
                payload: $json
            );
        }

        $request = isset($check['request']) && is_array($check['request']) ? $check['request'] : [];
        $responseRows = isset($check['response']) && is_array($check['response']) ? $check['response'] : [];

        $effectiveQuery = (string) ($request['query'] ?? $query);
        $totalFound     = (int) ($request['results'] ?? 0);

        $entries = [];
        foreach ($responseRows as $row) {
            if (is_array($row)) {
                $entries[] = BlacklistEntry::fromArray($row);
            }
        }

        return new BlacklistSearchResult(
            query: $effectiveQuery,
            totalFound: $totalFound,
            entries: $entries,
        );
    }

    /**
     * Добавить новую запись в базу BestChange.
     *
     * ВНИМАНИЕ:
     * - юридически и репутационно чувствительная операция
     * - используйте только при наличии внутренней модерации и аудита
     *
     * @param BlacklistAddType $type Тип записи (мошенник/неадекват)
     * @param string $contacts Контакты/кошельки/идентификаторы для записи
     * @param string $text Описание (причина/детали)
     *
     * @return bool true если API подтвердило добавление ("Adding OK")
     *
     * @throws BlacklistFeatureDisabledException
     * @throws BlacklistTransportException
     * @throws BlacklistApiException
     */
    public function add(BlacklistAddType $type, string $contacts, string $text): bool
    {
        $this->assertEnabled();

        $contacts = trim($contacts);
        $text = trim($text);

        if ($contacts === '' || $text === '') {
            throw new BlacklistApiException('Для добавления записи обязательны contacts и text.');
        }

        $params = [
            'id'       => (string) $this->config->apiId(),
            'key'      => (string) $this->config->apiKey(),
            'add'      => $type->value,
            'contacts' => $contacts,
            'text'     => $text,
        ];

        try {
            $body = Http::baseUrl(self::BASE_URL)
                ->timeout(10)
                ->retry(2, 250, throw: false)
                ->get(self::ENDPOINT, $params)
                ->throw()
                ->body();
        } catch (ConnectionException $e) {
            $this->logSafe('BestChange blacklist: ошибка соединения при add()', $contacts, $e);
            throw new BlacklistTransportException('BestChange: ошибка соединения при добавлении записи.', $e);
        } catch (RequestException $e) {
            $status = $e->response?->status();
            $body = (string) ($e->response?->body() ?? '');

            $this->logSafe('BestChange blacklist: HTTP ошибка при add()', $contacts, $e, $status);

            if ($this->looksLikeAuthError($body)) {
                throw new BlacklistApiException('BestChange: неверный login/key или обменник не активен.', httpStatus: $status);
            }

            throw new BlacklistApiException('BestChange: HTTP ошибка при добавлении записи.', httpStatus: $status);
        } catch (Throwable $e) {
            $this->logSafe('BestChange blacklist: непредвиденная ошибка при add()', $contacts, $e);
            throw new BlacklistApiException('BestChange: непредвиденная ошибка при добавлении записи.');
        }

        return str_contains($body, 'Adding OK');
    }

    /**
     * Комплексная проверка набора реквизитов по BestChange Blacklist.
     *
     * Назначение:
     * - использовать в антифроде/валидации заявок как "сигнал для ручной проверки"
     * - НЕ использовать для автоматического отказа
     *
     * Поведение:
     * - если модуль выключен — возвращаем OK
     * - какие поля проверять определяем по настройке categories() внутри BestChangeBlacklistConfig
     * - ошибки API не ломают бизнес-процесс: просто логируем и продолжаем
     *
     * @param string|null $email Email клиента
     * @param string|null $walletFrom Реквизит "отдаю" (кошелёк/счёт)
     * @param string|null $walletTo Реквизит "получаю" (кошелёк/счёт)
     *
     * @return BlacklistCheckResult Результат: нужно ли ставить ручную проверку и какое поле сработало.
     */
    public function checkContext(?string $email, ?string $walletFrom, ?string $walletTo): BlacklistCheckResult
    {
        if ((int) $this->config->isEnabled() !== 1) {
            return BlacklistCheckResult::ok();
        }

        $profile = $this->buildCheckProfileFromConfig();

        $email = $this->normalizeEmail($email);
        $walletFrom = $this->normalizeAccount($walletFrom);
        $walletTo = $this->normalizeAccount($walletTo);

        // Собираем проверки с дедупликацией
        $checks = [];

        if ($profile->checkEmail) {
            $this->addCheck($checks, key: 'email', field: $profile->fieldEmail, value: $email);
        }

        if ($profile->checkWalletFrom) {
            $this->addCheck($checks, key: 'wallet_from', field: $profile->fieldWalletFrom, value: $walletFrom);
        }

        if ($profile->checkWalletTo) {
            $this->addCheck($checks, key: 'wallet_to', field: $profile->fieldWalletTo, value: $walletTo);
        }

        if ($checks === []) {
            return BlacklistCheckResult::ok();
        }

        foreach ($checks as $check) {
            try {
                $result = $this->search($check['value']);
                if ($result->hasMatches()) {
                    return BlacklistCheckResult::hit($check['field'], 'bestchange_blacklist_hit');
                }
            } catch (Throwable $e) {
                // Ошибка внешнего API не должна ломать создание заявки
                Log::warning('BestChange blacklist check failed', [
                    'category' => $check['key'],
                    'value_hash' => hash('sha256', mb_strtolower($check['value'])),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return BlacklistCheckResult::ok();
    }

    /**
     * HTTP-запрос к API и получение JSON с корректной обработкой текстовых ошибок.
     *
     * В BestChange иногда приходит plain text:
     * - "Incorrect login or key, or the exchanger is not active!"
     *
     * @param array<string,mixed> $params
     * @param string $safeValueForLogs Значение для логирования в виде хэша (без PII)
     *
     * @return array<string,mixed>
     *
     * @throws BlacklistTransportException
     * @throws BlacklistApiException
     */
    private function requestJson(array $params, string $safeValueForLogs): array
    {
        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->acceptJson()
                ->timeout(10)
                ->retry(2, 250, throw: false)
                ->get(self::ENDPOINT, $params)
                ->throw();
        } catch (ConnectionException $e) {
            $this->logSafe('BestChange blacklist: ошибка соединения', $safeValueForLogs, $e);
            throw new BlacklistTransportException('BestChange: ошибка соединения.', $e);
        } catch (RequestException $e) {
            $status = $e->response?->status();
            $body = (string) ($e->response?->body() ?? '');

            $this->logSafe('BestChange blacklist: HTTP ошибка', $safeValueForLogs, $e, $status);

            if ($this->looksLikeAuthError($body)) {
                throw new BlacklistApiException('BestChange: неверный login/key или обменник не активен.', httpStatus: $status);
            }

            throw new BlacklistApiException('BestChange: HTTP ошибка ответа.', httpStatus: $status);
        }

        $status = $response->status();
        $contentType = (string) $response->header('Content-Type', '');
        $body = (string) $response->body();

        // Если пришёл текст с ошибкой — покажем понятное исключение
        if ($this->looksLikeAuthError($body)) {
            throw new BlacklistApiException('BestChange: неверный login/key или обменник не активен.', httpStatus: $status);
        }

        // Иногда Content-Type может быть неверным — пробуем распарсить JSON всё равно,
        // но если не получилось — выдаём понятную ошибку.
        $json = $response->json();

        if (!is_array($json)) {
            // Если сервер отдал plain text (или HTML), сообщаем как есть, но ограничиваем размер.
            $snippet = mb_substr(trim($body), 0, 300);

            throw new BlacklistApiException(
                message: 'BestChange: ответ не является валидным JSON.',
                httpStatus: $status,
                payload: [
                    'content_type' => $contentType,
                    'body' => $snippet,
                ]
            );
        }

        /** @var array<string,mixed> $json */
        return $json;
    }

    /**
     * Проверка, похож ли ответ на ошибку авторизации/активности обменника.
     */
    private function looksLikeAuthError(string $body): bool
    {
        $b = mb_strtolower(trim($body));

        return $b !== '' && (
                str_contains($b, 'incorrect login or key') ||
                str_contains($b, 'exchanger is not active')
            );
    }

    /**
     * Проверка включенности модуля.
     *
     * @throws BlacklistFeatureDisabledException
     */
    private function assertEnabled(): void
    {
        if ((int) $this->config->isEnabled() !== 1) {
            throw new BlacklistFeatureDisabledException('BestChange Blacklist API выключен в настройках.');
        }
    }

    /**
     * Безопасное логирование без утечки PII:
     * - не логируем email/телефоны/кошельки/IP
     * - логируем только хэш, чтобы можно было сопоставить события
     */
    private function logSafe(string $message, string $raw, Throwable $e, ?int $status = null): void
    {
        Log::warning($message, [
            'status' => $status,
            'error' => $e->getMessage(),
            'query_hash' => hash('sha256', mb_strtolower(trim($raw))),
        ]);
    }

    /**
     * Нормализация email.
     */
    private function normalizeEmail(?string $email): string
    {
        return trim((string) $email);
    }

    /**
     * Нормализация реквизита/кошелька/счёта:
     * - удаляем все пробельные символы
     */
    private function normalizeAccount(?string $value): string
    {
        $value = (string) $value;
        $value = preg_replace('/\s+/u', '', $value) ?? '';
        return trim($value);
    }

    /**
     * Профиль проверки: какие поля включены, и как называются поля результата.
     */
    private function buildCheckProfileFromConfig(): BlacklistCheckProfile
    {
        $enabled = $this->normalizeCategories($this->config->categories());

        return new BlacklistCheckProfile(
            checkEmail: (bool) ($enabled['email'] ?? false),
            checkWalletFrom: (bool) ($enabled['wallet_from'] ?? false),
            checkWalletTo: (bool) ($enabled['wallet_to'] ?? false),
            fieldEmail: 'email',
            fieldWalletFrom: 'sell',
            fieldWalletTo: 'buy',
        );
    }

    /**
     * Нормализует список категорий в удобный вид (ключ => true).
     *
     * @param array<int,string>|null $categories
     * @return array<string,bool>
     */
    private function normalizeCategories(?array $categories): array
    {
        $out = [];
        foreach (($categories ?? []) as $item) {
            $key = trim((string) $item);
            if ($key !== '') {
                $out[$key] = true;
            }
        }
        return $out;
    }

    /**
     * Добавляет проверку (если значение не пустое) и предотвращает повторы.
     *
     * @param array<string,array{key:string,field:string,value:string}> $checks
     */
    private function addCheck(array &$checks, string $key, string $field, string $value): void
    {
        if ($value === '') {
            return;
        }

        $dedupeKey = $key . ':' . hash('sha256', mb_strtolower($value));
        if (isset($checks[$dedupeKey])) {
            return;
        }

        $checks[$dedupeKey] = [
            'key' => $key,
            'field' => $field,
            'value' => $value,
        ];
    }
}
