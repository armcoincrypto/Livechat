<?php
namespace iEXPackages\Rare\ProxiesFilter;

use GuzzleHttp\Client;
use UnexpectedValueException;
use GuzzleHttp\Exception\GuzzleException;

class ProxiesFilter
{
    public const IP_VERSION_4 = 1 << 0;

    public const IP_VERSION_6 = 1 << 1;

    public const IP_VERSION_ANY = self::IP_VERSION_4 | self::IP_VERSION_6;


    /**
     * Retrieve Cloudflare proxies list.
     *
     * @param int $type
     *
     * @return array
     * @throws GuzzleException
     */
    public function load(int $type = self::IP_VERSION_ANY) : array
    {
        $sources = iEXSetting('proxiesfilter_sources_ddos');

        if(empty($sources))
            return [];

        if(\Str::lower($sources) == 'cloudflare') {
            $proxies = [];
            if ((bool) ($type & self::IP_VERSION_4)) {
                $proxies = $this->retrieve('ips-v4');
            }

            if ((bool) ($type & self::IP_VERSION_6)) {
                $proxies = array_merge($proxies, $this->retrieve('ips-v6'));
            }

            \File::put(storage_path('/app/iexexchanger/proxies/cloudflare.txt'), implode(','.PHP_EOL, $proxies));


            return $proxies;
        }

        $service = \File::get(storage_path('/app/iexexchanger/proxies/'.\Str::lower($sources.'.txt')));;
        return explode(',', remove_all_spaces($service));
    }


    /**
     * Retrieve requested proxy list by name.
     *
     * @param  string $name requet name
     *
     * @return array
     * @throws GuzzleException
     */
    protected function retrieve($name) : array
    {
        try {
            $client = new Client(['base_uri' => 'https://www.cloudflare.com/']);
            $response = $client->request('GET', $name);
        } catch (\Exception $e) {
            throw new UnexpectedValueException('Failed to load trust proxies from Cloudflare server.', 1, $e);
        }

        if ($response->getStatusCode() != 200) {
            throw new UnexpectedValueException('Failed to load trust proxies from Cloudflare server.');
        }

        return array_filter(explode("\n", (string) $response->getBody()));
    }
}
