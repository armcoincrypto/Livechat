<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Result;

use iEXPackages\Order\Validation\Enums\EffectType;
use iEXPackages\Order\Validation\Enums\Severity;

/**
 * ValidationResult — результат валидации:
 * - violations: ошибки/предупреждения
 * - effects: эффекты для применения после создания заявки
 *
 * Сделано в одном файле для компактности.
 */
final class ValidationResult
{
    /** @var array<int, array{field:string,message:string,code:string,severity:Severity,modal:bool,meta:array}> */
    private array $violations = [];

    /** @var array<int, array{type:EffectType,payload:array}> */
    private array $effects = [];

    /** @var array<string,true> */
    private array $effectDedup = [];

    public static function ok(): self
    {
        return new self();
    }

    /**
     * @param array<string,mixed> $meta
     */
    public function addViolation(
        string $field,
        string $message,
        string $code = 'validation_error',
        Severity $severity = Severity::Error,
        bool $modal = false,
        array $meta = []
    ): self {
        $this->violations[] = [
            'field' => $field,
            'message' => $message,
            'code' => $code,
            'severity' => $severity,
            'modal' => $modal,
            'meta' => $meta,
        ];

        return $this;
    }

    /**
     * @param array<string,mixed> $meta
     */
    public function addError(string $field, string $message, string $code = 'validation_error', bool $modal = false, array $meta = []): self
    {
        return $this->addViolation($field, $message, $code, Severity::Error, $modal, $meta);
    }

    /**
     * @param array<string,mixed> $meta
     */
    public function addWarning(string $field, string $message, string $code = 'validation_warning', bool $modal = false, array $meta = []): self
    {
        return $this->addViolation($field, $message, $code, Severity::Warning, $modal, $meta);
    }

    /**
     * Добавить эффект (с дедупликацией).
     *
     * По умолчанию эффекты дедупятся по ключу:
     *   type + sha1(json(payload))
     *
     * Это защищает от повторных добавлений одним и тем же правилом или при merge().
     *
     * @param array<string,mixed> $payload
     */
    public function addEffect(EffectType $type, array $payload = []): self
    {
        $dedupKey = $this->makeEffectDedupKey($type, $payload);

        if (isset($this->effectDedup[$dedupKey])) {
            return $this;
        }

        $this->effectDedup[$dedupKey] = true;

        $this->effects[] = [
            'type' => $type,
            'payload' => $payload,
        ];

        return $this;
    }

    public function merge(self $other): self
    {
        foreach ($other->violations as $v) {
            $this->violations[] = $v;
        }

        foreach ($other->effects as $e) {
            $this->addEffect($e['type'], $e['payload']);
        }

        return $this;
    }

    public function hasErrors(): bool
    {
        foreach ($this->violations as $v) {
            if ($v['severity'] === Severity::Error) {
                return true;
            }
        }
        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->violations as $v) {
            if ($v['severity'] === Severity::Warning) {
                return true;
            }
        }
        return false;
    }

    public function hasViolations(): bool
    {
        return $this->violations !== [];
    }

    /** @return array<int, array{field:string,message:string,code:string,severity:Severity,modal:bool,meta:array}> */
    public function violations(): array
    {
        return $this->violations;
    }

    /** @return array<int, array{type:EffectType,payload:array}> */
    public function effects(): array
    {
        return $this->effects;
    }

    /**
     * Старый формат ошибок для твоего API.
     *
     * @return array<int, array<string,mixed>>
     */
    public function toLegacyErrorsArray(): array
    {
        $out = [];

        foreach ($this->violations as $v) {
            if ($v['severity'] !== Severity::Error) {
                continue;
            }

            $out[] = [
                'field' => $v['field'],
                'message' => $v['message'],
                'code' => $v['code'],
                'modal' => $v['modal'],
                'meta' => $v['meta'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function makeEffectDedupKey(EffectType $type, array $payload): string
    {
        // стабильный ключ: type + hash payload
        // json_encode с сортировкой ключей можно усложнять, но это уже перебор
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return $type->value . ':' . sha1($json ?: '');
    }
}
