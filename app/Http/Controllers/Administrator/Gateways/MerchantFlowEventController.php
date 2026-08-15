<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Gateways;

use App\Http\Controllers\Controller;
use iEXPackages\Payments\Callback\MerchantFlowEventFormatter;
use iEXPackages\Payments\Callback\MerchantFlowEventReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MerchantFlowEventController extends Controller
{
    public function index(
        Request $request,
        MerchantFlowEventReader $reader,
        MerchantFlowEventFormatter $formatter
    ): JsonResponse {
        $perPage = max(1, min(200, (int) $request->query('per_page', 50)));
        $page = $reader->paginate($request, $perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($row) => $formatter->format($row))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
                'per_page'     => $page->perPage(),
            ],
        ]);
    }

    public function task(
        int $taskId,
        Request $request,
        MerchantFlowEventReader $reader,
        MerchantFlowEventFormatter $formatter
    ): JsonResponse {
        $perPage = max(1, min(200, (int) $request->query('per_page', 50)));
        $page = $reader->paginateForTask($taskId, $request, $perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($row) => $formatter->format($row))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
                'per_page'     => $page->perPage(),
            ],
        ]);
    }

    public function merchant(
        int $merchantId,
        Request $request,
        MerchantFlowEventReader $reader,
        MerchantFlowEventFormatter $formatter
    ): JsonResponse {
        $perPage = max(1, min(200, (int) $request->query('per_page', 50)));
        $page = $reader->paginateForMerchant($merchantId, $request, $perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($row) => $formatter->format($row))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
                'per_page'     => $page->perPage(),
            ],
        ]);
    }
}
