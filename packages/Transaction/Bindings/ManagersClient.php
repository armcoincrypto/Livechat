<?php

namespace iEXPackages\Transaction\Bindings;

use App\Models\BannedUser;
use App\Models\BlacklistOrder;
use iEXPackages\SmartMailer\Facades\SmartMailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait ManagersClient
{
    /**
     * Забанить клиента в случае подозрительных действий
     */
    public function clientBan(array $attributes = []): void
    {
        // Считаем, какой по счёту это бан и логируем факт блокировки
        $banCount = $this->clientBanLog();

        // Определяем дату окончания бана на основе настроек ступеней
        $expiredAt = $this->resolveClientBanExpiration($banCount);

        $client = $this->getClient();

        $client->ban([
            'expired_at' => $expiredAt->toDateTimeString(),
            'comment'    => $attributes['comment'] ?? null,
        ]);

        $client->forceFill([
            'banned_at' => $expiredAt->toDateTimeString(),
        ])->save();
    }

    /**
     * Определяет дату окончания бана на основе количества банов и настроек системы.
     *
     * Используются настройки:
     * - scam_ban_step1_minutes — длительность первого бана в минутах
     * - scam_ban_step2_hours   — длительность второго бана в часах
     * - scam_ban_step3_days    — длительность третьего и последующих банов в днях (0 = перманентный)
     */
    private function resolveClientBanExpiration(int $banCount): Carbon
    {
        $now = Carbon::now();

        // Читаем значения из настроек; если что-то не задано — используем безопасные дефолты
        $step1Minutes = (int) iEXSetting('scam_ban_step1_minutes');
        $step2Hours   = (int) iEXSetting('scam_ban_step2_hours');
        $step3Days    = (int) iEXSetting('scam_ban_step3_days');

        if ($step1Minutes <= 0) {
            // по умолчанию 1 день в минутах
            $step1Minutes = 60 * 24;
        }

        if ($step2Hours <= 0) {
            // по умолчанию 14 дней в часах
            $step2Hours = 24 * 14;
        }

        if ($step3Days < 0) {
            // по умолчанию ~10 лет (почти перманентный бан)
            $step3Days = 3650;
        }

        if ($banCount <= 1) {
            // Первый бан
            return $now->copy()->addMinutes($step1Minutes);
        }

        if ($banCount === 2) {
            // Второй бан
            return $now->copy()->addHours($step2Hours);
        }

        // Третий и последующие баны
        if ($step3Days === 0) {
            // 0 = перманентный бан — используем большую длительность как замену
            return $now->copy()->addYears(10);
        }

        return $now->copy()->addDays($step3Days);
    }

    /**
     * Записываем каждого забаненного клиента
     */
    private function clientBanLog(): int
    {
        BannedUser::create([
            'id_user' => $this->getClient()->id,
            'id_task' => $this->transaction->id,
        ]);

        return BannedUser::where('id_user', $this->getClient()->id)->count();
    }

    /**
     * Добавить реквизиты клиента в черный список
     */
    public function clientToBlackList(): void
    {
        $text = sprintf('Заявка №%s от клиента была признана мошеннической.', $this->transaction->id);

        $contacts = collect([
            'Email' => $this->getClient()->email,
            'IP' => $this->transaction->ip,
            'Отправитель' => $this->transaction->from_shot ? preg_replace('/\s+/', '', $this->transaction->from_shot) : null,
            'Получатель' => $this->transaction->to_shot ? preg_replace('/\s+/', '', $this->transaction->to_shot) : null,
        ])->filter()->map(fn($value, $key) => "{$key}: {$value}")->implode(PHP_EOL);


        // Добавляем данные в черный список
        BlacklistOrder::create([
            'value' => $contacts,
            'text' => $text,
            'type' => 3,
        ]);
    }

    /**
     * Добавить данные клиента в черный список
     *
     * @param bool $unban
     * @return void
     */
    public function orderDataBan(bool $unban = false): void
    {
        // Разблокируем данные клиента
        if($unban) {
            $this->transaction->update([
                'is_ban_order_data' => 0
            ]);
            BlacklistOrder::where('id_task', $this->transaction->id)->delete();

        } else {
            $text = sprintf('Данные клиента по заявке  №%s заблокированы.', $this->transaction->id);

            $contacts = 'Email: '.$this->getClient()->email.PHP_EOL;
            $contacts .= 'IP: '.$this->transaction->ip.PHP_EOL;

            if (! is_null($this->transaction->from_shot)) {
                $contacts .= 'Номер счета: '.preg_replace('/\s+/', '', $this->transaction->from_shot).PHP_EOL;
            }
            if (! is_null($this->transaction->to_shot)) {
                $contacts .= 'Номер счета: '.preg_replace('/\s+/', '', $this->transaction->to_shot).PHP_EOL;
            }

            if($this->transaction->is_ban_order_data == 0)
            {
                // Добавляем IP, Email в черный список
                BlacklistOrder::updateOrInsert([
                    'id_task' => $this->transaction->id,
                ], [
                    'value' => $contacts,
                    'text' => $text,
                    'type' => 3,
                    'id_task' => $this->transaction->id,
                    'created_at' => Carbon::now()->format('c'),
                    'updated_at' => Carbon::now()->format('c'),
                ]);

                // Уведомлениям клиента о том, что его данные находятся в черном списке
                if ((int)iEXSetting('is_order_shot_black_list') == 1)
                {
                    SmartMailer::dispatch(
                        sendable: 'order_shot_blacklist_job',
                        model: $this->transaction,
                        delaySeconds: 10,
                        queue: 'low'
                    );
                }

                $this->transaction->update(['is_ban_order_data' => 1]);
            }
        }
    }

    /**
     * Обновляет агрегированную статистику клиента по заявкам:
     * - увеличивает количество заявок (order_num)
     * - увеличивает общую сумму обменов в USD (order_total_exchanges)
     */
    public function updateClientExchangeStats(string $amountUsd): void
    {
        $client = $this->getClient();
        if (!$client) {
            return;
        }

        $amountUsd = str_replace(',', '.', trim($amountUsd));
        if ($amountUsd === '' || !is_numeric($amountUsd) || bccomp($amountUsd, '0', 8) <= 0) {
            return;
        }

        DB::transaction(function () use ($client, $amountUsd) {
            $locked = $client->newQuery()
                ->whereKey($client->getKey())
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return;
            }

            $currentTotal = (string) ($locked->order_total_exchanges ?? '0');
            $newTotal = bcadd($currentTotal, $amountUsd, 8);

            $locked->forceFill([
                'order_num' => (int) ($locked->order_num ?? 0) + 1,
                'order_total_exchanges' => $newTotal,
            ])->save();
        });
    }

    /**
     * Баним клиента в случае если система определения заявку как мошенническую
     *
     * @throws \Throwable
     */
    public function autoBanClientForAutoPayment(): void
    {
        if ((int) iEXSetting('ban_cheater_user') == 1) {
            // Категория, мошенническая заявка
            $this->setCategoryReject(4);
            $this->reject();
        }
    }
}
