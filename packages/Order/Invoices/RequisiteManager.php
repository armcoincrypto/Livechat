<?php
declare(strict_types=1);

namespace iEXPackages\Order\Invoices;


use App\Models\Currency;
use App\Models\GatewayMerchant;
use App\Models\MerchantTransactionData;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * RequisiteManager — единая точка получения реквизитов по заявке.
 *
 * Как это работает (простыми словами):
 *  1) Если для направления/валюты подключён мерчант, мы запрашиваем реквизиты у него.
 *     Если мерчант выбран, но не вернул данные — показываем, что реквизиты ещё не готовы
 *     (и не подменяем их ручными).
 *  2) Если мерчантов нет, и в заявке включён режим «по запросу», отдаём текст из заявки.
 *  3) Если и это не подходит — берём ручные реквизиты: сначала из направления, затем из валюты.
 *
 * Важно: никакого кеширования и блокировок — только прямые обращения. Это делает поведение
 * предсказуемым и одинаковым на любом количестве серверов (масштабирование без сюрпризов).
 */
class RequisiteManager
{
    use Concerns\ManagesAttributes,
        Concerns\HasMerchant,
        Concerns\HasManual;

    /**
     * Данные по валюте
     *
     * @var Currency
     */
    protected Currency $currencyIn;

    /**
     * Данные по заявке
     *
     * @var Task
     */
    protected Task $task;

    /**
     * Информация о мерчанте
     *
     * @var mixed
     */
    protected mixed $merchantPay = [];

    /**
     * Подключаем заявку
     *
     * @param Task $task
     * @return static
     */
    public function make(Task $task): static
    {
        $this->task = $task;
        return $this;
    }

    /**
     * Вернёт реквизиты для этой заявки в приоритете: мерчант → по запросу → вручную.
     *
     * Возвращает:
     *  - массив с реквизитами (кошелёк/счёт/банк) или ссылку checkout от мерчанта;
     *  - либо строку "Не определен", если данных пока нет.
     *
     * Примечание: если мерчант подключён, но ещё не выдал реквизиты, мы не подменяем
     * их ручными. Это защищает от ошибок при оплате и дублировании счётов.
     */
    public function get(): mixed
    {
        $r = $this->resolveFromMerchant();


        if ($r instanceof RequisitesResult) {
            // Мерчант — финальная точка (даже если data === null)
            return $r->data ?? __('Не определен');
        }

        $r = $this->resolveFromRequest();
        if ($r instanceof RequisitesResult) {
            return $r->data ?? __('Не определен');
        }

        $r = $this->resolveFromManual();
        return $r->data ?? __('Не определен');
    }


    /**
     * Источник №1 — мерчант (приоритет: направление → валюта).
     *
     * Если по заявке уже сохранены данные мерчанта — сразу возвращаем их (без повторных запросов).
     * Иначе — один раз пробуем инициализировать у мерчанта и снова читаем из БД.
     * Если так и не появилось — возвращаем RequisitesResult::merchant(null) — это финальная точка.
     */
    private function resolveFromMerchant(): ?RequisitesResult
    {
        // Связанные данные валюты и направления
        $direction = $this->task->direction_exchange;
        if (!$direction) {
            Log::warning('У заявки отсутствует связь direction_exchange', ['task_id' => $this->task->id]);
            return null;
        }

        $this->currencyIn = $direction->currency1;

        // Догружаем недостающие связи один раз
        $this->task->loadMissing([
            'direction_exchange.merchants',
            'direction_exchange.direction_requisites',
        ]);
        $this->currencyIn?->loadMissing('merchants');

        // Проверяем активных мерчантов (направление → валюта)
        $directionMerchants = $direction?->merchants?->where('status', 1) ?? collect();
        $currencyMerchants  = $this->currencyIn?->merchants?->where('status', 1) ?? collect();
        $hasMerchant = $directionMerchants->isNotEmpty() || $currencyMerchants->isNotEmpty();
        if (!$hasMerchant) {
            return null; // передаём управление следующему источнику
        }

        // Если в БД уже есть MTD — используем его (без повторных обращений)
        $existing = MerchantTransactionData::where('id_task', $this->task->id)->first();


        if ($existing) {
            return RequisitesResult::merchant($this->formatMerchantRequisites($existing));
        }

        // Пытаемся получить реквизиты у мерчанта (без кешей/локов)
        try {
            $this->resolveMerchantAccountWithHandling();
        } catch (\Throwable $e) {
            Log::error('Ошибка при инициализации мерчанта', [
                'task_id' => $this->task->id,
                'message' => $e->getMessage(),
            ]);
        }

        // Повторно читаем MTD
        $mtd = MerchantTransactionData::where('id_task', $this->task->id)->first();
        if ($mtd) {
            return RequisitesResult::merchant($this->formatMerchantRequisites($mtd));
        }

        // Мерчант активен, но ничего не выдал — стоп, ручные не используем
        Log::warning('Мерчант выбран, но реквизиты не выданы', [
            'task_id'      => $this->task->id,
            'direction_id' => $direction->id ?? null,
            'currency_id'  => $this->currencyIn->id ?? null,
        ]);
        return RequisitesResult::merchant(null);
    }

