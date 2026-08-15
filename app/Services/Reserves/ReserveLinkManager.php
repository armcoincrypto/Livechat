<?php

declare(strict_types=1);

namespace App\Services\Reserves;

use App\Models\Reserve;
use App\Models\ReserveLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ReserveLinkManager
 *
 * Управление связями резервов по новой схеме (цепочки любой длины).
 *
 * ## Используемые таблицы
 * - `reserve_links` — хранит прямую связь (parent) для каждого резерва.
 * - `reserve_closures` — closure table (ancestor/descendant/depth) для быстрых запросов:
 *   - поиск корня цепочки;
 *   - проверка циклов;
 *   - получение предков/потомков;
 *   - построение дерева.
 *
 * ## Гарантии и правила
 * - Нельзя привязать резерв к самому себе.
 * - Нельзя создать цикл: parent не может быть потомком child.
 * - После изменения связи корректно обновляется closure table для всего поддерева child.
 *
 * ## Примечание по данным
 * - Запись в `reserve_links` может отсутствовать для корневых резервов — это допустимо.
 * - Корень цепочки в системе определяется через `reserve_closures` (MAX(depth)).
 */
final class ReserveLinkManager
{
    /**
     * Привязать child-резерв к parent-резерву.
     *
     * ## Что делает метод
     * 1) Проверяет, что child != parent.
     * 2) Проверяет существование резервов.
     * 3) Проверяет защиту от цикла (parent не должен быть потомком child).
     * 4) Пишет прямую связь в `reserve_links` (parent_reserve_id).
     * 5) Перестраивает `reserve_closures` для поддерева child:
     *    - удаляет внешних предков над поддеревом;
     *    - добавляет новых предков от parent для всех потомков child.
     *
     * ## Важно
     * - Метод работает в транзакции.
     * - Self-связи (`depth=0`) гарантируются insertOrIgnore.
     *
     * @param int $childId ID резерва-потомка.
     * @param int $parentId ID резерва-родителя.
     * @param string|null $note Примечание к связи (для UI/аудита).
     *
     * @return void
     *
     * @throws RuntimeException
     *   - если child == parent;
     *   - если child/parent не существует;
     *   - если попытка создать цикл связей.
     */
    public function attach(int $childId, int $parentId, ?string $note = null): void
    {
        if ($childId === $parentId) {
            throw new RuntimeException('Нельзя привязать резерв сам к себе.');
        }

        DB::transaction(function () use ($childId, $parentId, $note): void {
            if (!Reserve::query()->whereKey($childId)->exists()) {
                throw new RuntimeException('Резерв (child) не найден: ' . $childId);
            }
            if (!Reserve::query()->whereKey($parentId)->exists()) {
                throw new RuntimeException('Резерв (parent) не найден: ' . $parentId);
            }

            // Гарантируем self-связи (на случай если closures не заполнены)
            DB::table('reserve_closures')->insertOrIgnore([
                ['ancestor_id' => $childId, 'descendant_id' => $childId, 'depth' => 0],
                ['ancestor_id' => $parentId, 'descendant_id' => $parentId, 'depth' => 0],
            ]);

            // Защита от цикла: parent не должен быть потомком child
            $cycle = DB::table('reserve_closures')
                ->where('ancestor_id', $childId)
                ->where('descendant_id', $parentId)
                ->exists();

            if ($cycle) {
                throw new RuntimeException('Нельзя создать цикл связей резервов.');
            }

            // Сохраняем прямую связь
            ReserveLink::query()->updateOrCreate(
                ['reserve_id' => $childId],
                [
                    'parent_reserve_id' => $parentId,
                    'is_active' => true,
                    'note' => $note,
                ]
            );

            // Поддерево child (включая self)
            $descendants = $this->getDescendantsWithDepth($childId);

            /** @var array<int,int> $descendantIds */
            $descendantIds = $descendants
                ->pluck('descendant_id')
                ->map(static fn ($v) => (int) $v)
                ->all();

            // Удаляем старые внешние предки над поддеревом child
            DB::table('reserve_closures')
                ->whereIn('descendant_id', $descendantIds)
                ->whereNotIn('ancestor_id', $descendantIds)
                ->delete();

            // Предки parent (включая self)
            $parentAncestors = $this->getAncestorsWithDepth($parentId);

            // Добавляем новые связи: (предки parent) × (потомки child)
            $insert = [];

            foreach ($parentAncestors as $pa) {
                $paId = (int) $pa->ancestor_id;
                $paDepth = (int) $pa->depth;

                foreach ($descendants as $cd) {
                    $dId = (int) $cd->descendant_id;
                    $dDepth = (int) $cd->depth;

                    $insert[] = [
                        'ancestor_id' => $paId,
                        'descendant_id' => $dId,
                        'depth' => $paDepth + 1 + $dDepth,
                    ];
                }
            }

            if (!empty($insert)) {
                DB::table('reserve_closures')->insertOrIgnore($insert);
            }

            // Гарантируем self-связь для child (на всякий случай)
            DB::table('reserve_closures')->insertOrIgnore([
                'ancestor_id' => $childId,
                'descendant_id' => $childId,
                'depth' => 0,
            ]);
        });
    }

