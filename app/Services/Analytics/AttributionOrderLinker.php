<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\SessionAttribution;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Fail-open order ↔ attribution linker (Batch 11 / C.3D).
 * Never throws. Never rolls back financial order creation.
 * Stores only tasks.session_attribution_id (opaque pointer) — no UTM/PII copy.
 */
final class AttributionOrderLinker
{
    public function __construct(private readonly AttributionSanitizer $sanitizer)
    {
    }

    /**
     * @param object $task Task model instance (duck-typed to avoid hard package deps in tests)
     */
    public function attachFailOpen(object $task, mixed $publicSessionId): void
    {
        if (! AttributionFeatures::orderLinkEnabled()) {
            return;
        }

        try {
            $sessionId = $this->sanitizer->sanitizeSessionId($publicSessionId);
            if ($sessionId === null) {
                return;
            }
            if (! isset($task->id)) {
                return;
            }
            // Already linked — idempotent.
            if (! empty($task->session_attribution_id)) {
                return;
            }

            $attrId = SessionAttribution::query()
                ->where('public_session_id', $sessionId)
                ->value('id');
            if (! $attrId) {
                // Fail-open stub: order may arrive before async ingest (or after
                // consent grant without a prior navigation). Opaque id only.
                try {
                    $attrId = SessionAttribution::query()->create([
                        'public_session_id' => $sessionId,
                        'first_seen_at' => now(),
                        'last_seen_at' => now(),
                    ])->id;
                } catch (\Throwable) {
                    $attrId = SessionAttribution::query()
                        ->where('public_session_id', $sessionId)
                        ->value('id');
                }
            }
            if (! $attrId) {
                return;
            }

            if (method_exists($task, 'forceFill') && method_exists($task, 'saveQuietly')) {
                $task->forceFill(['session_attribution_id' => (int) $attrId])->saveQuietly();

                return;
            }

            if (method_exists($task, 'forceFill') && method_exists($task, 'save')) {
                $task->forceFill(['session_attribution_id' => (int) $attrId])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('attribution_order_link_failed', [
                'class' => $e::class,
                'task_id' => $task->id ?? null,
            ]);
        }
    }

    /**
     * Resolve opaque session id from request options without trusting numeric DB ids.
     *
     * Accepts array or Collection (ManagerOrder stores options as Collection).
     *
     * @param  array<string, mixed>|Collection  $options
     */
    public function sessionIdFromOptions(array|Collection $options): mixed
    {
        if ($options instanceof Collection) {
            $options = $options->all();
        }

        return $options['public_session_id']
            ?? $options['attribution_session_id']
            ?? $options['exs_public_session_id']
            ?? null;
    }
}
