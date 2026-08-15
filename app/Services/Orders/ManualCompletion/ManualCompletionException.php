<?php

declare(strict_types=1);

namespace App\Services\Orders\ManualCompletion;

use RuntimeException;

/**
 * Structured completion failures for admin JSON (no HTML, no traces).
 */
final class ManualCompletionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, $httpStatus);
    }

    public static function unauthenticated(): self
    {
        return new self('unauthenticated', 'Authentication required', 401);
    }

    public static function forbidden(): self
    {
        return new self('forbidden', 'Not authorized to complete orders', 403);
    }

    public static function evidenceRequired(): self
    {
        return new self(
            'settlement_evidence_required',
            'Settlement reference or operator settlement note is required',
            422,
            ['settlement_reference' => ['Settlement evidence is required to complete this order']],
        );
    }

    public static function evidenceInvalid(string $reason): self
    {
        return new self(
            'settlement_evidence_invalid',
            $reason,
            422,
            ['settlement_reference' => [$reason]],
        );
    }

    public static function alreadyCompleted(): self
    {
        return new self('already_completed', 'Order is already completed', 409);
    }

    public static function invalidStatus(int $from): self
    {
        return new self(
            'invalid_completion_state',
            'Order cannot be completed from status '.$from,
            409,
        );
    }

    public static function bypassForbidden(): self
    {
        return new self(
            'completion_bypass_forbidden',
            'COMPLETED may only be written through the canonical completion path',
            409,
        );
    }

    public static function routeIdMismatch(): self
    {
        return new self('route_id_mismatch', 'Order id mismatch', 422);
    }

    public function toJsonResponse()
    {
        $payload = [
            'status' => 1,
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
        ];
        if ($this->errors !== []) {
            $payload['errors'] = $this->errors;
        }

        return response()->json($payload, $this->httpStatus);
    }
}