    /**
     * Отвязать резерв от родителя (сделать его корнем).
     *
     * ## Что делает метод
     * - Обнуляет `parent_reserve_id` в `reserve_links`.
     * - Удаляет внешних предков над поддеревом резерва:
     *   т.е. оставляет связи только внутри поддерева.
     *
     * ## Важно
     * - Потомки резерва остаются под ним.
     * - Метод работает в транзакции.
     *
     * @param int $reserveId ID резерва.
     *
     * @return void
     *
     * @throws RuntimeException если резерв не существует.
     */
    public function detach(int $reserveId): void
    {
        DB::transaction(function () use ($reserveId): void {
            if (!Reserve::query()->whereKey($reserveId)->exists()) {
                throw new RuntimeException('Резерв не найден: ' . $reserveId);
            }

            ReserveLink::query()->updateOrCreate(
                ['reserve_id' => $reserveId],
                [
                    'parent_reserve_id' => null,
                    'is_active' => true,
                ]
            );

            $descendantIds = $this->getDescendantIds($reserveId);

            // Гарантируем self
            if (empty($descendantIds)) {
                DB::table('reserve_closures')->insertOrIgnore([
                    'ancestor_id' => $reserveId,
                    'descendant_id' => $reserveId,
                    'depth' => 0,
                ]);
                $descendantIds = [$reserveId];
            } elseif (!in_array($reserveId, $descendantIds, true)) {
                $descendantIds[] = $reserveId;
            }

            // Удаляем внешних предков над поддеревом reserveId
            DB::table('reserve_closures')
                ->whereIn('descendant_id', $descendantIds)
                ->whereNotIn('ancestor_id', $descendantIds)
                ->delete();
        });
    }

    /**
     * Получить потомков резерва (включая self) с глубиной.
     *
     * Если closure не содержит self-связи — создаём её.
     *
     * @param int $reserveId
     *
     * @return Collection<int, object{descendant_id:int, depth:int}>
     */
    private function getDescendantsWithDepth(int $reserveId): Collection
    {
        $rows = DB::table('reserve_closures')
            ->select(['descendant_id', 'depth'])
            ->where('ancestor_id', $reserveId)
            ->orderBy('depth')
            ->get();

        if ($rows->isEmpty()) {
            DB::table('reserve_closures')->insertOrIgnore([
                'ancestor_id' => $reserveId,
                'descendant_id' => $reserveId,
                'depth' => 0,
            ]);

            return collect([(object) ['descendant_id' => $reserveId, 'depth' => 0]]);
        }

        return $rows;
    }

    /**
     * Получить предков резерва (включая self) с глубиной.
     *
     * Если closure не содержит self-связи — создаём её.
     *
     * @param int $reserveId
     *
     * @return Collection<int, object{ancestor_id:int, depth:int}>
     */
    private function getAncestorsWithDepth(int $reserveId): Collection
    {
        $rows = DB::table('reserve_closures')
            ->select(['ancestor_id', 'depth'])
            ->where('descendant_id', $reserveId)
            ->orderBy('depth')
            ->get();

        if ($rows->isEmpty()) {
            DB::table('reserve_closures')->insertOrIgnore([
                'ancestor_id' => $reserveId,
                'descendant_id' => $reserveId,
                'depth' => 0,
            ]);

            return collect([(object) ['ancestor_id' => $reserveId, 'depth' => 0]]);
        }

        return $rows;
    }

    /**
     * Получить список потомков (ID) включая self.
     *
     * @param int $reserveId
     *
     * @return array<int,int>
     */
    private function getDescendantIds(int $reserveId): array
    {
        return DB::table('reserve_closures')
            ->where('ancestor_id', $reserveId)
            ->pluck('descendant_id')
            ->map(static fn ($v) => (int) $v)
            ->all();
    }
}
