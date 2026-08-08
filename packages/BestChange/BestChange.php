<?php
declare(strict_types=1);

namespace iEXPackages\BestChange;

use iEXPackages\BestChange\Blacklist\BestChangeBlacklistClient;

final class BestChange
{
    /**
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * @param array<string,mixed> $config Обычно config('courses.bestchange', [])
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function rates(): RatesConnection
    {
        return new RatesConnection($this->config);
    }

    /**
     * Клиент для BestChange Blacklist API.
     */
    public function blacklist(): BestChangeBlacklistClient
    {
        /** @var BestChangeBlacklistClient $client */
        $client = app(BestChangeBlacklistClient::class);

        return $client;
    }

    /**
     * @return array<string,mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
