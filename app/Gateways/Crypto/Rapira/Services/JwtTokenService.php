<?php
declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira\Services;

use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

class JwtTokenService
{
    protected string $privateKey;
    protected string $uuid;
    protected string $apiHost;

    public function __construct(?string $privateKey, ?string $uuid, ?string $apiHost)
    {
        $this->privateKey = $privateKey ?? '';
        $this->uuid = $uuid ?? '';
        $this->apiHost = $apiHost ?? '';
    }

    /**
     * Генерация JWT-токена для авторизации.
     *
     * @throws Exception
     */
    public function generate(): string
    {
        if (empty($this->privateKey) || empty($this->uuid) || empty($this->apiHost)) {
            return '';  // тихо возвращаем пустой токен
        }

        $key = InMemory::plainText(base64_decode($this->privateKey));
        $config = Configuration::forSymmetricSigner(new Sha256(), $key);
        $now = new DateTimeImmutable();

        $builder = $config->builder()
            ->identifiedBy(bin2hex(random_bytes(12)))
            ->expiresAt($now->modify('+1 month'))
            ->getToken($config->signer(), $config->signingKey());

        $response = Http::baseUrl('https://' . $this->apiHost)
            ->asJson()
            ->post('/open/generate_jwt', [
                'kid' => $this->uuid,
                'jwt_token' => strval($builder->toString()),
            ])
            ->throw()
            ->json();

        if (empty($response['token'])) {
            Log::error('Rapira JWT generation failed', ['response' => $response]);
            throw new Exception('Не удалось создать JWT-токен');
        }

        return $response['token'];
    }
}
