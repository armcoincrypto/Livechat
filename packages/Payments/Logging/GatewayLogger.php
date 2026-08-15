<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Logging;

use App\Models\PaymentGatewayLog;

final class GatewayLogger
{
    public function __construct(
        private readonly SensitiveDataMasker $masker,
    ) {}

    public function write(array $row): void
    {
        // Маскирование
        if (isset($row['request_headers']) && is_array($row['request_headers'])) {
            $row['request_headers'] = $this->masker->mask($row['request_headers']);
        }
        if (isset($row['request_body']) && is_array($row['request_body'])) {
            $row['request_body'] = $this->masker->mask($row['request_body']);
        }
        if (isset($row['response_body']) && is_array($row['response_body'])) {
            $row['response_body'] = $this->masker->mask($row['response_body']);
        }

        // Здесь мы сохраняем в проектную таблицу
        PaymentGatewayLog::create($row);
    }
}
