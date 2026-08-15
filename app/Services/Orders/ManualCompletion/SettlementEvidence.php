<?php

declare(strict_types=1);

namespace App\Services\Orders\ManualCompletion;

/**
 * Smallest existing representation of operator settlement context.
 *
 * Accepts (first match wins):
 * - settlement_reference (string)
 * - extra_fields name/value pairs (existing Vue extra_fields)
 * - message_success / message (existing handler field)
 *
 * Same rule for CRYPTO / BANK / CARD / CASH / E_WALLET / OTHER:
 * an explicit operator reference or note, not a chain-specific txid.
 */
final class SettlementEvidence
{
    public const MAX_LENGTH = 191;

    public const MIN_LENGTH = 3;

    private function __construct(
        public readonly string $reference,
        public readonly string $source,
        public readonly array $extraFields,
    ) {
    }

    public static function fromOptions(array $options): self
    {
        if (array_key_exists('settlement_reference', $options) && $options['settlement_reference'] !== null) {
            $ref = self::normalizeScalar($options['settlement_reference'], 'settlement_reference');
            if ($ref !== null) {
                return new self($ref, 'settlement_reference', self::sanitizeExtraFields($options['otherFieldsForSuccess'] ?? []));
            }
        }

        $fields = self::sanitizeExtraFields($options['otherFieldsForSuccess'] ?? []);
        if ($fields !== []) {
            $joined = [];
            foreach ($fields as $row) {
                $joined[] = $row['name'].': '.$row['value'];
            }
            $reference = self::normalizeScalar(implode('; ', $joined), 'extra_fields');
            if ($reference !== null) {
                return new self($reference, 'extra_fields', $fields);
            }
        }

        foreach (['message', 'message_success'] as $key) {
            if (!array_key_exists($key, $options) || $options[$key] === null) {
                continue;
            }
            $ref = self::normalizeScalar($options[$key], $key);
            if ($ref !== null) {
                return new self($ref, $key, $fields);
            }
        }

        throw ManualCompletionException::evidenceRequired();
    }

    public function toAuditArray(int $operatorId, string $writer): array
    {
        return [
            'reference' => $this->reference,
            'source' => $this->source,
            'extra_fields' => $this->extraFields,
            'writer' => $writer,
            'completed_by' => $operatorId,
            'completed_at' => now()->toIso8601String(),
        ];
    }

    private static function normalizeScalar(mixed $value, string $field): ?string
    {
        if (is_array($value) || is_object($value)) {
            throw ManualCompletionException::evidenceInvalid('Settlement evidence must be a string, not an object or array');
        }
        if (is_bool($value)) {
            throw ManualCompletionException::evidenceInvalid('Settlement evidence must be a string');
        }
        if (!is_scalar($value)) {
            throw ManualCompletionException::evidenceInvalid('Settlement evidence must be a string');
        }

        $raw = (string) $value;
        if (!mb_check_encoding($raw, 'UTF-8')) {
            throw ManualCompletionException::evidenceInvalid('Settlement evidence must be valid UTF-8');
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        if (mb_strlen($trimmed) < self::MIN_LENGTH) {
            throw ManualCompletionException::evidenceInvalid('Settlement evidence is too short');
        }
        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw ManualCompletionException::evidenceInvalid('Settlement evidence exceeds '.self::MAX_LENGTH.' characters');
        }

        return $trimmed;
    }

    /**
     * @param mixed $fields
     * @return list<array{name: string, value: string}>
     */
    private static function sanitizeExtraFields(mixed $fields): array
    {
        if ($fields === null || $fields === []) {
            return [];
        }
        if (!is_array($fields)) {
            throw ManualCompletionException::evidenceInvalid('extra_fields must be a list of name/value pairs');
        }

        $out = [];
        foreach (array_slice($fields, 0, 5) as $item) {
            if (!is_array($item)) {
                throw ManualCompletionException::evidenceInvalid('extra_fields entries must be objects with name and value');
            }
            $name = $item['name'] ?? '';
            $value = $item['value'] ?? '';
            if (is_array($name) || is_object($name) || is_array($value) || is_object($value)) {
                throw ManualCompletionException::evidenceInvalid('extra_fields name and value must be strings');
            }
            $name = trim((string) $name);
            $value = trim((string) $value);
            if ($name === '' || $value === '') {
                continue;
            }
            if (mb_strlen($name) > 80 || mb_strlen($value) > self::MAX_LENGTH) {
                throw ManualCompletionException::evidenceInvalid('extra_fields value exceeds allowed length');
            }
            $out[] = ['name' => $name, 'value' => $value];
        }

        return $out;
    }
}
