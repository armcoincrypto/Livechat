<?php

namespace App\Http\Resources\Admin\Verifications;

use iEXPackages\ExchangerClient\Support\MaskVerificationIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class VerificationCardsResources extends ResourceCollection
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

        $resource = $resource->getCollection(); // Necessary to remove meta and links

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        // Full identifiers only on explicit reveal for permitted admins (detail workflow).
        $reveal = $request->boolean('reveal_identifiers')
            && $request->user()
            && method_exists($request->user(), 'can')
            && $request->user()->can('admin_verification_card');

        return [
            'data' => $this->collection->map(function ($item) use ($reveal)
            {
                $imageName = get_image_from_private_path('card_verification', $item->image);
                $fullNumber = (string) ($item->resolvedCardNumber() ?? '');
                $fullDisplay = (string) ($item->resolvedCardNumberString() ?? '');
                $last4 = (string) ($item->card_number_last4 ?? '');
                if ($last4 === '' && $fullNumber !== '') {
                    $last4 = mb_substr(preg_replace('/\s+/u', '', $fullNumber) ?? '', -4);
                }

                return [
                    'id' => $item->id,
                    'attributes' => [
                        'id_user' => $item->id_user,
                        'user' => [
                            'name' => $item->user?->name,
                            'email' => $item->user?->email,
                        ],
                        'id_manager' => $item->id_manager,
                        'manager' => [
                            'name' => $item->manager?->name,
                            'email' => $item->manager?->email,
                        ],
                        'ip_address' => $item->ip_address,
                        'currency' => [
                            'name' => $item->currency?->tech_name
                        ],
                        'id_order' => (isset($item->tasks) and !empty($item->tasks)) ? current_order_id($item->tasks) : '',
                        'task' => [
                            'id' => (isset($item->tasks) and !empty($item->tasks)) ? current_order_id($item->tasks) : '',
                            'link_id' => $item->tasks?->id,
                        ],
                        // Routine list: masked. Full value only with reveal_identifiers=1.
                        'card_number_string' => $reveal
                            ? $fullDisplay
                            : MaskVerificationIdentifier::mask($fullDisplay !== '' ? $fullDisplay : $fullNumber),
                        'card_number' => $reveal
                            ? $fullNumber
                            : MaskVerificationIdentifier::mask($fullNumber),
                        'card_number_last4' => $last4,
                        'identifiers_revealed' => $reveal,
                        'name' => $item->name,
                        'image' =>  get_image_from_private_path('card_verification', $item->image),
                        'image_preview' =>  empty($item->image_preview) ? $imageName : get_image_from_private_path('card_verification', $item->image_preview),
                        'status' => $item->status,
                        'text_message' => $item->text_message,
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