    /**
     * Источник №2 — «по запросу».
     *
     * Работает, только если включён режим и текст действительно заполнен.
     * Приоритет флага включения:
     *   1) направление (direction_exchange.method_request_payment)
     *   2) валюта currency1.method_request_payment
     */
    private function resolveFromRequest(): ?RequisitesResult
    {
        $direction = $this->task->direction_exchange;
        if ($direction && !isset($this->currencyIn)) {
            $this->currencyIn = $direction->currency1;
        }

        $text = is_string($this->task->requisites_receive ?? null)
            ? trim($this->task->requisites_receive)
            : '';

        if ($text === '') {
            return null;
        }

        // 1. Приоритет за направлением
        $directionFlag = $direction ? (int)($direction->method_request_payment ?? 0) : 0;
        if ($directionFlag === 1) {
            return RequisitesResult::request([$text]);
        }

        // 2. Если в направлении не включено, смотрим на валюту
        $currencyFlag = isset($this->currencyIn)
            ? (int)($this->currencyIn->method_request_payment ?? 0)
            : 0;

        if ($currencyFlag === 1) {
            return RequisitesResult::request([$text]);
        }

        return null;
    }

    /**
     * Источник №3 — вручную.
     * Сначала пробуем реквизиты, настроенные в направлении (с учётом типа выдачи),
     * если не получилось — берём из валюты. Данные приводятся к единому формату.
     */
    private function resolveFromManual(): RequisitesResult
    {
        $direction = $this->task->direction_exchange;
        if ($direction && !isset($this->currencyIn)) {
            $this->currencyIn = $direction->currency1;
        }

        // 3.1 Ручной счёт указан прямо в заявке
        if ($this->task->transfer_to_account_type === 'manual' && !empty($this->task->transfer_to_account)) {
            return RequisitesResult::manual($this->normalizeManualRequisites($this->task->transfer_to_account));
        }

        // 3.2 Приоритет — реквизиты из направления
        $directionRequisites = optional($direction)
            ->direction_requisites
            ?->where('status', 1);

        if ($directionRequisites?->isNotEmpty()) {
            $requisite = match ((int)($direction->type_output_requisites ?? 0)) {
                1 => $directionRequisites->random(),
                2 => $this->getCachedDirectionRequisite('daily'),
                3 => $this->getCachedDirectionRequisite('monthly'),
                default => $directionRequisites->first(),
            };

            if ($requisite) {
                $data = $this->getDirectionExchangeManual(collect([$requisite])) ?: [];

                $data = $this->normalizeManualRequisites($data);
                if (!empty($data)) {
                    return RequisitesResult::manual($data);
                }
            }

            // Фолбэк: берём реквизиты из валюты
            $fallback = $this->getManualWallet() ?: [];
            return RequisitesResult::manual($this->normalizeManualRequisites($fallback));
        }

        // Фолбэк: берём реквизиты из валюты
        $data = $this->getManualWallet() ?: [];
        return RequisitesResult::manual($this->normalizeManualRequisites($data));
    }


