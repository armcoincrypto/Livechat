<?php

namespace App\Services;

use Jenssegers\Agent\Agent;

class DeviceDetectorService
{
    protected Agent $agent;

    public function __construct()
    {
        $this->agent = new Agent();
    }

    /**
     * Определить тип устройства (mobile / tablet / desktop)
     */
    public function getDeviceType(): string
    {
        if ($this->agent->isMobile()) {
            return 'mobile';
        }

        if ($this->agent->isTablet()) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Определить браузер
     */
    public function getBrowser(): string
    {
        return $this->agent->browser() ?: 'Unknown';
    }

    /**
     * Определить операционную систему
     */
    public function getOS(): string
    {
        return $this->agent->platform() ?: 'Unknown';
    }

    /**
     * Собрать полную информацию
     */
    public function getInfo(): array
    {
        return [
            'browser'     => $this->getBrowser(),
            'browser_ver' => $this->agent->version($this->getBrowser()),
            'device_type' => $this->getDeviceType(),
            'os'          => $this->getOS(),
            'os_ver'      => $this->agent->version($this->getOS()),
            'user_agent'  => request()->userAgent(),
        ];
    }
}
