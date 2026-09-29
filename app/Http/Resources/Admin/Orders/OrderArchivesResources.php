<?php

namespace App\Http\Resources\Admin\Orders;

use App\Services\Orders\Archival\OwnerConfirmedHistoricalSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class OrderArchivesResources extends ResourceCollection
{
    private $pagination;

    public function __construct($resource)
    {
        $this->pagination = [
            'total' => $resource->total(),
            'per_page' => $resource->perPage(),
            'current_page' => $resource->currentPage(),
            'from' => $resource->firstItem(),
            'to' => $resource->lastItem(),
            'last_page' => $resource->lastPage(),
        ];

        $resource = $resource->getCollection();

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($item) {
                $archivedAt = $item->archived_at ?? null;
                if ($archivedAt instanceof CarbonInterface === false) {
                    if ($archivedAt) {
                        try {
                            $archivedAt = Carbon::parse($archivedAt);
                        } catch (\Throwable $e) {
                            $archivedAt = null;
                        }
                    } else {
                        $archivedAt = null;
                    }
                }

                $opt = is_array($item->opt_params) ? $item->opt_params : null;
                $hist = OwnerConfirmedHistoricalSettlement::fromOptParams($opt);
                $isOwnerConfirmed = $hist !== null;

                return [
                    'id' => $item->id,
                    'attributes' => [
                        'public_id' => $item->public_id,
                        'tech_name' => $item->direction_exchange?->tech_name,
                        'designation_xml' => $item->designation_xml,
                        'status' => (int) $item->status,
                        'status_label' => 'Paid / awaiting settlement (historical)',
                        'is_historical' => true,
                        'archived_at' => $archivedAt ? $archivedAt->format('c') : null,
                        'resolution_type' => $hist['resolution_type'] ?? null,
                        'resolution_source' => $hist['resolution_source'] ?? null,
                        'resolved_at' => $hist['resolved_at'] ?? null,
                        'resolved_by' => $hist['resolved_by'] ?? null,
                        'resolution_reference' => $hist['reference'] ?? null,
                        'owner_confirmed_historical_settlement' => $isOwnerConfirmed,
                        'resolution_label' => $isOwnerConfirmed
                            ? 'Owner-confirmed historical settlement'
                            : 'Archived',
                        'payout_evidence_present' => false,
                        'payout_evidence_note' => $isOwnerConfirmed
                            ? 'No blockchain/provider payout evidence invented; owner attestation only.'
                            : null,
                    ],
                ];
            }),
            ...$this->pagination
        ];
    }
}
