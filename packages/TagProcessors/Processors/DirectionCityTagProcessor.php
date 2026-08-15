<?php

declare(strict_types=1);

namespace iEXPackages\TagProcessors\Processors;

use App\Models\DirectionExchangeCity;
use iEXPackages\TagProcessors\Contracts\DescribableTagProcessorInterface;
use iEXPackages\TagProcessors\Support\TagDefinition;
use Illuminate\Http\Request;

/**
 * Процессор тегов для городов конкретных направлений (DirectionExchangeCity).
 *
 * Примеры тегов:
 *  - [city_12_IVFR_add_comm]
 *  - [city_12_IVFR_profit]
 *  - [city_12_IVFR_profit_s]
 *  - [city_12_IVFR_bid_value]
 *
 *  - [city_45_add_comm]
 *  - [city_45_profit]
 *  - [city_45_profit_s]
 *  - [city_45_bid_value]
 */
class DirectionCityTagProcessor extends AbstractTagProcessor implements DescribableTagProcessorInterface
{
    /**
     * Кеш TagDefinition, чтобы не дёргать базу много раз.
     *
     * @var TagDefinition[]|null
     */
    protected ?array $cachedDefinitions = null;

    /**
     * Описания шаблонов тегов этого процессора (для каталога).
     *
     * @return TagDefinition[]
     */
    public function definitions(): array
    {
        if ($this->cachedDefinitions !== null) {
            return $this->cachedDefinitions;
        }

        $group = 'direction_city';

        $this->cachedDefinitions = [
            new TagDefinition(
                key: '[city_{direction_id}_{city_code}_{field}]',
                name: 'Город направления по коду',
                description: 'Шаблон: [city_{id_direction_exchange}_{designation_xml}_{field}], где field ∈ {add_comm, profit, profit_s, bid_value}.',
                example: '[city_12_IVFR_add_comm]',
                group: $group,
                deprecated: false,
                resolver: static fn () => ''
            ),
            new TagDefinition(
                key: '[city_{city_id}_{field}]',
                name: 'Город по ID записи DirectionExchangeCity',
                description: 'Шаблон: [city_{DirectionExchangeCity.id}_{field}], где field ∈ {add_comm, profit, profit_s, bid_value}.',
                example: '[city_45_profit_s]',
                group: $group,
                deprecated: false,
                resolver: static fn () => ''
            ),
        ];

        return $this->cachedDefinitions;
    }

    /**
     * Собираем map [tag => resolver] из TagDefinition.
     *
     * @return array<string, \Closure>
     */
    protected function tagHandlers(): array
    {
        $handlers = [];

        foreach ($this->definitions() as $definition) {
            if ($definition->resolver instanceof \Closure) {
                $handlers[$definition->key] = $definition->resolver;
            }
        }

        return $handlers;
    }

    /**
     * Динамическая обработка тегов [city_*], без предварительной регистрации всех комбинаций.
     *
     * Поддерживаются форматы:
     *  - [city_{direction_id}_{city_code}_{field}]
     *  - [city_{city_id}_{field}]
     *
     * @param string       $text
     * @param mixed        $data   не используется для фильтрации, здесь только текст
     * @param Request|null $request
     *
     * @return string
     */
    public function processDynamicTags(string $text, mixed $data, ?Request $request): string
    {
        if ($text === '' || !str_contains($text, '[city_')) {
            return $text;
        }

        // Ищем все вхождения [city_...]
        $pattern = '/\[city_([^\]]+)\]/u';

        if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return $text;
        }

        $allowedFields = ['add_comm', 'profit', 'profit_s', 'bid_value'];

        // what to load
        $cityIds       = []; // список ID DirectionExchangeCity
        $dirCityCodes  = []; // [direction_id][] = city_code из тега
        // что именно надо заменить
        $tagDescriptors = []; // каждый тег: исходная строка + тип + поле

