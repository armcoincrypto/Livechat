<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Probe public /currencies.xml delivery timing and validity.
 */
final class RatesXmlDeliveryProbeCommand extends Command
{
    protected $signature = 'rates:xml-delivery-probe
        {--url=https://exswaping.com/currencies.xml : Public URL to probe}
        {--ipv4 : Force IPv4}
        {--ipv6 : Force IPv6}
        {--max-time=10 : Curl max-time seconds}
        {--format=json : json}';

    protected $description = 'Probe public currencies.xml TTFB/size/XML validity (read-only).';

    public function handle(): int
    {
        $url = (string) $this->option('url');
        $max = (int) $this->option('max-time');
        $tmp = tempnam(sys_get_temp_dir(), 'xmlprobe');
        $fmt = 'code=%{http_code}\n dns=%{time_namelookup}\n connect=%{time_connect}\n tls=%{time_appconnect}\n ttfb=%{time_starttransfer}\n total=%{time_total}\n size=%{size_download}\n ip=%{remote_ip}\n proto=%{http_version}\n';

        $cmd = ['curl', '-sS', '--max-time', (string) $max, '--connect-timeout', '5', '-o', $tmp, '-w', $fmt];
        if ((bool) $this->option('ipv4')) {
            $cmd[] = '-4';
        }
        if ((bool) $this->option('ipv6')) {
            $cmd[] = '-6';
        }
        $cmd[] = $url;

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        $metrics = [];
        foreach (explode("\n", trim((string) $stdout)) as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $metrics[trim($k)] = trim($v);
            }
        }

        $body = is_file($tmp) ? (string) file_get_contents($tmp) : '';
        @unlink($tmp);
        $items = substr_count($body, '<item>');
        $valid = str_contains($body, '<rates') && str_contains($body, '</rates>') && $items > 0;
        $hasBtcGel = (bool) preg_match('/<from>BTC<\/from>\s*<to>CARDGEL<\/to>/', $body);
        $hasTon = (bool) preg_match('/<from>TON<\/from>|<to>TON<\/to>/', $body);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'url' => $url,
            'curl_exit' => $code,
            'stderr' => trim((string) $stderr),
            'metrics' => $metrics,
            'items' => $items,
            'valid_xml' => $valid,
            'sample_btc_cardgel_present' => $hasBtcGel,
            'ton_exact_present' => $hasTon,
            'ok' => $code === 0
                && ($metrics['code'] ?? '') === '200'
                && (float) ($metrics['ttfb'] ?? 99) < 10
                && (int) ($metrics['size'] ?? 0) > 0
                && $valid,
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $payload['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
