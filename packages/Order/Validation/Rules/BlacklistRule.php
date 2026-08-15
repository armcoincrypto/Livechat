<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\BlacklistOrder;
use App\Models\DirectionExchange;
use App\Settings\BestChangeBlacklistConfig;
use iEXPackages\BestChange\Facades\BestChangeFacade;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Enums\EffectType;
use iEXPackages\Order\Validation\Result\ValidationResult;
use Illuminate\Support\Facades\Log;

/**
 * Правило: проверка заявки по blacklist (Local + BestChange).
 *
 * Требования:
 * - Никаких callback
 * - Никаких новых сервисов/DTO/классов для blacklist
 * - Вся логика в одном файле
 * - BestChange модуль не правим
 *
 * Стратегия реакции:
 * - если есть совпадение BestChange → используем BestChangeBlacklistConfig::method()
 * - иначе → iEXSetting('is_blacklist_method')
 *
 * method:
 * - 0 => отказ (ошибка)
 * - 1 => freeze (пометка заявки через эффект)
 */
final class BlacklistRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        $email = $context->env->email();
        $ip = $context->env->ip();

        $from = $this->normalizeAccount(
            $context->data->getString('income_account'),
            (int)($direction->currency1?->remove_spaces_requisite ?? 0)
        );

        $to = $this->normalizeAccount(
            $context->data->getString('outcome_account'),
            (int)($direction->currency2?->remove_spaces_requisite ?? 0)
        );

        $hits = [];

        // local
        if ((int)iEXSetting('is_local_blacklist') === 1) {
            $localHit = $this->checkLocal($email, $ip, $from, $to);
            if ($localHit !== null) $hits[] = $localHit;
        }

        // bestchange
        $bestMethod = null;
        $bestField = null;

        try {
            /** @var BestChangeBlacklistConfig $cfg */
            $cfg = app(BestChangeBlacklistConfig::class);

            if ((int)$cfg->isEnabled() === 1) {
                $bestMethod = (int)$cfg->method();

                $bc = BestChangeFacade::blacklist()->checkContext(
                    email: (string)($email ?? ''),
                    walletFrom: (string)($from ?? ''),
                    walletTo: (string)($to ?? ''),
                );

                if ($bc->requiresManualReview) {
                    $bestField = (string)($bc->field ?? 'blacklist');

                    $hits[] = [
                        'source' => 'bestchange',
                        'field' => $bestField,
                        'reason' => (string)($bc->reason ?? 'bestchange_match'),
                        'meta' => [],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('BestChange blacklist error: ' . $e->getMessage());
        }

        if ($hits === []) {
            return $result;
        }

        $hasBest = $this->hasSource($hits, 'bestchange');

        $method = $hasBest
            ? (int)($bestMethod ?? 0)
            : (int)iEXSetting('is_blacklist_method', 0);

        $type = $hasBest ? 'bestchange' : 'blacklist';
        $field = $hasBest ? ($bestField ?: 'blacklist') : 'blacklist';

        $neutralMessage = __('Заявка требует дополнительной проверки оператором.');

        // deny
        if ($method === 0) {
            $this->safeLog($context, 'deny', $type, $hits);

            return $result->addError(
                field: $field,
                message: $neutralMessage,
                code: 'manual_review_required',
                modal: false,
                meta: ['hits' => $this->safeHits($hits)]
            );
        }

        // freeze
        $this->safeLog($context, 'freeze', $type, $hits);

        return $result->addEffect(EffectType::FreezeScam, [
            'type' => $type,
            'hits' => $this->safeHits($hits),
        ]);
    }

    /**
     * Нормализация реквизита:
     * - trim
     * - если removeSpaces=1 → убираем пробелы
     */
    private function normalizeAccount(?string $raw, int $removeSpaces): ?string
    {
        if ($raw === null) return null;

        $s = trim($raw);
        if ($s === '') return null;

        if ($removeSpaces === 1) {
            $s = preg_replace('/\s+/', '', $s) ?? $s;
        }

        $s = trim($s);
        return $s !== '' ? $s : null;
    }

    /**
     * Локальная проверка через таблицу BlacklistOrder.
     *
     * @return array<string,mixed>|null
     */
    private function checkLocal(?string $email, string $ip, ?string $from, ?string $to): ?array
    {
        try {
            $email = is_string($email) ? trim($email) : '';
            $ip = trim($ip);
            $from = is_string($from) ? trim($from) : '';
            $to = is_string($to) ? trim($to) : '';

            $row = BlacklistOrder::query()
                ->where(function ($q) use ($ip, $email, $from, $to) {
                    if ($ip !== '') $q->where('value', 'like', '%' . $ip . '%');
                    if ($email !== '') $q->orWhere('value', 'like', '%' . $email . '%');
                    if ($to !== '') $q->orWhere('value', 'like', '%' . $to . '%');
                    if ($from !== '') $q->orWhere('value', 'like', '%' . $from . '%');
                })
                ->first();

            if (!$row) return null;

            return [
                'source' => 'local',
                'field' => 'blacklist',
                'reason' => 'local_match',
                'meta' => ['blacklist_id' => (int)$row->id],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<int,array<string,mixed>> $hits */
    private function hasSource(array $hits, string $source): bool
    {
        foreach ($hits as $h) {
            if (($h['source'] ?? '') === $source) return true;
        }
        return false;
    }

    /**
     * @param array<int,array<string,mixed>> $hits
     * @return array<int,array<string,mixed>>
     */
    private function safeHits(array $hits): array
    {
        $out = [];
        foreach ($hits as $h) {
            $out[] = [
                'source' => (string)($h['source'] ?? ''),
                'field' => (string)($h['field'] ?? ''),
                'reason' => (string)($h['reason'] ?? ''),
                'meta' => is_array($h['meta'] ?? null) ? $h['meta'] : [],
            ];
        }
        return $out;
    }

    /**
     * Безопасный лог: email — только hash; реквизиты не пишем.
     *
     * @param array<int,array<string,mixed>> $hits
     */
    private function safeLog(ValidationContext $context, string $mode, string $type, array $hits): void
    {
        $email = $context->env->email();

        Log::info('Blacklist rule triggered', [
            'mode' => $mode,
            'type' => $type,
            'ip' => $context->env->ip(),
            'email_hash' => $email ? sha1(mb_strtolower($email)) : null,
            'hits' => $this->safeHits($hits),
        ]);
    }
}
