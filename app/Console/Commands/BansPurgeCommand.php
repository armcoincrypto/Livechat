<?php

namespace App\Console\Commands;

use App\Models\Banned;
use Illuminate\Console\Command;

class BansPurgeCommand extends Command
{
    protected $signature = 'bans:purge
        {--expired : Удалить только истёкшие (expired_at < now())}
        {--older-than-days= : Доп. фильтр: удалить истёкшие, которые истекли N+ дней назад}
        {--type= : Фильтр по типу (ip|cidr|email|domain)}
        {--q= : Поиск по filter_name/filter_key}
        {--limit=5000 : Максимум записей за запуск}
        {--dry-run : Только показать количество, ничего не удалять}';

    protected $description = 'Удаляет записи из таблицы banned по условиям (без архива).';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 1) $limit = 5000;
        if ($limit > 200000) $limit = 200000;

        $query = Banned::query()->orderBy('id');

        // type
        $type = $this->option('type');
        if (is_string($type) && $type !== '') {
            $query->where('type', $type);
        }

        // search
        $q = $this->option('q');
        if (is_string($q) && trim($q) !== '') {
            $q = trim($q);
            $qLower = mb_strtolower($q);

            $query->where(function ($w) use ($q, $qLower) {
                $w->where('filter_name', 'like', '%' . $q . '%')
                    ->orWhere('filter_key', 'like', '%' . $qLower . '%');
            });
        }

        // expired filter
        $expired = (bool) $this->option('expired');
        $olderDays = $this->option('older-than-days');

        if ($expired) {
            $query->where('expired_at', '<', now());

            if ($olderDays !== null && $olderDays !== '') {
                $days = (int) $olderDays;
                if ($days < 0) $days = 0;

                $query->where('expired_at', '<', now()->subDays($days));
            }
        }

        // limit (delete in chunks)
        $ids = $query->limit($limit)->pluck('id');

        $count = $ids->count();
        if ($count === 0) {
            $this->info('Нечего удалять.');
            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->info("DRY RUN: найдено к удалению: {$count}");
            return self::SUCCESS;
        }

        $deleted = Banned::query()->whereIn('id', $ids)->delete();

        $this->info("Удалено записей: {$deleted}");

        return self::SUCCESS;
    }
}
