<?php

declare(strict_types=1);

namespace App\Services\Certification;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * All seven controls must pass. Any miss → CERTIFICATION_REQUEST=REJECTED.
 */
final class CertificationGate
{
    public const REJECTED = 'CERTIFICATION_REQUEST=REJECTED';

    /**
     * @return array{allowed:bool, reason:string}
     */
    public function evaluate(?Request $request = null): array
    {
        if (! (bool) config('exs_order_certification.enabled', false)) {
            return $this->deny('flag_disabled');
        }

        $context = app(CertificationContext::class);
        if (! $context->isActive()) {
            $header = (string) config('exs_order_certification.request_header');
            $marked = $request instanceof Request
                && (string) $request->headers->get($header, '') === '1';
            if (! $marked) {
                return $this->deny('server_marker_missing');
            }
        }

        if (! $this->operatorContext()) {
            return $this->deny('operator_context_missing');
        }

        $expected = (string) config('exs_order_certification.token', '');
        if ($expected === '' || strlen($expected) < 16) {
            return $this->deny('token_not_configured');
        }

        $provided = $context->isActive()
            ? $context->token()
            : (string) ($request?->headers->get((string) config('exs_order_certification.token_header'), '') ?? '');
        if (! hash_equals($expected, $provided)) {
            return $this->deny('wrong_token');
        }

        $email = $context->isActive()
            ? $context->email()
            : strtolower(trim((string) ($request?->input('email') ?? '')));
        if (! $this->isQaEmail($email)) {
            return $this->deny('non_qa_email');
        }

        if ((string) config('exs_order_certification.provider') !== 'INTERNAL_FAKE') {
            return $this->deny('provider_not_internal_fake');
        }

        return ['allowed' => true, 'reason' => 'ok'];
    }

    public function allows(?Request $request = null): bool
    {
        return $this->evaluate($request)['allowed'] === true;
    }

    public function isQaEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        $suffix = strtolower((string) config('exs_order_certification.email_suffix', '@qa.exswaping.com'));

        return $email !== '' && str_ends_with($email, $suffix);
    }

    public function requestClaimsCertification(Request $request): bool
    {
        $header = (string) config('exs_order_certification.request_header');

        return (string) $request->headers->get($header, '') === '1';
    }

    private function operatorContext(): bool
    {
        if (app()->runningInConsole() && app(CertificationContext::class)->isActive()) {
            return true;
        }

        $user = Auth::user();
        if (! $user instanceof Authenticatable) {
            return false;
        }

        return method_exists($user, 'can') && $user->can('admin_orders_execute');
    }

    /**
     * @return array{allowed:bool, reason:string}
     */
    private function deny(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason];
    }
}
