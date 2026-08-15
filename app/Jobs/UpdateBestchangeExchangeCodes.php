<?php

namespace App\Jobs;

use App\Models\BestChangeDirection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateBestchangeExchangeCodes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        BestChangeDirection::query()
            ->whereNull('exchange_in')
            ->orWhereNull('exchange_out')
            ->chunkById(500, function ($items) {
                foreach ($items as $item) {
                    if (strpos($item->name, '->') === false) {
                        continue;
                    }

                    [$inPart, $outPart] = explode('->', $item->name);

                    preg_match('/([A-Za-z]+)\)?\s*$/', trim($inPart), $matchesIn);
                    preg_match('/([A-Za-z]+)\)?\s*$/', trim($outPart), $matchesOut);

                    if (!isset($matchesIn[1], $matchesOut[1])) {
                        continue;
                    }

                    $exchangeIn = strtoupper(preg_replace('/[^A-Za-z]/', '', $matchesIn[1]));
                    $exchangeOut = strtoupper(preg_replace('/[^A-Za-z]/', '', $matchesOut[1]));

                    if (empty($exchangeIn) || empty($exchangeOut)) {
                        continue;
                    }

                    $item->exchange_in = $exchangeIn;
                    $item->exchange_out = $exchangeOut;

                    $item->save();
                }
            });
    }
}
