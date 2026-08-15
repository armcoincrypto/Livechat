<?php

declare(strict_types=1);

namespace App\Services\Reserves;

use App\Models\Reserve;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ReserveLinkResolver
 *
 * Сервис чтения цепочек резервов.
 *
 * ## Ключевой принцип
 * Корень цепочки определяется ТОЛЬКО через `reserve_closures`:
 * - root_id = ancestor_id на максимальной глубине MAX(depth) для descendant_id
 *
 * Это важно, потому что запись в `reserve_links` может отсутствовать у корневых резервов.
 */
final class ReserveLinkResolver
{
    /**
     * Получить ID корневого резерва для указанного резерва.
     *
     * Логика:
     * - корень = ancestor с максимальной глубиной в closure table
     * - если данных нет — корень = сам reserveId
     *
     * @param int $reserveId
     *
     * @return int
     */
    public function getRootId(int $reserveId): int
    {
        $row = DB::table('reserve_closures')
            ->where('descendant_id', $reserveId)
            ->orderByDesc('depth')
            ->select('ancestor_id')
            ->first();

        return (int) (($row->ancestor_id ?? null) ?: $reserveId);
    }

    /**
     * Получить модель корневого резерва.
     *
     * @param Reserve $reserve
     *
     * @return Reserve
     */
    public function getRoot(Reserve $reserve): Reserve
    {
        $rootId = $this->getRootId((int) $reserve->id);

        if ($rootId === (int) $reserve->id) {
            return $reserve;
        }

        return Reserve::query()->findOrFail($rootId);
    }

    /**
     * Проверить, привязан ли резерв к другому (имеет parent).
     *
     * @param int $reserveId
     *
     * @return bool
     */
    public function isLinked(int $reserveId): bool
    {
        return DB::table('reserve_links')
            ->where('reserve_id', $reserveId)
            ->whereNotNull('parent_reserve_id')
            ->exists();
    }

    /**
     * Проверить, является ли резерв корневым.
     *
     * Важно:
     * - если записи в reserve_links нет — считаем корнем (это нормальная ситуация)
     *
     * @param int $reserveId
     *
     * @return bool
     */
    public function isRoot(int $reserveId): bool
    {
        $pid = DB::table('reserve_links')
            ->where('reserve_id', $reserveId)
            ->value('parent_reserve_id');

        return $pid === null;
    }

    /**
     * Получить цепочку предков (вверх), без самого резерва.
     *
     * Формат:
     * - depth=1,2,3... (от ближайшего предка к корню)
     *
     * @param int $reserveId
     *
     * @return array<int,int>
     */
    public function getAncestorIds(int $reserveId): array
    {
        return DB::table('reserve_closures')
            ->where('descendant_id', $reserveId)
            ->where('depth', '>', 0)
            ->orderBy('depth')
            ->pluck('ancestor_id')
            ->map(static fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Получить цепочку предков как модели Reserve (в порядке depth).
     *
     * @param int $reserveId
     *
     * @return EloquentCollection<int, Reserve>
     */
    public function getAncestors(int $reserveId): EloquentCollection
    {
        $ids = $this->getAncestorIds($reserveId);

        if (empty($ids)) {
            return new EloquentCollection();
        }

        /** @var EloquentCollection<int, Reserve> $items */
        $items = Reserve::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $ordered = [];
        foreach ($ids as $id) {
            if ($items->has($id)) {
                $ordered[] = $items->get($id);
            }
        }

        return new EloquentCollection($ordered);
    }

    /**
     * Получить потомков (ID), без самого резерва.
     *
     * @param int $reserveId
     *
     * @return array<int,int>
     */
    public function getDescendantIds(int $reserveId): array
    {
        return DB::table('reserve_closures')
            ->where('ancestor_id', $reserveId)
            ->where('depth', '>', 0)
            ->orderBy('depth')
            ->pluck('descendant_id')
            ->map(static fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Получить количество потомков (без самого резерва).
     *
     * @param int $reserveId
     *
     * @return int
     */
    public function getDescendantsCount(int $reserveId): int
    {
        return (int) DB::table('reserve_closures')
            ->where('ancestor_id', $reserveId)
            ->where('depth', '>', 0)
            ->count();
    }

    /**
     * Размер группы корня (корень + все потомки, включая self depth=0).
     *
     * @param int $reserveId
     *
     * @return int
     */
    public function getGroupSize(int $reserveId): int
    {
        $rootId = $this->getRootId($reserveId);

        return (int) DB::table('reserve_closures')
            ->where('ancestor_id', $rootId)
            ->count();
    }

    /**
     * Эффективная сумма резерва (что реально используется системой).
     *
     * Правило:
     * - берём `summa` у корневого резерва
     * - возвращаем строку (без float, без scientific notation)
     *
     * @param Reserve $reserve
     * @param int $scale
     *
     * @return string
     */
    public function getEffectiveSumma(Reserve $reserve, int $scale = 18): string
    {
        $root = $this->getRoot($reserve);
        $val = (string) ($root->summa ?? '0');

        return function_exists('iex_money_normalize')
            ? iex_money_normalize($val, $scale)
            : $val;
    }

    /**
     * Метаданные для UI.
     *
     * @param int $reserveId
     *
     * @return array{
     *   is_linked: bool,
     *   is_root: bool,
     *   root_id: int,
     *   group_size: int,
     *   descendants_count: int
     * }
     */
    public function getUiMeta(int $reserveId): array
    {
        $rootId = $this->getRootId($reserveId);

        return [
            'is_linked' => $this->isLinked($reserveId),
            'is_root' => $this->isRoot($reserveId),
            'root_id' => $rootId,
            'group_size' => $this->getGroupSize($reserveId),
            'descendants_count' => $this->getDescendantsCount($reserveId),
        ];
    }

    /**
     * Диагностика closure: проверяем self-связь depth=0.
     *
     * @param int $reserveId
     *
     * @return void
     *
     * @throws RuntimeException если self-связь отсутствует.
     */
    public function assertClosureReady(int $reserveId): void
    {
        $exists = DB::table('reserve_closures')
            ->where('ancestor_id', $reserveId)
            ->where('descendant_id', $reserveId)
            ->where('depth', 0)
            ->exists();

        if (!$exists) {
            throw new RuntimeException('reserve_closures не содержит self-связь для резерва: ' . $reserveId);
        }
    }
}
