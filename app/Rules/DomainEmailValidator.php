<?php

namespace App\Rules;

use Closure;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Egulias\EmailValidator\Validation\RFCValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Правило валидации email с проверкой разрешенных и запрещенных почтовых доменов,
 * а также валидности домена (наличие MX/A записей).
 *
 * Класс используется для обеспечения защиты от спама, нежелательной регистрации
 * и проверки корректности доменных записей.
 */
class DomainEmailValidator implements ValidationRule
{
    /**
     * @var array Список разрешенных почтовых доменов
     */
    protected array $allowedDomains;

    /**
     * @var array Список запрещенных почтовых доменов
     */
    protected array $blockedDomains;

    /**
     * @var int Время жизни кэша (в секундах) для DNS-запросов
     */
    protected int $cacheTtl;

    /**
     * @var EmailValidator Экземпляр валидатора email-адресов
     */
    protected EmailValidator $emailValidator;

    /**
     * DomainEmailValidator constructor.
     *
     * Инициализирует списки разрешенных и запрещенных доменов,
     * а также устанавливает TTL для кэша и экземпляр валидатора email.
     */
    public function __construct()
    {
        $this->allowedDomains = $this->parseDomains(iEXSetting('email_allowed_domains'));
        $this->blockedDomains = $this->parseDomains(iEXSetting('email_blocked_domains'));
        $this->cacheTtl = Config::get('mail.cache_ttl', 86400);

        $this->emailValidator = new EmailValidator();
    }

    /**
     * Основная функция валидации email.
     *
     * @param string $attribute Имя валидируемого атрибута
     * @param mixed $value Значение атрибута (email)
     * @param Closure $fail Функция, вызываемая при ошибке валидации
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((int)iEXSetting('enable_email_domain_protection') === 0) {
            return;
        }

        $email = strtolower(trim((string) $value));

        if ($email === '') {
            return;
        }

        // Строгая проверка формата email с проверкой DNS
        if (
            !$this->emailValidator->isValid(
                $email,
                new MultipleValidationWithAnd([
                    new RFCValidation(),
                    new DNSCheckValidation()
                ])
            )
        ) {
            Log::warning('Попытка регистрации с некорректным форматом email', ['email' => $email]);
            $fail(__('Некорректный формат email.'));
            return;
        }

        if (!str_contains($email, '@')) {
            Log::warning('Попытка регистрации с email без домена', ['email' => $email]);
            $fail(__('Некорректный формат email.'));
            return;
        }

        [, $domain] = explode('@', $email, 2);
        $domain = strtolower(trim($domain));

        if (!$this->hasValidMxRecord($domain)) {
            Log::warning('Попытка регистрации с домена без валидной MX/A записи', ['email' => $email, 'domain' => $domain]);
            $fail(__('Почтовый домен не существует или не принимает почту.'));
            return;
        }

        if (empty($this->allowedDomains) && empty($this->blockedDomains)) {
            return;
        }

        if (!empty($this->allowedDomains) && !in_array($domain, $this->allowedDomains, true)) {
            Log::info('Попытка регистрации с неразрешённого домена', ['email' => $email, 'domain' => $domain]);
            $fail(__('Регистрация с этого почтового домена не разрешена.'));
            return;
        }

        if (!empty($this->blockedDomains) && in_array($domain, $this->blockedDomains, true)) {
            Log::warning('Попытка регистрации с запрещённого домена', ['email' => $email, 'domain' => $domain]);
            $fail(__('Регистрация с этого почтового домена запрещена.'));
            return;
        }
    }

    /**
     * Проверяет наличие валидной MX или A записи у домена.
     *
     * Результат проверки кэшируется на время, указанное в $cacheTtl.
     *
     * @param string $domain Почтовый домен для проверки
     * @return bool Результат проверки MX/A записей домена
     */
    protected function hasValidMxRecord(string $domain): bool
    {
        return Cache::remember("email_mx_record_{$domain}", $this->cacheTtl, function () use ($domain) {
            try {
                return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
            } catch (\Throwable $e) {
                Log::error('Ошибка DNS-запроса при проверке домена', [
                    'domain' => $domain,
                    'error' => $e->getMessage()
                ]);
                // При DNS-ошибке запрещаем почту (для безопасности)
                return false;
            }
        });
    }

    /**
     * Преобразует строку доменов (через запятую или новую строку) в массив.
     *
     * Убирает лишние пробелы и пустые элементы.
     *
     * @param string|null $domains Строка доменов для парсинга
     * @return array Массив доменов
     */
    private function parseDomains(?string $domains): array
    {
        if (empty($domains)) {
            return [];
        }

        // Заменяем переносы строк на запятые и разбиваем в массив
        return array_filter(
            array_map(
                static fn (string $item) => strtolower(trim($item)),
                preg_split("/[\n,]+/", $domains)
            )
        );
    }
}
