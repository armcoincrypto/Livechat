<?php
declare(strict_types=1);

namespace iEXPackages\AuthAudit\Services;

use App\Services\DeviceDetectorService;
use iEXPackages\AuthAudit\Context\DeviceIdFactory;
use iEXPackages\AuthAudit\DTO\AuditData;
use iEXPackages\AuthAudit\Jobs\EnrichAuditEventJob;
use iEXPackages\AuthAudit\Models\AuthEvent;
use Illuminate\Database\Eloquent\Model;

final class AuditService
{
    public function __construct(
        private readonly DeviceDetectorService $deviceDetector,
        private readonly DeviceIdFactory $deviceIdFactory,
        private readonly AuditContextBuilder $contextBuilder,
    ) {}

    public function log(AuditData $data): AuthEvent
    {
        $r = request();

        $ip = $data->ip ?? $r->ip();
        $ua = $data->userAgent ?? $r->userAgent();

        $deviceInfo = $this->deviceDetector->getInfo();

        $deviceId = $this->deviceIdFactory->make(
            $ua,
            $r->header('accept-language'),
            $r->header('sec-ch-ua-platform'),
        );

        $userId = null;
        $userEmail = null;

        if ($data->user instanceof Model) {
            $userId = (int) $data->user->getKey();
            $userEmail = (string) $data->user->getAttribute('email');
        }

        $email = $data->email ?? $userEmail;

        $event = new AuthEvent();
        $event->fill([
            'user_id' => $userId,
            'email' => $email ?: null,
            'guard' => $data->guard,
            'channel' => $data->channel,

            'event' => $data->event->value,
            'result' => $data->result->value,
            'reason_code' => $data->reasonCode,
            'message' => $data->message,

            'ip' => $ip,
            'ip_prev' => $data->ipPrev,
            'user_agent' => $ua,

            'device_id' => $this->deviceIdFactory->short($deviceId),

            'browser' => $deviceInfo['browser'] ?? null,
            'os' => $deviceInfo['os'] ?? null,
            'device' => $deviceInfo['device_type'] ?? null,

            'session_id' => $data->meta['session_id'] ?? (method_exists($r, 'session') ? $r->session()->getId() : null),
            'session_prev_id' => $data->meta['session_prev_id'] ?? null,

            'meta' => $this->buildMeta($data, $deviceInfo),
        ]);

        $event->save();

        $event->is_new_device = $this->isNewDevice($event);
        $event->save();

        dispatch(new EnrichAuditEventJob((int) $event->id))->onQueue('low');

        return $event;
    }

    private function buildMeta(AuditData $data, array $deviceInfo): array
    {
        $r = request();

        // Единый Audit Context: request/device + поверх накладываем $data->meta
        return $this->contextBuilder->build(
            r: $r,
            deviceInfo: $deviceInfo,
            extra: $data->meta,
        );
    }

    private function isNewDevice(AuthEvent $event): bool
    {
        if (! $event->user_id || ! $event->device_id) {
            return false;
        }

        return ! AuthEvent::query()
            ->where('user_id', $event->user_id)
            ->where('device_id', $event->device_id)
            ->where('id', '<>', $event->id)
            ->exists();
    }
}
