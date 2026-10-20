<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Auth\OAuth\LegacyOAuthIdentityBackfill;
use Illuminate\Console\Command;

final class OAuthIdentitiesBackfillCommand extends Command
{
    protected $signature = 'auth:oauth-identities-backfill
        {--dry-run : Report only (default)}
        {--apply : Perform inserts}
        {--provider= : Limit to google|yandex}';

    protected $description = 'Idempotent backfill of legacy users.provider/provider_id into user_oauth_identities';

    public function handle(LegacyOAuthIdentityBackfill $backfill): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = !$apply || (bool) $this->option('dry-run');
        if ($apply) {
            $dryRun = false;
        }

        $provider = $this->option('provider');
        $provider = is_string($provider) && $provider !== '' ? $provider : null;

        $report = $backfill->run($dryRun, $provider);
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $report['aborted'] ? self::FAILURE : self::SUCCESS;
    }
}