        foreach ($matches as $match) {
            $fullTag = $match[0]; // "[city_38465_bid_value]"
            $body    = $match[1]; // "38465_bid_value" или "16051_MSK_bid_value"

            $field     = null;
            $remaining = null;

            // Определяем поле по суффиксу (так обрабатываются поля с подчёркиванием: bid_value, profit_s и т.п.)
            foreach ($allowedFields as $allowedField) {
                $suffix = '_' . $allowedField;

                if (!str_ends_with($body, $suffix)) {
                    continue;
                }

                // Отрезаем суффикс "_{field}", остаётся часть с id и, возможно, кодом города
                $remaining = substr($body, 0, -strlen($suffix));
                $field     = $allowedField;
                break;
            }

            // Если поле не найдено или нет префикса с id — пропускаем тег
            if ($field === null || $remaining === null || $remaining === '') {
                continue;
            }

            // Если в оставшейся части есть подчёркивание, считаем что формат: {direction_id}_{city_code}
            if (str_contains($remaining, '_')) {
                [$directionIdRaw, $cityCode] = explode('_', $remaining, 2);

                $directionId = (int) $directionIdRaw;
                if ($directionId <= 0) {
                    continue;
                }

                $cityCode = (string) $cityCode;
                if ($cityCode === '') {
                    continue;
                }

                $dirCityCodes[$directionId][] = $cityCode;

                $tagDescriptors[] = [
                    'tag'          => $fullTag,
                    'kind'         => 'direction_city',
                    'direction_id' => $directionId,
                    'city_code'    => $cityCode,
                    'field'        => $field,
                ];
            } else {
                // Иначе считаем, что формат: {city_id}_{field}
                $cityId = (int) $remaining;
                if ($cityId <= 0) {
                    continue;
                }

                $cityIds[] = $cityId;

                $tagDescriptors[] = [
                    'tag'     => $fullTag,
                    'kind'    => 'city',
                    'city_id' => $cityId,
                    'field'   => $field,
                ];
            }
        }

        if ($tagDescriptors === []) {
            return $text;
        }

        // Убираем дубликаты
        $cityIds = array_values(array_unique($cityIds));
        foreach ($dirCityCodes as $directionId => $codes) {
            $dirCityCodes[$directionId] = array_values(array_unique($codes));
        }

        if ($cityIds === [] && $dirCityCodes === []) {
            return $text;
        }

        // Собираем запрос по нужным ID/кодам
        $query = DirectionExchangeCity::query()
            ->with(['city', 'profile'])
            ->where('status', 1);

        $query->where(function ($q) use ($cityIds, $dirCityCodes) {
            if ($cityIds !== []) {
                $q->orWhereIn('id', $cityIds);
            }

            if ($dirCityCodes !== []) {
                $directionIds = array_keys($dirCityCodes);

                $codes = [];
                foreach ($dirCityCodes as $codesByCity) {
                    $codes = array_merge($codes, $codesByCity);
                }
                $codes = array_values(array_unique($codes));

                $q->orWhere(function ($qq) use ($directionIds, $codes) {
                    $qq->whereIn('id_direction_exchange', $directionIds)
                        ->whereHas('city', function ($qc) use ($codes) {
                            $qc->whereIn('designation_xml', $codes);
                        });
                });
            }
        });

        $cityPrices = $query->get();

        if ($cityPrices->isEmpty()) {
            return $text;
        }

        $replacements = [];

        // Индексируем найденные записи для быстрого доступа
        $byId = [];
        $byDirectionAndDbCode = []; // [direction_id][lower(designation_xml)] = model


        foreach ($cityPrices as $cityPrice) {
            /** @var \App\Models\DirectionExchangeCity $cityPrice */
            $directionId = (int) ($cityPrice->id_direction_exchange ?? 0);
            $cityId      = (int) ($cityPrice->id ?? 0);
            $cityModel   = $cityPrice->city ?? null;

            if ($cityId > 0) {
                $byId[$cityId] = $cityPrice;
            }

            if ($cityModel !== null && $directionId > 0) {
                $dbCode = (string) ($cityModel->designation_xml ?? '');
                if ($dbCode !== '') {
                    $key = strtolower($dbCode);
                    $byDirectionAndDbCode[$directionId][$key] = $cityPrice;
                }
            }
        }



