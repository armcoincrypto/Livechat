<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\BestchangeParserError;
use Illuminate\Support\Carbon;

/**
 * ParserErrorRecorder
 *
 * Записывает ошибки парсинга в bestchange_parser_error с дедупликацией.
 */
final class ParserErrorRecorder
{
    public function __construct(private readonly int $dedupeHours = 12) {}

    public function record(int $bestChangeId, int $directionExchangeId, int $status, string $description): void
    {
        $description = trim($description);
        if ($description === '') $description = 'Unknown error';

        $since = Carbon::now()->subHours($this->dedupeHours);

        $exists = BestchangeParserError::query()
            ->where('id_bestchange', $bestChangeId)
            ->where('id_direction_exchange', $directionExchangeId)
            ->where('status', $status)
            ->where('description', $description)
            ->where('created_at', '>=', $since)
            ->latest('id')
            ->first();

        if ($exists) {
            $exists->touch();
            return;
        }

        BestchangeParserError::create([
            'id_bestchange' => $bestChangeId,
            'id_direction_exchange' => $directionExchangeId,
            'status' => $status,
            'description' => $description,
        ]);
    }
}
