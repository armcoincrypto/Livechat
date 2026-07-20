<?php
declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Http\Resources\Operations;

use App\Models\CheckboxAgreement;
use App\Models\Currency;
use App\Models\ExtraField;
use App\Models\DirectionTemplate;
use App\Models\CurrencyTemplate;
use App\Models\Page;
use App\Services\Rates\RateDirectionEligibility;
use iEXPackages\Calculator\CalculatorFacade;
use iEXPackages\TagProcessors\TagProcessors;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DirectionDetailResource extends JsonResource
{
    public static $wrap = null;


    private ?Collection $cachedTemplates = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (empty($this->currency1) && empty($this->currency2)) {
            return [];
        }

        $coursePayload = null;
        $rateUnavailable = false;
        $rateUnavailableCode = null;
        try {
            $surface = RateDirectionEligibility::make()->evaluateDirection($this->resource);
            if (!empty($surface['quote_allowed'])) {
                $coursePayload = CalculatorFacade::setDirectionExchange($this->resource)->calculate()->toArray();
            } else {
                $rateUnavailable = true;
                $rateUnavailableCode = RateDirectionEligibility::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE;
            }
        } catch (Throwable $e) {
            Log::error('rate_public_surface_quote_gate_failed', [
                'direction_id' => $this->id ?? null,
                'message' => $e->getMessage(),
            ]);
            $rateUnavailable = true;
            $rateUnavailableCode = RateDirectionEligibility::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE;
        }

        $response =  [
            'id' => $this->id,
            'type' => 'direction_exchange',
            'attributes' => array_filter([
                'course' => $coursePayload,
                'rate_unavailable' => $rateUnavailable ?: null,
                'error_code' => $rateUnavailableCode,
                ...$this->getLimits(),
                'buy_currency' => new CurrencyResource($this->currency1),
                'sell_currency' => new CurrencyResource($this->currency2),
                'direction_fields' => $this->getDirectionFields(),
                'user_fields' => $this->getUserOrderFields(),
                'confirm_order' => $this->getConfirmOrder(),
                'reserve_value' => $this->when($this->type_reserve == 1, (float)$this->direction_reserve),
                'is_user_discount' => (int)$this->is_enable_user_discount == 1,
                ...$this->getCommissions(),
                ...$this->getDescriptions(),
                'multiplicity' => $this->getMultiplicity(),
                'is_notify_exchange_amount' => (int)$this->is_notify_exchange_amount,
                ...$this->getCitiesAndPrices(),
                'type_rate' => $this->getTypeRate(),
                'seo' => $this->getSeoPayload(),

                'title_selector_fee' => $this->when(!empty($this->title_selector_fee), $this->title_selector_fee),
                'text_selector_fee' => $this->when(!empty($this->text_selector_fee), $this->text_selector_fee),
                'checkbox_agreements' => $this->getCheckboxAgreements(),
                'verification_settings' => $this->getVerificationSettings(),
            ]),
        ];

        // Верификация личности: базовые настройки берем с уровня валюты
        $currencyIdentityRules = $this->currency1->identity_verification_rules ?? [];
        $identityMode = (string) ($currencyIdentityRules['mode'] ?? 'disabled');
        $identityDescription = !empty($this->currency1->identity_text)
            ? $this->currency1->identity_text
            : null;

        // Если на уровне направления включены собственные настройки,
        // можем переопределить режим и текст
        if ((int) ($this->identity_verification_type ?? 0) === 1) {
            $identityRules = $this->identity_verification_rules ?? [];

            if (!empty($identityRules['mode'])) {
                $identityMode = (string) $identityRules['mode'];
            }

            if (!empty($this->identity_text)) {
                $identityDescription = $this->identity_text;
            }
        }

        $response['attributes']['identity_verification'] = [
            'mode'        => $identityMode,
            'description' => $identityDescription,
        ];

        // Базовый режим и описание верификации карты — с уровня валюты
        $cardMode = (int) ($this->currency1->is_enabled_verification ?? 0);
        $cardDescription = !empty($this->currency1->verification_text)
            ? $this->currency1->verification_text
            : null;



        // Если на уровне направления включены собственные настройки верификации карты,
        // учитываем их режим и описание
        if (!empty($this->card_verification_type)) {
            $rules = $this->card_verification_rules ?? [];
            $directionCfg = $rules['direction'] ?? [];

            // card_verification_type = 1 — "из настроек направления"
            if ((int) $this->card_verification_type === 1 && array_key_exists('mode', $directionCfg)) {
                $cardMode = (int) $directionCfg['mode'];
            }

            // При наличии отдельного текста верификации на уровне направления — используем его
            if (!empty($this->direction_verification_text)) {
                $cardDescription = $this->direction_verification_text;
            }
        }

        $response['attributes']['card_verification'] = [
            'mode' => (int) $cardMode,
            'description' => $cardDescription,
        ];






        return $response;
    }

    protected function getVerificationSettings(): ?array
    {
        // Конструктор верификации карт используем только при типе 2
        if ((int) $this->card_verification_type !== 2) {
            return null;
        }

        $defaultMin = is_numeric($this->min_price1) ? (float) $this->min_price1 : 0.0;
        $defaultMax = is_numeric($this->max_price1) ? (float) $this->max_price1 : 0.0;

        // Функция для нормализации значений
        $normalizeAmount = static function ($amount, $default) {
            return is_numeric($amount) && (float) $amount > 0
                ? (float) $amount
                : $default;
        };

        $rules = $this->card_verification_rules ?? [];

        $noVerification   = $rules['no_verification']   ?? [];
        $withVerification = $rules['with_verification'] ?? [];

        return [
            // Для обратной совместимости фронт по-прежнему использует key card_verification_type
            'card_verification_type' => (int) $this->card_verification_type,
            'no_verification'   => [
                'min_amount'  => $normalizeAmount($noVerification['min_amount'] ?? null, $defaultMin),
                'max_amount'  => $normalizeAmount($noVerification['max_amount'] ?? null, $defaultMax),
                'fee'         => (string) ($noVerification['fee'] ?? '0'),
                // Описание берём из полей направления, как и раньше
                'description' => $this->no_verification_description,
            ],
            'verification'      => [
                'min_amount'  => $normalizeAmount($withVerification['min_amount'] ?? null, $defaultMin),
                'max_amount'  => $normalizeAmount($withVerification['max_amount'] ?? null, $defaultMax),
                'fee'         => (string) ($withVerification['fee'] ?? '0'),
                'description' => $this->verification_description,
            ],
        ];
    }

    protected function getLimits(): array
    {
        return [
            'min_in' => $this->min_price1,
            'max_in' => $this->max_price1,
            'min_out' => $this->min_price2,
            'max_out' => $this->max_price2,
            'sorting_out' => $this->sorting_2,
        ];
    }

    protected function getDirectionFields(): array
    {
        return $this->direction_field_sorting->map(function ($relationship) {
            return [
                'key' => $relationship->key_id,
                'type' => 'input',
                'templateOptions' => [
                    'label' => $relationship->name,
                    'placeholder' => $relationship->name,
                    'required' => $relationship->obligatory_field == 0,
                    'appearance' => 'fill',
                    'description' => (is_string($relationship->description) && !empty($relationship->description)) ? $relationship->description : null
                ],
            ];
        })->toArray();
    }

    /**
     * Глобальные пользовательские поля для заявок (scope=user_order).
     *
     * Фронт отправляет значения в payload как:
     * - user_fields.{key_id}
     *
     * ВАЖНО:
     * - description/placeholder берём из name (так как в упрощённой схеме нет description).
     * - required определяется по obligatory_field (0 => required).
     */
    protected function getUserOrderFields(): array
    {
        $fields = ExtraField::query()
            ->active()
            ->where('scope', 'user_order')
            ->orderBy('sorting')
            ->get();

        if ($fields->isEmpty()) {
            return [];
        }

        return $fields->map(static function (ExtraField $field) {
            $label = $field->name;

            return [
                'key' => $field->key_id,
                'type' => 'input',
                'templateOptions' => [
                    'label' => $label,
                    'placeholder' => $label,
                    'required' => ((int) $field->obligatory_field) === 0,
                    'appearance' => 'fill',
                ],
            ];
        })->values()->toArray();
    }

    protected function getConfirmOrder(): ?array
    {
        if (empty($this->text_order_confirm)) {
            return null;
        }

        $intervalConfirm = (int)$this->interval_confirm_order;

        return [
            'description' => $this->text_order_confirm,
            'custom_button' => $this->order_button_i_confirm ?: null,
            'interval' => $intervalConfirm > 0 ? $intervalConfirm * 1000 : 3000
        ];
    }

    protected function getCommissions(): array
    {
        return [
            'oth_comm1_percent' => $this->oth_comm_percent,
            'oth_comm1_currency' => $this->oth_comm_currency,
            'oth_min1_comm' => $this->oth_min_comm,
            'oth_min2_comm' => $this->oth_min2_comm,
            'oth_comm2_percent' => $this->oth_comm2_percent,
            'oth_comm2_currency' => $this->oth_comm2_currency,
            'pay_comm1_percent' => $this->pay_comm_percent,
            'pay_comm1_currency' => $this->pay_comm_currency,
            'pay_min1_comm' => $this->pay_min_comm,
            'pay_comm2_percent' => $this->pay_comm2_percent,
            'pay_comm2_currency' => $this->pay_comm2_currency,
            'pay_min2_comm' => $this->pay_min2_comm,
        ];
    }

    protected function getDescriptions(): array
    {
        // Режимы источника описания:
        // - на уровне направления (главный)
        // - на уровне валюты (используем только если у направления auto)
        $directionMode = $this->desc_source_mode ?? 'auto';
        $currencyMode  = $this->currency1?->desc_source_mode ?? 'auto';

        // Выбираем эффективный режим по аналогии с инструкциями
        $effectiveMode = $directionMode !== 'auto' ? $directionMode : $currencyMode;
        if (!in_array($effectiveMode, ['direction', 'currency'], true)) {
            $effectiveMode = 'auto';
        }

        // 1. Описание на уровне направления (desc_exchange)
        $directionDesc = $this->getTemplateText(1, $this->desc_exchange);

        // 2. Описание на уровне валюты (currency1) — desc_exchange_currency
        $descExchangeCurrency = null;

        if ($this->currency1) {
            // Кешируем шаблоны для валют (как в CurrencyResource)
            $templates_currencies = Cache::remember(
                'compiler-conditionals-directions-template',
                Carbon::now()->addSeconds(40),
                static function () {
                    return CurrencyTemplate::all();
                }
            );

            $templates_desc_exchange = $templates_currencies->first(function ($item) {
                return $item->id_type === 1;
            });

            $descExchangeCurrency = $this->currency1->desc_exchange;

            if (!empty($templates_desc_exchange)) {
                if ($templates_desc_exchange->type_view_info == 1) {
                    $descExchangeCurrency = $templates_desc_exchange->text;
                } elseif ($templates_desc_exchange->type_view_info == 2 && empty($descExchangeCurrency)) {
                    $descExchangeCurrency = $templates_desc_exchange->text;
                }
            }
        }

        // Применяем эффективный режим к полученным описаниям
        switch ($effectiveMode) {
            case 'direction':
                // Принудительно только описание направления,
                // описание с уровня валюты отключаем
                $descExchangeCurrency = null;
                break;

            case 'currency':
                // Принудительно только описание валюты,
                // описание направления отключаем
                $directionDesc = null;
                break;

            case 'auto':
            default:
                // Старое поведение:
                // основное описание — с уровня направления,
                // описание валюты может использоваться как дополнительное
                break;
        }

        return array_filter([
            'desc_exchange'          => $directionDesc,
            'desc_exchange_currency' => $descExchangeCurrency,
            'desc_exchange_dop'      => $this->getTemplateText(2, $this->desc_exchange_dop),
            'formalization_text'     => $this->getTemplateText(3, $this->formalization_text),
        ]);
    }

    protected function getTemplateText(int $typeId, mixed $default): ?string
    {
        $template = $this->directionTemplates()->firstWhere('id_type', $typeId);

        if (!empty($template) && ($template->type_view_info == 1 || ($template->type_view_info == 2 && empty($default)))) {
            return $template->text;
        }

        if (empty($default)) {
            return null;
        }

        return is_array($default) ? json_encode($default) : $default;
    }

    protected function getMultiplicity(): ?array
    {
        if ($this->multiplicity_type <= 0) {
            return null;
        }

        return [
            'multiplicity_type' => $this->multiplicity_type,
            'multiplicity_amount' => $this->multiplicity_amount,
            'multiplicity_comment' => $this->multiplicity_comment ?: null,
        ];
    }

    protected function getCitiesAndPrices(): array
    {
        /** @var TagProcessors $app */
        $app = app(TagProcessors::class);

        return $this->whenLoaded('direction_exchange_cities', function () use ($app) {
            $result = ['prices' => [], 'cities' => [], 'city_detail' => []];

            // Соберём список id городов для meta (опционально, но красиво)
            $cityIds = $this->direction_exchange_cities
                ->where('status', 1)
                ->pluck('id')
                ->all();

            foreach ($this->direction_exchange_cities as $city_price) {
                if (!$city_price->status) {
                    continue; // Пропускаем города с отключённым статусом
                }

                $result['prices']['id_' . $city_price->id] = [
                    'min_price' => $city_price->min_price ?: $this->min_price1,
                    'max_price' => $city_price->max_price ?: $this->max_price1,
                ];

                // Обрабатываем information через TagProcessors с meta/данными
                $rawInformation = $city_price->information;

                if (is_string($rawInformation) && $rawInformation !== '') {
                    $processedInfo = $app
                        ->setProcessor('direction_city')
                        ->setText($rawInformation)
                        ->setData([
                            'direction_id' => $this->id,
                            'city_id'      => $city_price->id,
                            'city_ids'     => $cityIds,
                        ])
                        ->withMeta([
                            'direction_id' => $this->id,
                            'city_id'      => $city_price->id,
                            'city_ids'     => $cityIds,
                        ])
                        ->process()
                        ->getText();
                } else {
                    $processedInfo = null;
                }

                $result['city_detail']['id_' . $city_price->id] = [
                    'information' => $processedInfo,
                ];

                if (isset($city_price->city, $city_price->city->country)) {
                    $result['cities'][] = [
                        'country' => $city_price->city?->country?->value ?? 'NoName',
                        'value'   => $city_price->id,
                        'label'   => $city_price->city->name,
                        'code'    => $city_price->city->designation_xml,
                    ];
                }
            }

            return $result;
        }, ['prices' => [], 'cities' => [], 'city_detail' => []]);
    }

    protected function getTypeRate(): ?array
    {
        if ($this->is_type_rate != 1) {
            return null;
        }

        return [
            'description' => $this->type_rate_description,
            'display' => array_filter([
                'fixed' => $this->fix_fee_display == 1 ? $this->fix_fee : null,
                'floating' => $this->floating_fee_display == 1 ? $this->floating_fee : null,
            ]),
        ];
    }

    protected function directionTemplates(): Collection
    {
        if (!$this->cachedTemplates) {
            $this->cachedTemplates = DirectionTemplate::all();
        }

        return $this->cachedTemplates;
    }

    /**
     * Get checkbox agreements for this direction exchange.
     *
     * @return array
     */
    protected function getCheckboxAgreements(): array
    {
        $directionId = $this->id;

        $agreements = CheckboxAgreement::query()
            ->with(['page'])
            ->where('status', true)
            ->where(function ($query) use ($directionId) {
                $query
                    // Вариант 1: режим "all_except" (или null для старых записей) — для всех, кроме исключённых
                    ->where(function ($q) use ($directionId) {
                        $q->where(function ($qq) {
                            $qq->whereNull('apply_mode')
                                ->orWhere('apply_mode', 'all_except');
                        })
                            ->whereDoesntHave('excludedDirections', function ($qq) use ($directionId) {
                                $qq->where('direction_exchange.id', $directionId);
                            });
                    })
                    // Вариант 2: режим "only_selected" — только для явно разрешённых направлений
                    ->orWhere(function ($q) use ($directionId) {
                        $q->where('apply_mode', 'only_selected')
                            ->whereHas('allowedDirections', function ($qq) use ($directionId) {
                                $qq->where('direction_exchange.id', $directionId);
                            });
                    });
            })
            ->orderBy('sorting')
            ->get();

        return $agreements
            ->map(function ($agreement) {
                $defaultLink = $agreement->page_type === 'manual'
                    ? $agreement->link
                    : ($agreement->page ? '/pages/' . $agreement->page->page_slug : '');

                $parsedLabel = $this->parseLabelLinks($agreement->label);

                return [
                    'key'          => $agreement->key_id,
                    'label'        => $parsedLabel['label'],
                    'description'  => $agreement->description,
                    'checked'      => $agreement->checked,
                    'required'     => $agreement->required,
                    'text_error'   => $agreement->text_error,
                    'link'         => $defaultLink,
                    'pages_links'  => $parsedLabel['pages_links'],
                    'fees'         => $parsedLabel['fees'],
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Парсинг текста на наличие меток страниц {page=slug_name} и комиссий {fee=value}.
     * Метки {link} не изменяются и обрабатываются отдельно на frontend.
     *
     * @param string $label
     *
     * @return array
     */
    protected function parseLabelLinks(string $label): array
    {
        $pagesLinks = [];
        $fees = [];

        preg_match_all('/\{page=([^}]+)\}(.*?)\{\/page\}/', $label, $pageMatches, PREG_SET_ORDER);
        foreach ($pageMatches as $match) {
            $slugOrUrl = $match[1];
            $isExternal = filter_var($slugOrUrl, FILTER_VALIDATE_URL);

            $url = $isExternal
                ? $slugOrUrl
                : Page::where('page_slug', $slugOrUrl)->value('page_slug');

            $pagesLinks[] = [
                'type' => $isExternal ? 'external' : 'internal',
                'slug_or_url' => $slugOrUrl,
                'url' => $url ? ($isExternal ? $url : '/pages/' . $url) : null,
            ];
        }

        preg_match_all('/\{fee=([-+]?[0-9]*\.?[0-9]+%?)\}(.*?)\{\/fee\}/', $label, $feeMatches, PREG_SET_ORDER);
        foreach ($feeMatches as $feeMatch) {
            $fees[] = [
                'value' => $feeMatch[1],
                'text' => trim($feeMatch[2]),
            ];
        }



        return [
            'label' => $label,
            'pages_links' => $pagesLinks,
            'fees' => $fees,
        ];
    }

    /**
     * SEO Phase D3A: unique metadata fallbacks when direction DB SEO fields are empty.
     *
     * @return array{title: string, description: string, keywords: string}
     */
    protected function getSeoPayload(): array
    {
        $fromName = $this->resolveCurrencySeoName($this->currency1);
        $toName = $this->resolveCurrencySeoName($this->currency2);
        $fromCode = trim((string) ($this->currency1?->designation_xml ?? ''));
        $toCode = trim((string) ($this->currency2?->designation_xml ?? ''));

        return [
            'title' => $this->resolveSeoField('seo_title', fn () => $this->buildFallbackSeoTitle($fromName, $toName)),
            'description' => $this->resolveSeoField('seo_description', fn () => $this->buildFallbackSeoDescription($fromName, $toName)),
            'keywords' => $this->resolveSeoField('seo_keywords', fn () => $this->buildFallbackSeoKeywords($fromName, $toName, $fromCode, $toCode)),
        ];
    }

    protected function resolveSeoField(string $field, callable $fallback): string
    {
        $dbValue = $this->getDbSeoField($field);
        if ($dbValue !== null) {
            return $dbValue;
        }

        return $fallback();
    }

    protected function getDbSeoField(string $field): ?string
    {
        $model = $this->resource;
        if (!is_object($model) || empty($model->id)) {
            return null;
        }

        if (method_exists($model, 'getTranslations')) {
            $translations = $model->getTranslations($field);
            if (is_array($translations)) {
                $locale = app()->getLocale();
                if ($this->isNonEmptySeoString($translations[$locale] ?? null)) {
                    return $this->normalizeSeoString((string) $translations[$locale]);
                }

                foreach ($translations as $value) {
                    if ($this->isNonEmptySeoString($value)) {
                        return $this->normalizeSeoString((string) $value);
                    }
                }
            }
        }

        $raw = $model->{$field} ?? null;
        if ($this->isNonEmptySeoString($raw)) {
            return $this->normalizeSeoString((string) $raw);
        }

        return null;
    }

    protected function isNonEmptySeoString(mixed $value): bool
    {
        if ($value === null || is_array($value)) {
            return false;
        }

        $normalized = $this->normalizeSeoString((string) $value);

        return $normalized !== '' && $normalized !== '[]' && $normalized !== '{}';
    }

    protected function normalizeSeoString(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
    }

    protected function resolveCurrencySeoName(?Currency $currency): string
    {
        if ($currency === null) {
            return '';
        }

        $code = trim((string) ($currency->designation_xml ?? ''));
        $paymentName = $this->normalizeSeoString((string) ($currency->payment?->name ?? ''));
        $isoCode = $this->normalizeSeoString((string) ($currency->code_currency?->name ?? ''));

        if ($paymentName !== '') {
            if ((int) ($currency->visible_code_currency ?? 0) === 1 && $isoCode !== '' && stripos($paymentName, $isoCode) === false) {
                return trim($paymentName . ' ' . $isoCode);
            }

            return $paymentName;
        }

        $techName = $this->normalizeSeoString((string) ($currency->tech_currency_name ?? ''));

        return $techName !== '' ? $techName : $code;
    }

    protected function buildFallbackSeoTitle(string $fromName, string $toName): string
    {
        $fromName = $fromName !== '' ? $fromName : 'crypto';
        $toName = $toName !== '' ? $toName : 'crypto';

        $title = sprintf('Exchange %s to %s', $fromName, $toName);

        if (mb_strlen($title) > 70) {
            $title = rtrim(mb_substr($title, 0, 67)) . '...';
        }

        return $title;
    }

    protected function buildFallbackSeoDescription(string $fromName, string $toName): string
    {
        $fromName = $fromName !== '' ? $fromName : 'crypto';
        $toName = $toName !== '' ? $toName : 'crypto';

        $description = sprintf(
            'Exchange %s to %s securely on Exswaping. Competitive rates, fast processing and reliable support.',
            $fromName,
            $toName
        );

        if (mb_strlen($description) > 160) {
            $description = rtrim(mb_substr($description, 0, 157)) . '...';
        }

        return $description;
    }

    protected function buildFallbackSeoKeywords(string $fromName, string $toName, string $fromCode, string $toCode): string
    {
        $fromLabel = $fromCode !== '' ? $fromCode : $fromName;
        $toLabel = $toCode !== '' ? $toCode : $toName;

        return implode(', ', array_unique(array_filter([
            $fromLabel,
            $toLabel,
            sprintf('exchange %s to %s', $fromLabel, $toLabel),
            'crypto exchange',
        ])));
    }
}
