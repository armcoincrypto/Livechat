<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Abcex\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

final class OptionsRequest extends AbstractRequest
{
    public function getData(): array
    {
        $method = (string) $this->getParameter('method', '');
        if ($method === '') {
            throw new InvalidArgumentException('OptionsRequest: parameter "method" is required.');
        }

        // сюда можно также добавить дополнительные параметры:
        // wallet_unique_id, network, etc.
        return [
            'method' => $method,
            'params' => (array) $this->getParameter('params', []),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $method = $data['method'];
        $params = $data['params'];

        // Вариант A: маппинг method -> endpoint внутри шлюза
        $result = $this->callOptionsApi($method, $params);

        $options = $this->mapUserCoinCurrenciesOptions($result);

        return $this->response = new OptionsResponse(
            $this,
            $options,
            $data
        );
    }

    /**
     * Здесь ты решаешь, какой endpoint дергать для "getCurrencies" и т.д.
     */
    protected function callOptionsApi(string $method, array $params = []): array
    {
        return match ($method) {
            'getCurrencies' => $this->sendRequest('get', '/api/v1/wallets/balances', $params, 'asJson'),
            // 'getNetworks' => $this->callApi('get', '/api/v1/networks', $params, 'asJson'),
            default => throw new InvalidArgumentException("OptionsRequest: unsupported method [{$method}]"),
        };
    }

    /**
     * Преобразует ответ API в options-массив для select: [id => label].
     *
     * Ожидаемый формат входа:
     *  [
     *    'data' => [
     *      ['id'=>..., 'currencyId'=>..., 'type'=>'user', 'isCoin'=>1, ...],
     *      ...
     *    ]
     *  ]
     */
    protected function mapUserCoinCurrenciesOptions(array $response): array
    {
        $data = $response['data'] ?? null;


        if (!is_array($data) || $data === []) {
            return [];
        }

        $options = [];


        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            // type должен быть и равен 'user'
            $type = $item['type'] ?? null;
            if ($type !== 'referral-program') {
                continue;
            }

            $isCoin = (int) ($item['isCoin'] ?? 0);
            if ($isCoin !== 1) {
                continue;
            }

            // id обязателен и должен быть числом/строкой
            $id = $item['id'] ?? null;
            if ($id === null || $id === '' || (!is_numeric($id) && !is_string($id))) {
                continue;
            }

            // currencyId желательно, но если нет — можно не падать
            $currencyId = $item['currencyId'] ?? null;

            $idStr = (string) $id;
            $curStr = ($currencyId === null || $currencyId === '') ? '?' : (string) $currencyId;

            $options[$idStr] = '[' . $curStr . '] - [' . $idStr . ']';
        }

        if ($options === []) {
            return [];
        }

        // сортируем по id (как ты делал sortBy key)
        // если id числовые — сортируем численно, иначе лексикографически
        $allNumeric = true;
        foreach (array_keys($options) as $k) {
            if (!is_numeric($k)) {
                $allNumeric = false;
                break;
            }
        }

        if ($allNumeric) {
            uksort($options, static fn ($a, $b) => (int)$a <=> (int)$b);
        } else {
            ksort($options, SORT_NATURAL);
        }

        return $options;
    }
}
