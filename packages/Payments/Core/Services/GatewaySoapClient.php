<?php

namespace iEXPackages\Payments\Core\Services;

use iEXPackages\Payments\Core\Sandbox\Contracts\SandboxManagerInterface;
use iEXPackages\Payments\Logging\GatewayLogger;
use iEXPackages\Payments\Logging\GatewayLogContext;
use SoapClient;
use SoapFault;
use Throwable;

final class GatewaySoapClient
{
    public function __construct(
        private readonly GatewayLogger $logger,
        private readonly SandboxManagerInterface $sandbox,
    ) {}

    /**
     * Выполняет SOAP-вызов с логированием в payment_gateway_logs.
     *
     * @param array{
     *   wsdl: string,
     *   options?: array,
     *   timeout?: int,
     *   cache_wsdl?: int,
     * } $spec
     *
     * @return array<string,mixed>
     */
    public function call(
        array $spec,
        string $method,
        array $payload,
        GatewayLogContext $ctx,
    ): array {
        $wsdl = (string) ($spec['wsdl'] ?? '');
        if ($wsdl === '') {
            throw new \InvalidArgumentException('GatewaySoapClient: wsdl is required.');
        }

        $options = is_array($spec['options'] ?? null) ? $spec['options'] : [];

        // Нормальные дефолты
        $options += [
            'trace'      => true, // чтобы достать __getLastRequest/__getLastResponse
            'exceptions' => true,
        ];

        // SANDBOX/REPLAY (если включен)
        $replayKey = $this->sandbox->makeKey($ctx, 'soap', $wsdl . '#' . $method, $payload);

        if ($this->sandbox->shouldReplay($ctx)) {
            $replay = $this->sandbox->getReplay($replayKey);
            if (is_array($replay)) {
                $this->writeLog($ctx, [
                    'http_method'     => 'SOAP',
                    'url'             => $wsdl . '#' . $method,
                    'response_status' => 200,
                    'duration_ms'     => 0,
                    'request_headers' => [],
                    'request_body'    => $payload,
                    'response_body'   => $replay,
                    'is_sandbox'      => 1,
                    'replay_key'      => $replayKey,
                ]);

                return $replay;
            }
        }

        $started = microtime(true);

        try {
            $client = new SoapClient($wsdl, $options);

            // Вызов
            $result = $client->{$method}($payload);

            $durationMs = (int) round((microtime(true) - $started) * 1000);

            // Пробуем достать «сырые» SOAP request/response
            $rawRequest  = method_exists($client, '__getLastRequest') ? (string) $client->__getLastRequest() : null;
            $rawResponse = method_exists($client, '__getLastResponse') ? (string) $client->__getLastResponse() : null;

            // Нормализуем результат (stdClass -> array)
            $normalized = $this->normalizeSoapResult($result);

            $this->sandbox->storeReplay(
                ctx: $ctx,
                key: $replayKey,
                method: 'soap',
                url: $wsdl . '#' . $method,
                requestHeaders: [],
                requestBody: $payload,
                responseBody: $normalized,
                httpStatus: 200
            );

            $this->writeLog($ctx, [
                'http_method'     => 'SOAP',
                'url'             => $wsdl . '#' . $method,
                'response_status' => 200,
                'duration_ms'     => $durationMs,
                'request_headers' => [],
                'request_body'    => [
                    'payload' => $payload,
                    'raw'     => $rawRequest,
                ],
                'response_body'   => [
                    'data' => $normalized,
                    'raw'  => $rawResponse,
                ],
                'is_sandbox'      => 0,
                'replay_key'      => $replayKey,
            ]);

            return $normalized;

        } catch (SoapFault $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);

            $this->writeLog($ctx, [
                'http_method'   => 'SOAP',
                'url'           => $wsdl . '#' . $method,
                'duration_ms'   => $durationMs,
                'request_body'  => $payload,
                'error_class'   => $e::class,
                'error_message' => $e->getMessage(),
                'is_sandbox'    => 0,
                'replay_key'    => $replayKey,
            ]);

            throw $e;

        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);

            $this->writeLog($ctx, [
                'http_method'   => 'SOAP',
                'url'           => $wsdl . '#' . $method,
                'duration_ms'   => $durationMs,
                'request_body'  => $payload,
                'error_class'   => $e::class,
                'error_message' => $e->getMessage(),
                'is_sandbox'    => 0,
                'replay_key'    => $replayKey,
            ]);

            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private function normalizeSoapResult(mixed $result): array
    {
        // Стандартный приём: object->json->array
        return json_decode(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true) ?: [];
    }

    private function writeLog(GatewayLogContext $ctx, array $payload): void
    {
        // ВАЖНО: columns is_sandbox/replay_key должны существовать в таблице,
        // иначе либо добавь миграцией, либо не передавай их.
        $this->logger->write([
            ...$ctx->toArray(),
            ...$payload,
        ]);
    }
}
