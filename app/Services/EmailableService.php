<?php

namespace App\Services;


use Illuminate\Support\Facades\Http;

class EmailableService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.emailable.com/v1/verify';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Проверить email через Emailable API с дополнительной проверкой качества (score).
     */
    public function verifyEmail(string $email, int $minScore = 80): array
    {
        $response = Http::get($this->apiUrl, [
            'email' => $email,
            'api_key' => $this->apiKey,
        ]);

        if ($response->successful()) {
            $result = $response->json();

            if (($result['score'] ?? 0) < $minScore) {
                return [
                    'error' => true,
                    'message' => "Низкий рейтинг доверия email (Score: {$result['score']}).",
                    'data' => $result,
                ];
            }

            return $result;
        }

        return [
            'error' => true,
            'message' => $response->json('message', 'Ошибка запроса к Emailable API'),
        ];
    }
}
