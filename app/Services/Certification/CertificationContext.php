<?php

declare(strict_types=1);

namespace App\Services\Certification;

use App\Models\Task;

/**
 * Request-scoped certification session. Inactive unless artisan/operator
 * explicitly activates it after CertificationGate allows.
 */
final class CertificationContext
{
    public const SCENARIO_SUCCESS = 'success';

    public const SCENARIO_FAIL_CLOSED = 'fail_closed';

    private bool $active = false;

    private string $scenario = self::SCENARIO_SUCCESS;

    private string $email = '';

    private string $token = '';

    private int $kobbopayHttpAttempts = 0;

    /** @var list<array{eligible:bool, order_id:?int}> */
    private array $mailRecords = [];

    /** @var list<array{event:string, order_id:?int, sent:bool}> */
    private array $telegramRecords = [];

    private ?int $orderId = null;

    public function activate(string $scenario, string $email, string $token): void
    {
        $this->active = true;
        $this->scenario = $scenario === self::SCENARIO_FAIL_CLOSED
            ? self::SCENARIO_FAIL_CLOSED
            : self::SCENARIO_SUCCESS;
        $this->email = $email;
        $this->token = $token;
        $this->kobbopayHttpAttempts = 0;
        $this->mailRecords = [];
        $this->telegramRecords = [];
        $this->orderId = null;
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->email = '';
        $this->token = '';
        $this->scenario = self::SCENARIO_SUCCESS;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function scenario(): string
    {
        return $this->scenario;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function recordKobbopayHttpAttempt(): void
    {
        $this->kobbopayHttpAttempts++;
    }

    public function kobbopayHttpCallCount(): int
    {
        return $this->kobbopayHttpAttempts;
    }

    public function recordMailEligibility(bool $eligible, ?Task $order): void
    {
        $this->mailRecords[] = [
            'eligible' => $eligible,
            'order_id' => $order?->id,
        ];
    }

    /**
     * @return list<array{eligible:bool, order_id:?int}>
     */
    public function mailRecords(): array
    {
        return $this->mailRecords;
    }

    public function recordTelegram(string $event, ?Task $order, bool $sent): void
    {
        $this->telegramRecords[] = [
            'event' => $event,
            'order_id' => $order?->id,
            'sent' => $sent,
        ];
    }

    /**
     * @return list<array{event:string, order_id:?int, sent:bool}>
     */
    public function telegramRecords(): array
    {
        return $this->telegramRecords;
    }

    public function recordOrder(Task $order): void
    {
        $this->orderId = $order->id;
    }

    public function orderId(): ?int
    {
        return $this->orderId;
    }
}
