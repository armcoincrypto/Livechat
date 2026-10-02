<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TelegramOperator\TelegramOperatorAuthService;
use App\Services\TelegramOperator\TelegramOperatorDestinationHealth;
use Illuminate\Console\Command;

/**
 * Read-only Telegram operator destination authorization health.
 * Does not send Telegram messages; writes JSONL/state via DestinationHealth.
 */
final class TelegramOperatorDestinationHealthCommand extends Command
{
    protected $signature = 'telegram:operator-destination-health
        {--format=json : json|summary}
        {--emit-empty : Force AUTH_EMPTY recording when authorized destinations are empty}';

    protected $description = 'Check Telegram operator destination authorization (Spatie/links); no Telegram send.';

    public function handle(
        TelegramOperatorAuthService $auth,
        TelegramOperatorDestinationHealth $health,
    ): int {
        $snapshot = $health->snapshot($auth);

        if ((bool) $this->option('emit-empty')
            && $snapshot['classification'] === TelegramOperatorDestinationHealth::CLASS_AUTHORIZATION_EMPTY
        ) {
            $health->recordAuthEmpty([
                'permission' => $snapshot['permission'],
                'links' => $auth->links(),
                'authorized_ids' => [],
                'configured_link_count' => $snapshot['configured_link_count'],
                'authorized_count' => 0,
                'classification' => TelegramOperatorDestinationHealth::CLASS_AUTHORIZATION_EMPTY,
            ]);
            $snapshot = $health->snapshot($auth);
        }

        if ((string) $this->option('format') === 'summary') {
            $this->line(sprintf(
                'state=%s classification=%s links=%d authorized=%d empty_total=%d permission=%s',
                $snapshot['state'],
                $snapshot['classification'],
                $snapshot['configured_link_count'],
                $snapshot['authorized_count'],
                $snapshot['telegram_operator_auth_empty_total'],
                $snapshot['permission'],
            ));
        } else {
            $this->line(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $snapshot['classification'] === TelegramOperatorDestinationHealth::CLASS_AUTHORIZATION_EMPTY
            ? self::FAILURE
            : self::SUCCESS;
    }
}
