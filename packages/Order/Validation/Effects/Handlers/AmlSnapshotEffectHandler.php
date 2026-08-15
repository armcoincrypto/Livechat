<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Effects\Handlers;

use App\Models\AMLResponseData;
use App\Models\Task;
use iEXPackages\Order\Validation\Enums\EffectType;
use Illuminate\Support\Facades\Log;

/**
 * AmlSnapshotEffectHandler
 *
 * Записывает результат AML в AMLResponseData.
 *
 * Идемпотентность:
 * - если запись для task+method уже есть — обновляем её, не создаём дубликат
 */
final class AmlSnapshotEffectHandler implements EffectHandlerInterface
{
    public function supports(EffectType $type): bool
    {
        return $type === EffectType::AmlSnapshot;
    }

    public function handle(Task $order, array $payload): void
    {
        $serviceId = (int)($payload['id_aml_service'] ?? 0);
        $alias = trim((string)($payload['alias'] ?? ''));
        $method = trim((string)($payload['method'] ?? 'address'));

        $ext = $payload['ext_params'] ?? null;

        if ($serviceId <= 0 || $alias === '' || $ext === null) {
            return;
        }

        // normalize ext_params
        if (is_string($ext)) {
            $decoded = json_decode($ext, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $ext = $decoded;
            } else {
                $ext = ['raw' => $ext];
            }
        } elseif (is_object($ext)) {
            $ext = (array)$ext;
        } elseif (!is_array($ext)) {
            $ext = ['value' => $ext];
        }

        try {
            $q = AMLResponseData::query()
                ->where('id_task', $order->id)
                ->where('method', $method);

            if ($q->exists()) {
                $q->update([
                    'id_aml_service' => $serviceId,
                    'alias'          => $alias,
                    'ext_params'     => $ext,
                ]);
                return;
            }

            AMLResponseData::create([
                'id_task'        => $order->id,
                'id_aml_service' => $serviceId,
                'alias'          => $alias,
                'ext_params'     => $ext,
                'method'         => $method,
            ]);
        } catch (\Throwable $e) {
            Log::error('AmlSnapshotEffectHandler failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
            ]);
        }
    }
}
