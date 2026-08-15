<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Http;

use App\Models\ProxyModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

/**
 * ProxyHealthService
 *
 * Корректное здоровье прокси:
 * - НЕ увеличиваем fail_count по 401/403/429 (это не ошибка прокси)
 * - увеличиваем по ConnectionException и 5xx
 */
final class ProxyHealthService
{
    public function markResult(?ProxyModel $proxy, bool $success, ?int $httpStatus, ?\Throwable $error, string $alias = 'BestChange'): void
    {
        if (!$proxy) return;

        if ($success) {
            $this->markSuccess($proxy);
            return;
        }

        if (!$this->shouldCountAsFail($httpStatus, $error)) {
            $proxy->last_checked_at = now();
            $proxy->save();
            return;
        }

        $this->markFail($proxy, $alias);
    }

    private function shouldCountAsFail(?int $status, ?\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) return true;
        if ($status === null) return true;

        if (in_array($status, [400,401,403,404,422,429], true)) return false;
        if ($status >= 500) return true;

        return false;
    }

    private function markSuccess(ProxyModel $proxy): void
    {
        if ($proxy->fail_count !== 0) {
            $proxy->fail_count = 0;
        }
        $proxy->last_checked_at = now();
        $proxy->save();
    }

    private function markFail(ProxyModel $proxy, string $alias): void
    {
        try {
            ProxyModel::whereKey($proxy->id)->increment('fail_count');
            $proxy->refresh();
        } catch (\Throwable) {
            $proxy->fail_count++;
        }

        $proxy->last_checked_at = now();

        $threshold = 3;
        if ($proxy->fail_count >= $threshold && $proxy->status != 0) {
            $proxy->status = 0;
            Log::warning("Auto-disable proxy (fail_count={$proxy->fail_count}) id={$proxy->id} alias={$alias}");
        }

        $proxy->save();
    }
}
