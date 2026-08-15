<?php

namespace iEXPackages\Order\Bindings;

use App\Models\AMLResponseData;
use App\Models\CheckboxAgreement;
use App\Models\CitiesModel;
use App\Enums\TaskStatusEnum;
use App\Models\DirectionExchangeCity;
use App\Models\Task;
use App\Models\TaskExtraOut;
use App\Models\TaskField;
use App\Models\TaskInfo;
use App\Models\TaskShot;
use App\Services\ExtraFields\ExtraFieldsService;
use App\Services\SelectedFeesResolver;
use App\Support\Facades\iEXApp;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use iEXPackages\GeoIp\Facades\GeoIP;
use iEXPackages\Order\Concerns\HandlesCardVerification;
use iEXPackages\Order\Concerns\HandlesIdentityVerification;
use iEXPackages\Order\Concerns\HandlesOrderProcessing;
use iEXPackages\Order\Concerns\ManagesUserRegistration;
use iEXPackages\Order\Concerns\OrderCreation;
use iEXPackages\Order\Concerns\ValidatesOrderRules;
use iEXPackages\Order\OrderCalculator;
use iEXPackages\SmartMailer\Facades\SmartMailer;
use iEXPackages\SmartMailer\SmartMailerConditionFactory;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


trait ManagerOrder
{
    use ManagesUserRegistration,
        HandlesOrderProcessing,
        OrderCreation,
        HandlesCardVerification,
        HandlesIdentityVerification,
        ValidatesOrderRules;

    /**
     * Массив данных
     */
    protected Collection $items;

    /**
     * Экземпляр коллекции данных
     *
     * @var Collection
     */
    private Collection $rules;

    /**
     * Создание новой заявки.
     *
     * @return array Массив данных о созданной заявке или ошибки валидации.
     * @throws \Exception
     */
    public function created(): array
    {
        $this->resolveAuthenticatedUser();

        $validationErrors = $this->validateOrder();

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $this->authInfo = $this->resolveUserAndVerifyEmail();

        // Идемпотентность: повторный POST с тем же намерением не создаёт новую заявку (без нового merchant lifecycle).
        try {
            $reused = $this->tryReuseRecentPendingTask();
            if ($reused instanceof Task) {
                $this->setOrder($reused);

                return [
                    'data' => $this->customArrayForCreate($reused),
                    'idempotent_reuse' => true,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Order create idempotent reuse skipped', [
                'message' => $e->getMessage(),
            ]);
        }

        // Создаём заявку
        $order = $this->addToDatabase();

        $this->applyValidationEffects($order);

        try {
            $this->rememberRecentCreateDedupKey($order);
        } catch (\Throwable) {
            // не блокируем создание
        }

        return [
            'data' => $this->customArrayForCreate($order),
        ];
    }

    /**
     * Добавляет новую заявку в базу данных и выполняет связанные действия.
     *
     * @return Task Экземпляр созданной заявки.
     * @throws \Exception
     */
    protected function addToDatabase(): Task
    {
        $resultInPrice = $this->formatIncomeAmount($this->options['income_amount'] ?? 0);

        $fees = new OrderCalculator($this->directionId);
        $responseFee = $fees
            ->setFromAmount($resultInPrice)
            ->withOptions($this->getCalculationOptions())
            ->run();

        $newOrder = Task::create($this->prepareOrderData($responseFee));

        $this->logReferralCaptureForTask($newOrder);

        // Инициализация транзакции и установка начального статуса
        $tx = TransactionFacade::init($newOrder);
        $tx->setStatus(2);

        // Сохраняем номер счёта в уникальной базе для последующей проверки изменений
        if (!empty($this->getOutComeAccount())) {
            TaskShot::create([
                'id_task' => $newOrder->id,
                'account' => security_xss($this->getOutComeAccount()),
            ]);
        }

        // Сохраняем дополнительные поля, указанные в направлениях обмена
        $this->saveAdditionalDirectionFields($newOrder->id);
        $this->saveAdditionalUserOrderFields($newOrder->id);


        $user = $newOrder->user; // или получи через id_user

        // Подготавливаем и сохраняем дополнительные поля валюты
        $task_fields = array_merge(
            $this->prepareCurrencyFields($this->options['fields_in'] ?? [], 'in', $newOrder->id),
            $this->prepareCurrencyFields($this->options['fields_out'] ?? [], 'out', $newOrder->id),
        );

        if (!empty($task_fields)) {
            TaskField::insert($task_fields);
        }

        $this->authId = $this->authInfo->id;
        if ($this->authInfo->isDirty()) {
            $this->authInfo->save();
        }

        // Сохраняем экземпляр текущей заявки
        $this->setOrder($newOrder);
        // Сохраняем доп. реквизиты «Получаю», если переданы во входных опциях
        $this->storeExtraOut($newOrder);

        // Устанавливаем флаг автоматического отображения модального окна
        if ((int)$this->getInCurrency()->is_auto_check_modal === 1) {
            $newOrder->is_autopay_modal = 1;
            $newOrder->save();
        }

        // Сохраняем данные заявки в файл, если это необходимо
        if ((int)iEXSetting('is_save_order_data_to_file') === 1) {
            $this->storeOrderDataToFile($newOrder);
        }

        // Проверяем, является ли пользователь новым
        $is_new_user = $this->isIdentifyNewbie();

        // Добавляем дополнительную информацию к заявке
        $this->additionInfo($newOrder, $is_new_user);

        return $newOrder;
    }

