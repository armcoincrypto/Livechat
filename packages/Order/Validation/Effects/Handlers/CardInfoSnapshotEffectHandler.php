<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Effects\Handlers;

use App\Models\Task;
use App\Models\TaskCardDetail;
use iEXPackages\Order\Validation\Enums\EffectType;
use Illuminate\Support\Facades\Log;

/**
 * CardInfoSnapshotEffectHandler
 *
 * - сохраняет snapshot в tasks_meta.card_info (если хочешь)
 * - пишет детали карты в TaskCardDetail
 *
 * Идемпотентность:
 * - перед созданием TaskCardDetail проверяем наличие записи по (task_id,type_column,card_number)
 */
final class CardInfoSnapshotEffectHandler implements EffectHandlerInterface
{
    public function supports(EffectType $type): bool
    {
        return $type === EffectType::CardInfoSnapshot;
    }

    public function handle(Task $order, array $payload): void
    {
//        // 1) meta snapshot (опционально)
//        if (method_exists($order, 'meta')) {
//            try {
//                $order->meta()->updateOrCreate([], [
//                    'card_info' => [
//                        'in'  => is_array($payload['in'] ?? null) ? ($payload['in'] ?? []) : [],
//                        'out' => is_array($payload['out'] ?? null) ? ($payload['out'] ?? []) : [],
//                        'at'  => now()->toISOString(),
//                    ],
//                ]);
//            } catch (\Throwable $e) {
//                Log::warning('CardInfoSnapshot meta write failed: ' . $e->getMessage(), [
//                    'order_id' => $order->id,
//                ]);
//            }
//        }

        // 2) TaskCardDetail
        $this->storeCardDetails($order, 'in', $payload['in'] ?? null);
        $this->storeCardDetails($order, 'out', $payload['out'] ?? null);
    }

    private function storeCardDetails(Task $order, string $type, array|string|null $cardInfo): void
    {
        if (!is_array($cardInfo) || $cardInfo === []) {
            return;
        }

        $cardNumber = match ($type) {
            'in'  => $order->from_shot,
            'out' => $order->to_shot,
            default => '',
        };

        if ($cardNumber === '' || $cardNumber === null) {
            Log::warning("CardInfoSnapshot: missing card number for '{$type}'", [
                'order_id' => $order->id,
            ]);
            return;
        }

        // идемпотентность: не плодим дубли
        $exists = TaskCardDetail::query()
            ->where('id_task', $order->id)
            ->where('type_column', $type)
            ->where('card_number', $cardNumber)
            ->exists();

        if ($exists) {
            return;
        }

        TaskCardDetail::create([
            'type_column'      => $type,
            'id_task'          => $order->id,
            'card_number'      => $cardNumber,
            'payment_system'   => $cardInfo['paymentSystem'] ?? null,
            'type_card'        => $cardInfo['typeName'] ?? null,
            'brand_card'       => $cardInfo['brandName'] ?? null,
            'country_name'     => $cardInfo['countryName'] ?? null,
            'country_currency' => $cardInfo['currencyName'] ?? null,
            'bank_name'        => $cardInfo['bankName'] ?? null,
            'bank_url'         => $cardInfo['website'] ?? null,
            'bank_phone'       => $cardInfo['phone'] ?? null,
        ]);
    }
}
