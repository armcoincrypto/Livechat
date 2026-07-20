<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\RateDirectionEligibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Explain why a direction is active / quarantined / not exportable.
 */
final class RatesDirectionStatusCommand extends Command
{
    protected $signature = 'rates:direction-status
        {id : direction_exchange id}
        {--format=json : json|table}';

    protected $description = 'Explain direction eligibility for quote, order, and export (read-only).';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $row = DB::table('direction_exchange as d')
            ->join('currencies as c1', 'c1.id', '=', 'd.id_currency1')
            ->join('currencies as c2', 'c2.id', '=', 'd.id_currency2')
            ->where('d.id', $id)
            ->first([
                'd.id',
                'd.status',
                'd.allow_export',
                'd.course_value',
                'd.profit',
                'd.deleted_at',
                'd.parser_source_name',
                'd.direction_reserve',
                'd.type_reserve',
                'c1.designation_xml as from',
                'c2.designation_xml as to',
                'c1.max_display_reserve as from_reserve',
                'c2.max_display_reserve as to_reserve',
            ]);

        if ($row === null) {
            $this->error('direction_not_found');

            return self::FAILURE;
        }

        $reserveRaw = $row->direction_reserve;
        if ($reserveRaw === null || $reserveRaw === '' || (is_numeric($reserveRaw) && (float) $reserveRaw <= 0)) {
            $reserveRaw = $row->to_reserve ?: $row->from_reserve;
        }
        $reserveOk = is_numeric($reserveRaw) && (float) $reserveRaw > 0;

        $payload = RateDirectionEligibility::make()->explain([
            'id' => (int) $row->id,
            'status' => (int) $row->status,
            'allow_export' => (int) $row->allow_export,
            'course_value' => (string) $row->course_value,
            'profit' => (string) ($row->profit ?? '0'),
            'deleted_at' => $row->deleted_at,
            'from' => (string) $row->from,
            'to' => (string) $row->to,
            'provider_status' => (string) ($row->parser_source_name ?? ''),
            'reserve_ok' => $reserveOk,
            'require_verified_export_mapping' => true,
        ]);

        if ((string) $this->option('format') === 'table') {
            $this->table(
                ['field', 'value'],
                collect($payload)->map(fn ($v, $k) => [$k, is_scalar($v) || $v === null ? (string) $v : json_encode($v)])->values()->all()
            );
        } else {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
