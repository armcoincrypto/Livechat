<?php
namespace iEXPackages\Rare\ProxiesFilter\Commands;


use GuzzleHttp\Exception\GuzzleException;
use iEXPackages\Rare\ProxiesFilter\ProxiesFilter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Output\OutputInterface;

class Reload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proxyfilter:reload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reload trust proxies IPs and store in cache.';

    /**
     * Execute the console command.
     *
     * @return void
     * @throws GuzzleException
     */
    public function handle()
    {
        $loader = new ProxiesFilter();

        Cache::forever('proxyfilter.allowed', $loader->load());
        $this->info('Cloudflare\'s IP blocks have been reloaded.', OutputInterface::VERBOSITY_VERBOSE);
    }
}