    private function buildGeoData(?string $ip): ?array
    {
        $ip = is_string($ip) ? trim($ip) : '';
        if ($ip === '') {
            return null;
        }

        if (!(bool) iEXSetting('geoip.toggles.enabled', true)) {
            return null;
        }

        try {
            $loc = GeoIP::locate($ip);

            // countryIso может быть null
            $iso = is_string($loc->countryIso) ? strtoupper(trim($loc->countryIso)) : null;
            if ($iso !== null && !preg_match('/^[A-Z]{2}$/', $iso)) {
                $iso = null;
            }

            return [
                'ip' => $ip,

                'continent_code' => $loc->continentCode,
                'continent'      => $loc->continentName,

                'country_iso' => $iso,
                'country'     => $loc->countryName,

                // region/district/city
                'region'   => $loc->regionName,
                'district' => $loc->districtName,
                'city'     => $loc->cityName,

                'timezone' => $loc->timeZone,

                // удобная строка для UI/логов
                'summary'  => $loc->summaryExtended(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Записывает дополнительную информацию, связанную с заявкой.
     *
     * @param Task $order Экземпляр заявки.
     * @param bool $is_newbie Флаг нового пользователя.
     * @throws \Exception
     */
    protected function additionInfo(Task $order, bool $is_newbie = false): void
    {
        // Создание записи с общей информацией о заявке
        $task_info = TaskInfo::create(
            $this->buildTaskInfoOptions($order, $is_newbie)
        );
        // На случай, если поток создания изменится: сохранём extra_out, если его ещё нет
        if (!$order->relationLoaded('extraOuts') || $order->extraOuts()->doesntExist()) {
            $this->storeExtraOut($order);
        }

        $metaData['user_agent'] = request()->userAgent();
        $metaData['device_type'] = $this->options['device_type'] ?? null;

        // 3) geo_data -> tasks_meta.geo_data
        $geoData = $this->buildGeoData($order->ip);
        $metaData['geo_data'] = $geoData;

        $metaData['user_flag'] = null;
        if (is_array($geoData)) {
            $iso = $geoData['country_iso'] ?? null;
            if (is_string($iso) && preg_match('/^[A-Z]{2}$/', $iso)) {
                $metaData['user_flag'] = $iso; // уже upper в buildGeoData
            }
        }

        // Подготовка данных telegram и selected_fees (новый формат)
        if (isset($this->options['telegram_id'], $this->options['telegram_data']) || !empty($this->options['selected_fees'])) {
            $metaData['telegram_id'] = $this->options['telegram_id'] ?? null;
            $metaData['telegram_data'] = $this->options['telegram_data'] ?? null;

            // Используем SelectedFeesResolver для enrichment
            if (!empty($this->options['selected_fees']) && is_array($this->options['selected_fees'])) {
                $resolver = app(SelectedFeesResolver::class);
                $metaData['selected_fees'] = $resolver->enrichSnapshot(
                    $this->options['selected_fees'],
                    !empty($this->options['selector_fees']) && is_array($this->options['selector_fees'])
                        ? $this->options['selector_fees']
                        : null
                );
            }
        }

        // Подготовка данных checkbox_agreements
        if (!empty($this->options['checkbox_agreements'])) {
            $checkboxAgreements = CheckboxAgreement::query()
                ->whereIn('key_id', array_keys($this->options['checkbox_agreements']))
                ->pluck('label', 'key_id');

            $checkboxData = [];
            foreach ($this->options['checkbox_agreements'] as $key => $checked) {
                $label = $checkboxAgreements[$key] ?? '';
                if (!is_string($label)) {
                    $label = (string) $label;
                }

                // Ищем комиссии (fee) внутри label
                preg_match_all('/\{fee=([-+]?[0-9]*\.?[0-9]+%?)\}(.*?)\{\/fee\}/', $label, $feeMatches, PREG_SET_ORDER);

                $fees = array_map(static fn($feeMatch) => [
                    'value' => $feeMatch[1],
                    'text' => trim($feeMatch[2]),
                ], $feeMatches);

                $cleanLabel = trim(str_replace(['{link}', '{/link}'], '', $label));
                $checkboxData[$key] = [
                    'checked' => (bool)$checked,
                    'label' => $cleanLabel,
                    'fees' => $fees,
                ];
            }

            $metaData['checkbox_agreements'] = $checkboxData;
        }

        // Сохраняем флаг "нужна ли верификация карты" (снимок)
        if (isset($this->options['card_verification_required'])) {
            $metaData['card_verification_required'] = (bool)$this->options['card_verification_required'];
        }

        // Сохраняем тип верификации, который сработал на момент создания заявки
        $metaData['card_verification_type'] = (int)($this->directionId->card_verification_type ?? 0);

        // Единоразово создаём или обновляем meta
        if (!empty($metaData)) {
            $order->meta()->updateOrCreate([], $metaData);

            // Перезагружаем отношение meta
            $order->load('meta');
        }

        // Обработка верификации карты, если необходимо
        $this->handleCardVerification($order);

        $this->handleIdentityVerification($order);

        // Отправка уведомления о создании заявки через Telegram
        iEXApp::telegramNotificationForChannel('process_created_order', $this->getOrder());


        // Отсылаем сообщение о создании заявки (стандартная)
        try {
            if (SmartMailerConditionFactory::make('order_created', $order)->shouldSend()) {
                SmartMailer::dispatch(
                    sendable: 'order_created_job',
                    model: $order,
                    delaySeconds: 5,
                    queue: 'high'
                );
            }
        } catch (\Throwable $e) {
            Log::error('SmartMailer dispatch failed: ' . $e->getMessage(), ['order_id' => $order->id]);
        }
    }

    /**
     * Сохраняет доп. реквизиты «Получаю» (extra_out) для заказа.
     *
     * Источник данных: $this->options['extra_out'] → { enabled?:bool, fields: [{label, amount}, ...] }.
     * Сохраняем ТОЛЬКО «осмысленные» строки: сумма строго > 0 (после нормализации) или непустой label.
     *
     * Особенности реализации:
     *  • Без number_format — работаем со строкой суммы, обрезая дробную часть до допустимой точности
     *    (по currency2->number_format) без округления; убираем хвостовые нули и точку.
     *  • Поддерживаем десятичную запятую и пробелы в сумме ("1 234,50" → "1234.5").
     *  • Максимум 20 записей (как договорено на фронте), порядок сохраняем через position.
     *  • Если после фильтрации нет валидных записей — ничего не пишем.
     */
    protected function storeExtraOut(Task $order): void
    {
        $payload = $this->options['extra_out'] ?? null;

        if (!is_array($payload) || empty($payload['fields']) || !is_array($payload['fields'])) {
            return;
        }

        $scale = (int) ($this->directionId->currency2?->number_format ?? (int) iEXSetting('max_decimal_places', 18));
        $limit  = 20;

        $fields = collect($payload['fields'])
            ->slice(0, $limit)
            ->values()
            ->map(function ($f, $i) use ($scale) {
                $amount = $this->parseAmount($f['amount'] ?? null, $scale); // null, если невалидно/≤0

                $label = isset($f['label']) ? trim((string)$f['label']) : '';
                $labelNorm = $label !== '' ? mb_substr(security_xss($label), 0, 255) : null;

                return [
                    'label'    => $labelNorm,
                    'amount'   => $amount,   // строка, например "100", "1234.5"
                    'position' => (int) $i,
                ];
            })
            ->filter(fn ($f) => $f['amount'] !== null || $f['label'] !== null)
            ->values();

        if ($fields->isEmpty()) {
            return;
        }

        $now  = now();
        $rows = $fields->map(fn ($f) => [
            'id_task'    => $order->id,
            'label'      => $f['label'],
            'amount'     => (string) $f['amount'],
            'position'   => $f['position'],
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        TaskExtraOut::insert($rows);

        // Перезагружаем только связь extraOuts, meta не трогаем
        $order->load('extraOuts');
    }

    /**
     * Парсит сумму в строку с отсечением дробной части без округления.
     * Возвращает null, если не число или ≤ 0.
     */
    private function parseAmount(?string $raw, int $scale = 18): ?string
    {
        if ($raw === null) return null;

        // 1) Лёгкая очистка: обычный пробел, NBSP, запятая → точка
        $s = trim((string) $raw);
        if ($s === '') return null;
        $s = str_replace([" ", "\u{00A0}"], '', $s);
        $s = str_replace(',', '.', $s);
        if ($s[0] === '.') $s = '0' . $s;

        // 2) Валидация формата
        if (!preg_match('/^\d+(?:\.\d+)?$/', $s)) {
            return null;
        }

        // 3) Точная математика и отсечение без округления
        $dec = BigDecimal::of($s);
        if ($dec->isLessThanOrEqualTo('0')) {
            return null;
        }
        $dec = $dec->toScale($scale, RoundingMode::DOWN);

        // 4) Человечный вывод: убрать хвостовые нули ТОЛЬКО у дробной части
        $out = $dec->__toString();
        if (str_contains($out, '.')) {
            $out = rtrim(rtrim($out, '0'), '.');
            if ($out === '') $out = '0';
        }
        return $out;
    }

    /**
     * Формирует массив опций для создания записи TaskInfo.
     *
     * @param Task $order Экземпляр заявки.
     * @param bool $is_newbie Флаг нового пользователя.
     *
     * @return array Подготовленный массив данных.
     */
    protected function buildTaskInfoOptions(Task $order, bool $is_newbie): array
    {
        $optionsTaskInfo = [
            'id_task' => $order->id,
            'ip' => $order->ip,
            'device' => $this->getUserDevice(),
            'newbie' => $is_newbie ? 1 : 0,
            'language' => app()->getLocale(),
            'is_not_partner' => $this->directionId->is_not_partner,
            'in_min_amount' => $this->directionId->min_price1,
            'in_max_amount' => $this->directionId->max_price1,
            'dot_not_remember_data' => $this->options['dot_not_remember_data'] ?? 0,
        ];

        if (!empty($this->options['city_id'])) {
            $this->appendCityData($optionsTaskInfo, (int)$this->options['city_id']);
        }

        if (!empty($this->options['country'])) {
            $optionsTaskInfo['country_name'] = security_xss(trim($this->options['country']));
        }

        return $optionsTaskInfo;
    }

    /**
     * Дополняет массив информацией о городе на основании переданного ID города.
     *
     * @param array $optionsTaskInfo Ссылка на массив данных, который дополняется.
     * @param int $cityId Идентификатор города.
     */
    protected function appendCityData(array &$optionsTaskInfo, int $cityId): void
    {
        $direction_city = DirectionExchangeCity::find($cityId);

        if ($direction_city && ($city_item = CitiesModel::find($direction_city->city_id))) {
            $optionsTaskInfo['direction_city_id'] = $direction_city->id;
            $optionsTaskInfo['city_id'] = $city_item->id;
            $optionsTaskInfo['city_name'] = $city_item->name;
        }
    }

    /**
     * Сохраняет данные заявки в файл (без чувствительных полей), используя Storage.
     *
     * @param Task $order Экземпляр заявки.
     */
    protected function storeOrderDataToFile(Task $order): void
    {
        try {
            // Локальный диск, путь: storage/app/orders/order_{id}_create.json
            $payload = json_encode(
                $order->toArray(),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
            Storage::disk('local')->put('orders/order_' . $order->id . '_create.json', $payload);
        } catch (\Throwable $e) {
            Log::error('StoreOrderDataToFile error: ' . $e->getMessage(), ['order_id' => $order->id]);
        }
    }

    /**
     * Окно повторного использования недавней заявки (минуты), верхняя граница — защита от ошибочной настройки.
     */
    private function orderCreateIdempotencyTtlMinutes(): int
    {
        return max(1, min(30, (int) iEXSetting('order_create_idempotency_ttl_minutes', 10)));
    }

    /**
     * Пересчёт сумм как при создании заявки (без записи в БД).
     *
     * @return array{response_fee: array<string, mixed>, give_price: string, receiving_price: string}
     */
    private function computeReusePricing(): array
    {
        $resultInPrice = $this->formatIncomeAmount($this->options['income_amount'] ?? 0);

        $fees = new OrderCalculator($this->directionId);
        $responseFee = $fees
            ->setFromAmount($resultInPrice)
            ->withOptions($this->getCalculationOptions())
            ->run();

        $incomeDecimal = $this->getInCurrency()->number_format;
        $outcomeDecimal = $this->getOutCurrency()->number_format;

        return [
            'response_fee' => $responseFee,
            'give_price' => $this->formatAmount($responseFee['give_price'], $incomeDecimal),
            'receiving_price' => $this->formatAmount($responseFee['receiving_price'], $outcomeDecimal),
        ];
    }

    private function resolveExpectedPromoCodeLower(array $responseFee): ?string
    {
        $promoId = (int) ($responseFee['id_promo_code'] ?? 0);
        if ($promoId <= 0) {
            return null;
        }

        $code = (string) ($responseFee['promo_code_code'] ?? ($this->options['promo_code'] ?? ''));
        $code = mb_strtolower(trim($code));

        return $code !== '' ? $code : null;
    }

    /**
     * @param array{response_fee: array<string, mixed>, give_price: string, receiving_price: string} $pricing
     */
    private function buildOrderCreateDedupHash(array $pricing): string
    {
        $calcOpts = $this->getCalculationOptions();
        $typeRate = $this->resolveOrderType();
        $promoLower = $this->resolveExpectedPromoCodeLower($pricing['response_fee']);

        $fromShot = (string) security_xss($this->getInComeAccount());
        $toShot = (string) security_xss($this->getOutComeAccount());

        $checkbox = $this->options['checkbox_agreements'] ?? null;
        $cbKeys = [];
        if (is_array($checkbox)) {
            foreach (array_keys($checkbox) as $k) {
                if (!empty($checkbox[$k])) {
                    $cbKeys[] = (string) $k;
                }
            }
            sort($cbKeys, SORT_STRING);
        }

        $struct = [
            'v' => 1,
            'dir' => $this->directionId->id,
            'give' => $pricing['give_price'],
            'recv' => $pricing['receiving_price'],
            'tr' => $typeRate,
            'promo' => $promoLower,
            'from' => $fromShot,
            'to' => $toShot,
            'city' => (int) ($this->options['city_id'] ?? 0),
            'fees' => $calcOpts['selected_fees'] ?? [],
            'card_v' => (bool) ($calcOpts['card_verification_required'] ?? false),
            'cb' => implode(',', $cbKeys),
            'uid' => $this->authInfo->id,
            'guest' => (int) ($this->authInfo->is_guest ?? 0),
            'attempt' => $this->normalizedOrderAttemptId(),
        ];

        if (! Auth::check()) {
            $struct['email'] = mb_strtolower(trim((string) $this->getClientEmail()));
            $struct['ip'] = (string) $this->clientIp();
        }

        return hash('sha256', json_encode($struct, JSON_UNESCAPED_UNICODE));
    }

    private function normalizedOrderAttemptId(): string
    {
        $raw = $this->options['order_attempt_id'] ?? $this->options['idempotency_key'] ?? '';
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '' || strlen($raw) > 128) {
            return '';
        }
        if (! preg_match('/^[A-Za-z0-9._:-]+$/', $raw)) {
            return '';
        }

        return $raw;
    }

    private function orderCreateIdempotencyCacheKey(string $hash): string
    {
        return 'order_create_idem:v1:'.$hash;
    }

    private function rememberRecentCreateDedupKey(Task $order): void
    {
        $pricing = $this->computeReusePricing();
        $hash = $this->buildOrderCreateDedupHash($pricing);
        $ttl = $this->orderCreateIdempotencyTtlMinutes();
        Cache::put($this->orderCreateIdempotencyCacheKey($hash), $order->id, now()->addMinutes($ttl));
        $attempt = $this->normalizedOrderAttemptId();
        if ($attempt !== '') {
            Cache::put('order_create_attempt:v1:'.$attempt, $order->id, now()->addMinutes($ttl));
        }
    }

    private function tryReuseRecentPendingTask(): ?Task
    {
        $pricing = $this->computeReusePricing();
        $hash = $this->buildOrderCreateDedupHash($pricing);
        $cacheKey = $this->orderCreateIdempotencyCacheKey($hash);
        $ttl = $this->orderCreateIdempotencyTtlMinutes();
        $typeRate = $this->resolveOrderType();
        $promoLower = $this->resolveExpectedPromoCodeLower($pricing['response_fee']);

        $attempt = $this->normalizedOrderAttemptId();
        if ($attempt !== '') {
            $attemptId = Cache::get('order_create_attempt:v1:'.$attempt);
            if ($attemptId) {
                $task = Task::query()->whereKey((int) $attemptId)->first();
                if ($task instanceof Task && $this->taskMatchesReuseContract($task, $pricing, $typeRate, $promoLower, $ttl)) {
                    Log::info('order_create_idempotent_reuse', [
                        'task_id' => $task->id,
                        'public_id' => $task->public_id,
                        'via' => 'attempt_id',
                    ]);

                    return $task;
                }
            }
        }

        $cachedId = Cache::get($cacheKey);
        if ($cachedId) {
            $task = Task::query()->whereKey((int) $cachedId)->first();
            if ($task instanceof Task && $this->taskMatchesReuseContract($task, $pricing, $typeRate, $promoLower, $ttl)) {
                Log::info('order_create_idempotent_reuse', [
                    'task_id' => $task->id,
                    'public_id' => $task->public_id,
                    'via' => 'cache',
                ]);

                return $task;
            }
        }

        $query = Task::query()
            ->where('id_direction_exchange', $this->directionId->id)
            ->where('status', TaskStatusEnum::PENDING_PAYMENT->value)
            ->where('id_user', $this->authInfo->id)
            ->where('give_price', $pricing['give_price'])
            ->where('receiving_price', $pricing['receiving_price'])
            ->where('type_rate', $typeRate)
            ->where('created_at', '>=', now()->subMinutes($ttl));

        if (! Auth::check()) {
            $email = mb_strtolower(trim((string) $this->getClientEmail()));
            $query->whereRaw('LOWER(TRIM(COALESCE(email, ?))) = ?', ['', $email])
                ->where('ip', $this->clientIp());
        }

        if ($promoLower === null) {
            $query->where(function ($q): void {
                $q->whereNull('promo_code_code')->orWhere('promo_code_code', '');
            });
        } else {
            $query->whereRaw('LOWER(TRIM(promo_code_code)) = ?', [$promoLower]);
        }

        $candidates = $query->orderByDesc('id')->limit(15)->get();
        foreach ($candidates as $task) {
            if (! $this->taskMatchesReuseContract($task, $pricing, $typeRate, $promoLower, $ttl)) {
                continue;
            }

            Cache::put($cacheKey, $task->id, now()->addMinutes($ttl));

            Log::info('order_create_idempotent_reuse', [
                'task_id' => $task->id,
                'public_id' => $task->public_id,
                'via' => 'db',
            ]);

            return $task;
        }

        return null;
    }

    /**
     * @param array{give_price: string, receiving_price: string} $pricing
     */
    private function taskMatchesReuseContract(
        Task $task,
        array $pricing,
        int $typeRate,
        ?string $expectedPromoLower,
        int $ttlMinutes
    ): bool {
        if ((int) $task->status !== TaskStatusEnum::PENDING_PAYMENT->value) {
            return false;
        }

        if ((int) $task->id_direction_exchange !== (int) $this->directionId->id) {
            return false;
        }

        if ((int) $task->id_user !== (int) $this->authInfo->id) {
            return false;
        }

        if (! Auth::check()) {
            $email = mb_strtolower(trim((string) $this->getClientEmail()));
            $tEmail = mb_strtolower(trim((string) ($task->email ?? '')));
            if ($tEmail !== $email || (string) $task->ip !== (string) $this->clientIp()) {
                return false;
            }
        }

        if ((int) $task->type_rate !== $typeRate) {
            return false;
        }

        $taskPromo = $task->promo_code_code;
        $taskPromoLower = ($taskPromo === null || $taskPromo === '')
            ? null
            : mb_strtolower(trim((string) $taskPromo));

        if ($taskPromoLower !== $expectedPromoLower) {
            return false;
        }

        if ((string) $task->give_price !== $pricing['give_price']
            || (string) $task->receiving_price !== $pricing['receiving_price']) {
            return false;
        }

        $fromShot = (string) security_xss($this->getInComeAccount());
        $toShot = (string) security_xss($this->getOutComeAccount());
        if ((string) $task->from_shot !== $fromShot || (string) $task->to_shot !== $toShot) {
            return false;
        }

        if (! $this->reuseCityMatches($task)) {
            return false;
        }

        $created = Carbon::parse($task->created_at);
        if ($created->lt(now()->subMinutes($ttlMinutes))) {
            return false;
        }

        $maxSec = (int) iEXSetting('max_time_task');
        if ($maxSec > 0 && now()->greaterThan($created->copy()->addSeconds($maxSec))) {
            return false;
        }

        return true;
    }

    private function reuseCityMatches(Task $task): bool
    {
        $task->loadMissing('task_info');

        $requested = (int) ($this->options['city_id'] ?? 0);
        $storedDirectionCityId = (int) (optional($task->task_info)->direction_city_id ?? 0);

        if ($requested <= 0) {
            return $storedDirectionCityId === 0;
        }

        $directionCity = DirectionExchangeCity::find($requested);

        return $directionCity !== null && (int) $directionCity->id === $storedDirectionCityId;
    }

    /**
     * Нормализует входящий selected_fees из опций формы до канонического массива [{id, scope}].
     *
     * @param array $input           Входной массив с элементами {id, scope?}
     * @param array|null $selectorFees Справочник комиссий (опционально), элементы: {id, type: 'common'|'individual'}
     * @param int $maxItems          Ограничение количества элементов (по умолчанию 20)
     * @return array<int, array{id:int, scope:string}>
     */
    private function normalizeSelectedFeesOptions(array $input, ?array $selectorFees = null, int $maxItems = 20): array
    {
        $input = array_slice($input, 0, max(1, $maxItems));

        // Индексация справочников
        $byCommon = $byIndividual = null;
        if (is_array($selectorFees)) {
            $byCommon = [];
            $byIndividual = [];
            foreach ($selectorFees as $feeRow) {
                $fid = (int)($feeRow['id'] ?? 0);
                $ftype = (string)($feeRow['type'] ?? '');
                if ($fid > 0 && $ftype === 'common') $byCommon[$fid] = $feeRow;
                if ($fid > 0 && $ftype === 'individual') $byIndividual[$fid] = $feeRow;
            }
        }

        $seen = [];
        $normalized = [];

        foreach ($input as $row) {
            if (is_object($row)) $row = (array)$row;
            if (!is_array($row) || !isset($row['id'])) continue;

            $id = (int)$row['id'];
            if ($id <= 0) continue;

            $scope = strtolower((string)($row['scope'] ?? 'common'));
            $scope = $scope === 'individual' ? 'individual' : 'common';

            $key = $id.'|'.$scope;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            // создаём базу
            $item = [
                'id' => $id,
                'scope' => $scope,
                'details' => [],
            ];

            // если фронт прислал details — сохраняем
            if (!empty($row['details']) && is_array($row['details'])) {
                $item['details'] = [
                    'name'        => (string)($row['details']['name'] ?? ''),
                    'description' => (string)($row['details']['description'] ?? ''),
                    'fee'         => (string)($row['details']['fee'] ?? ''),
                    'fee_type'    => (string)($row['details']['fee_type'] ?? 'dynamic'),
                    'sorting'     => (int)($row['details']['sorting'] ?? 0),
                    'snapshot_at' => now()->toISOString(),
                    'version'     => 1,
                ];
            } else {
                // иначе тянем из справочника
                $src = ($scope === 'individual') ? ($byIndividual[$id] ?? null) : ($byCommon[$id] ?? null);
                if ($src) {
                    $item['details'] = [
                        'name'        => (string)($src['name'] ?? ''),
                        'description' => (string)($src['description'] ?? ''),
                        'fee'         => (string)($src['fee'] ?? ''),
                        'fee_type'    => (string)($src['fee_type'] ?? 'dynamic'),
                        'sorting'     => (int)($src['sorting'] ?? 0),
                        'snapshot_at' => now()->toISOString(),
                        'version'     => 1,
                    ];
                }
            }

            $normalized[] = $item;
        }

        return array_values($normalized);
    }
}
