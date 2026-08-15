<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Verifications;

use App\Http\Controllers\Controller;
use App\Services\Administrator\KycOps\KycOpsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Throwable;

/**
 * KYC Operations Center — Blade admin UI + JSON helpers.
 * Safe ops only; reuses KycOpsService / DiditKycService / resolver.
 */
final class KycOpsController extends Controller
{
    public function __construct(
        private readonly KycOpsService $ops,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filtersFromRequest($request);
        $cases = $this->ops->listCases($filters, (int) $request->query('per_page', 25));
        $dashboard = $this->ops->dashboard();

        return view('admin.kyc-ops.index', [
            'dashboard' => $dashboard,
            'cases' => $cases,
            'filters' => $filters,
            'tab' => 'overview',
        ]);
    }

    public function attention(Request $request): View
    {
        return view('admin.kyc-ops.attention', [
            'dashboard' => $this->ops->dashboard(),
            'items' => $this->ops->attentionItems(100),
            'tab' => 'attention',
        ]);
    }

    public function diagnostics(): View
    {
        return view('admin.kyc-ops.diagnostics', [
            'dashboard' => $this->ops->dashboard(),
            'diagnostics' => $this->ops->diagnostics(),
            'tab' => 'diagnostics',
        ]);
    }

    public function show(string $caseKey): View
    {
        try {
            $detail = $this->ops->caseDetail($caseKey);
        } catch (Throwable $e) {
            abort(404, 'Verification case not found');
        }

        return view('admin.kyc-ops.show', [
            'dashboard' => $this->ops->dashboard(),
            'detail' => $detail,
            'tab' => 'overview',
        ]);
    }

    public function reconcile(Request $request, string $caseKey): RedirectResponse
    {
        $adminId = (int) ($request->user()?->id ?? 0);
        try {
            $result = $this->ops->reconcile($caseKey, $adminId);
        } catch (Throwable $e) {
            return back()->with('error', 'Reconcile failed.');
        }

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function openSession(Request $request, string $caseKey): RedirectResponse
    {
        $adminId = (int) ($request->user()?->id ?? 0);
        try {
            $url = $this->ops->openSessionUrl($caseKey, $adminId);
        } catch (Throwable) {
            return back()->with('error', 'Session not available.');
        }

        if ($url === null) {
            return back()->with('error', 'No open provider session URL for this case.');
        }

        return redirect()->away($url);
    }

    public function exportAudit(Request $request, string $caseKey): Response
    {
        $adminId = (int) ($request->user()?->id ?? 0);
        try {
            $export = $this->ops->exportAudit($caseKey, $adminId);
        } catch (RuntimeException) {
            abort(404);
        }

        return response($export['content'], 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
        ]);
    }

    public function dashboardJson(): JsonResponse
    {
        return response()->json($this->ops->dashboard());
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'provider' => trim((string) $request->query('provider', '')),
            'status' => trim((string) $request->query('status', '')),
            'verified' => $request->query('verified', ''),
            'manual_only' => $request->boolean('manual_only'),
            'webhook_failures' => $request->boolean('webhook_failures'),
            'status_mismatch' => $request->boolean('status_mismatch'),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'sort' => trim((string) $request->query('sort', 'last_update')),
            'dir' => trim((string) $request->query('dir', 'desc')),
            'page' => max(1, (int) $request->query('page', 1)),
        ];
    }
}