        // Строим подстановки, используя исходные строки тегов
        foreach ($tagDescriptors as $descriptor) {
            $tag   = $descriptor['tag'];
            $field = $descriptor['field'];

            if (!in_array($field, $allowedFields, true)) {
                continue;
            }

            $model = null;


            if ($descriptor['kind'] === 'city') {
                $cityId = $descriptor['city_id'] ?? null;
                if ($cityId !== null && isset($byId[$cityId])) {
                    $model = $byId[$cityId];
                }

            } elseif ($descriptor['kind'] === 'direction_city') {
                $directionId = $descriptor['direction_id'] ?? null;
                $cityCode    = $descriptor['city_code'] ?? null;

                if ($directionId !== null && $cityCode !== null) {
                    $key = strtolower((string) $cityCode);
                    if (isset($byDirectionAndDbCode[$directionId][$key])) {
                        $model = $byDirectionAndDbCode[$directionId][$key];
                    }
                }
            }

            if ($model === null) {
                continue;
            }

            // Учитываем профиль для add_comm / profit / profit_s
            $profile = $model->profile ?? null;

            if ($field === 'profit') {
                $value = $this->resolveEffectiveProfitValue(
                    $model->getAttribute('profit'),
                    $profile?->profit
                );
            } elseif ($field === 'profit_s') {
                $value = $this->resolveEffectiveProfitValue(
                    $model->getAttribute('profit_s'),
                    $profile?->profit_s
                );
            } elseif ($field === 'add_comm') {
                $value = $this->resolveEffectiveAddCommValue(
                    $model->getAttribute('add_comm'),
                    $profile?->add_comm
                );
            } else {
                // Для остальных полей используем исходное значение модели (bid_value и т.п.)
                $value = $model->getAttribute($field);
            }

            // Даже если значение null или пустое — подставляем пустую строку,
            // чтобы тег не оставался в тексте.
            $replacements[$tag] = $value !== null ? (string) $value : '';
        }

        // Дополнительные простые теги [city] и [country] на основе первой найденной модели
        if ((str_contains($text, '[city]') || str_contains($text, '[country]')) && (!empty($byId) || !empty($byDirectionAndDbCode))) {
            $primaryModel = null;

            // Пытаемся взять модель по city_id из tagDescriptors
            foreach ($tagDescriptors as $descriptor) {
                if (($descriptor['kind'] ?? null) === 'city') {
                    $cityId = $descriptor['city_id'] ?? null;
                    if ($cityId !== null && isset($byId[$cityId])) {
                        $primaryModel = $byId[$cityId];
                        break;
                    }
                }
            }

            // Если не нашли, пробуем по direction_id + city_code
            if ($primaryModel === null) {
                foreach ($tagDescriptors as $descriptor) {
                    if (($descriptor['kind'] ?? null) === 'direction_city') {
                        $directionId = $descriptor['direction_id'] ?? null;
                        $cityCode    = $descriptor['city_code'] ?? null;
                        if ($directionId !== null && $cityCode !== null) {
                            $key = strtolower((string) $cityCode);
                            if (isset($byDirectionAndDbCode[$directionId][$key])) {
                                $primaryModel = $byDirectionAndDbCode[$directionId][$key];
                                break;
                            }
                        }
                    }
                }
            }

            if ($primaryModel !== null) {
                $cityModel   = $primaryModel->city ?? null;
                $cityName    = $cityModel?->name ?? '';
                $countryName = $cityModel?->country?->value ?? '';

                if (str_contains($text, '[city]')) {
                    $replacements['[city]'] = $cityName;
                }
                if (str_contains($text, '[country]')) {
                    $replacements['[country]'] = $countryName;
                }
            }
        }

        if ($replacements === []) {
            return $text;
        }

        return strtr($text, $replacements);
    }

    /**
     * Пустое / нулевое значение прибыли, при котором надо переключаться на профиль.
     */
    protected function isEmptyProfitValue(mixed $value): bool
    {
        return $value === null
            || $value === ''
            || $value === 0
            || $value === '0'
            || $value === 0.0;
    }

    /**
     * Универсальная логика для прибыли: city -> profile -> null.
     */
    protected function resolveEffectiveProfitValue(mixed $cityValue, mixed $profileValue): ?string
    {
        if (! $this->isEmptyProfitValue($cityValue)) {
            return (string) $cityValue;
        }

        if (! $this->isEmptyProfitValue($profileValue)) {
            return (string) $profileValue;
        }

        return null;
    }

    /**
     * Эффективное значение add_comm: сначала значение на уровне города,
     * если оно непустое (''/null/0/0.0 считаются пустыми), иначе значение из профиля.
     */
    protected function resolveEffectiveAddCommValue(mixed $cityValue, mixed $profileValue): ?string
    {
        $cityRaw = $cityValue !== null ? trim((string) $cityValue) : '';
        if ($cityRaw !== '' && $cityRaw !== '0' && $cityRaw !== '0.0') {
            return $cityRaw;
        }

        $profileRaw = $profileValue !== null ? trim((string) $profileValue) : '';
        if ($profileRaw !== '' && $profileRaw !== '0' && $profileRaw !== '0.0') {
            return $profileRaw;
        }

        return null;
    }
}
