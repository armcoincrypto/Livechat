<?php
namespace App\Gateways\Crypto\Exnode\Services;

class SecureSignatureService
{
    private ?string $privateKey;
    private string $algo;
    private int $timestamp;

    public function __construct(?string $privateKey = null, string $algo = 'sha512')
    {
        $this->privateKey = $privateKey;
        $this->algo = $algo;
        $this->timestamp = time();
    }

    /**
     * Установка собственного timestamp (можно использовать для тестов или синхронизации).
     */
    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * Получить текущую временную метку.
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Безопасное кодирование массива в JSON.
     */
    private function encodeBody(?array $body): string
    {
        return empty($body) ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Генерация сигнатуры по временной метке и телу запроса.
     */
    public function generateSignature(?array $body = null): ?string
    {
        if (empty($this->privateKey)) {
            return null;
        }

        $message = $this->timestamp . $this->encodeBody($body);
        return hash_hmac($this->algo, $message, $this->privateKey);
    }

    /**
     * Проверка подписи (например, на стороне сервера при приёме данных).
     */
    public function verifySignature(string $signature, ?array $body = null, int $timestamp = null): bool
    {
        if (empty($this->privateKey)) {
            return false;
        }

        $timestamp = $timestamp ?? $this->timestamp;
        $message = $timestamp . $this->encodeBody($body);
        $expected = hash_hmac($this->algo, $message, $this->privateKey);

        return hash_equals($expected, $signature);
    }
}
