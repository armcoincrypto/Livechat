<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Services\Analytics\AttributionFeatures;
use App\Services\Analytics\AttributionIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AttributionIngestController
{
    public function store(Request $request, AttributionIngestService $ingest): JsonResponse
    {
        if (! AttributionFeatures::ingestEnabled()) {
            return response()->json(['ok' => true, 'disabled' => true]);
        }

        $payload = $request->only([
            'public_session_id',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'referrer',
            'landing_path',
            'locale',
            'device_class',
            'touch',
        ]);

        $result = $ingest->ingest($payload);

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
        ]);
    }
}