    /**
     * Детерминированный выбор реквизита направления без кеша:
     *  - daily   — стабильный выбор в пределах дня;
     *  - monthly — стабильный выбор в пределах месяца.
     * Это даёт ровное распределение без хранения состояния в БД/кеше.
     */
    protected function getCachedDirectionRequisite(string $period): mixed
    {
        // Загружаем связанные реквизиты для направления
        $this->task->loadMissing('direction_exchange.direction_requisites');

        $directionExchange = $this->task->direction_exchange;
        if (!$directionExchange || $directionExchange->direction_requisites->isEmpty()) {
            Log::warning('Нет реквизитов для направления', [
                'direction_exchange_id' => $directionExchange->id ?? null
            ]);
            return null;
        }

        $requisites = $directionExchange->direction_requisites->where('status', 1)->values();
        $count = $requisites->count();
        if ($count === 0) {
            return null;
        }

        // Индекс без сохранения состояния
        if ($period === 'daily') {
            $index = (int)Carbon::now()->format('z') % $count; // 0..365
        } else {
            $index = ((int)Carbon::now()->format('n') + (int)($directionExchange->id ?? 0)) % $count; // 1..12
        }

        return $requisites[$index];
    }

    /**
     * Нормализация ручных реквизитов к единому формату, который понимает фронт.
     * Принимает строку или массив с разными ключами (account, account_number и т.п.),
     * на выходе — одинаковые поля: wallet_number, memo_id, bank_name, holder.
     */
    private function normalizeManualRequisites(mixed $data): array
    {
        // Приводим к единому формату, понятному фронту
        if (is_string($data)) {
            $data = ['account' => $data];
        }

        if (!is_array($data)) {
            return [];
        }

        $account = $data['wallet_number']
            ?? $data['account']
            ?? $data['account_number']
            ?? $data['number']
            ?? null;

        $memo    = $data['memo_id'] ?? $data['memo'] ?? $data['tag'] ?? null;
        $bank    = $data['bank_name'] ?? $data['bank'] ?? null;
        $holder  = $data['holder'] ?? $data['name'] ?? null;

        $normalized = [
            'wallet_number' => $account ? (string) $account : '',
            'memo_id'       => $memo ? (string) $memo : '',
            'bank_name'     => $bank ? (string) $bank : '',
            'holder'        => $holder ? (string) $holder : '',
        ];

        // Очищаем пустые значения
        return array_filter($normalized, static fn($v) => $v !== null && $v !== '');
    }

    /**
     * Получает текущие данные выбранного мерчанта, если он задан.
     *
     * @return GatewayMerchant|null Данные мерчанта или пустой массив при их отсутствии
     */
    public function getMerchantPay(): ?GatewayMerchant
    {
        return !empty($this->merchantPay) ? $this->merchantPay : null;
    }

    /**
     * Приведение данных мерчанта к единому формату: либо checkout-ссылка, либо счёт/кошелёк.
     * Возвращает null, если данных недостаточно для показа пользователю.
     */
    private function formatMerchantRequisites(MerchantTransactionData $mtd): ?array
    {
        $ext = is_array($mtd->ext_data) ? $mtd->ext_data : [];

        /**
         * 1) Унифицированный checkout (redirect / form)
         */
        if (!empty($mtd->is_checkout_url) && !empty($ext['checkout']['url'])) {
            return array_filter([
                'type'         => 'checkout',
                'checkout_url' => (string) ($ext['checkout']['url'] ?? ''),
                'checkout_id'  => (string) ($ext['checkout']['id'] ?? ''),
                'flow'         => $ext['flow']['mode'] ?? null, // redirect | form
            ], static fn ($v) => $v !== null && $v !== '');
        }

        /**
         * 2) Прямые реквизиты (карта / счёт / кошелёк)
         */
        $account =
            (string) ($ext['wallet_number'] ?? '')
                ?: (string) ($ext['account'] ?? '');

        if ($account !== '') {
            return array_filter([
                'type'          => 'requisites',
                'wallet_number' => $account,
                'memo_id'       => (string) ($ext['memo_id'] ?? ''),
                'bank_name'     => (string) ($ext['bank_name'] ?? ''),
                'label'         => (string) ($ext['label'] ?? ''),
            ], static fn ($v) => $v !== null && $v !== '');
        }

        /**
         * 3) Fallback — ничего валидного
         */
        return null;
    }
}
