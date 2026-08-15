<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Contracts;

/**
 * BestChangeHttpClientInterface
 *
 * Контракт "сырого" доступа к BestChange API v2.
 *
 * Важные принципы:
 * - Методы возвращают данные как пришло от API (массивы).
 * - Форматирование (keyBy id, full_name, default_code) и кэширование делаются выше (CatalogRepository / RatesConnection).
 * - fetchRatesBatch() обязан батчить пары по 500.
 */
interface BestChangeHttpClientInterface
{
    /** @return string[] */
    public function fetchLangs(): array;

    /** @return array<int, array{id:int,name:string}> */
    public function fetchGroups(string $lang): array;

    /** @return array<int, array{id:int,name:string,code?:string,rank?:int}> */
    public function fetchCountries(string $lang): array;

    /** @return array<int, array{id:int,name:string,code?:string,country:int,rank?:int}> */
    public function fetchCities(string $lang): array;

    /** @return array<int, array{id:int,name:string,code?:string,urlname?:string,viewname?:string,crypto?:bool,cash?:bool,ps?:int,group?:int}> */
    public function fetchCurrencies(string $lang): array;

    /** @return array<int, array{id:int,name:string,langs?:array<int,string>,urls?:array<string,string>,pages?:array<string,string>,reserve?:int|float|string,reviews?:array<string,int>,rating?:int,active?:bool}> */
    public function fetchChangers(string $lang): array;

    /**
     * @param string[] $pairKeys Пример: ["42-93","93-91-1"]
     * @return array<string, array<int, array{changer:int,rate:mixed,rankrate?:mixed,reserve?:mixed,inmin?:mixed,inmax?:mixed,marks?:array<int,string>,extra?:array<string,mixed>}>>
     */
    public function fetchRatesBatch(array $pairKeys): array;

    /**
     * GET /presences/{from}-{to} или /presences/{from}-{to}-{city}
     *
     * @return array{pair?:string,best?:string|float,count?:int}|null
     */
    public function fetchPresence(string $pairKey): ?array;
}
