<?php

namespace App\Http\Resources\Admin\Users;

use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\ResourceCollection;


class UsersResource extends ResourceCollection
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

        return [
            'data' => $this->collection->map(function ($item) {

                $referralUser = null;

                if ($item->relationLoaded('fromReferral') && $item->fromReferral &&
                    $item->fromReferral->relationLoaded('referralLink') && $item->fromReferral->referralLink &&
                    $item->fromReferral->referralLink->relationLoaded('user') && $item->fromReferral->referralLink->user) {
                    $referralUser = $item->fromReferral->referralLink->user;
                }

                $roles = $item->roles()->select('id', 'title', 'name')->get(); // через метод отношения, а не ->roles-коллекцию
                $firstRole = $roles->first(); // вместо array_first(...)
                $otherRoles = $firstRole
                    ? $roles->filter(fn ($role) => $role->id !== $firstRole->id)->values()
                    : collect();

                return [
                    'id' => $item->id,
                    'attributes' => [
                        'name' => $item->name,
                        'email' => $item->email,
                        'is_email_verified' => !is_null($item->email_verified_at),
                        'is_verify_account' => (int) $item->is_verify_account == 1,
                        'avatar' => \Str::upper(\Str::substr($item->name, 0, 1)),
                        'ip_address' => $item->ip_address,
                        'from_referral' => $referralUser ? [
                            'id' => $referralUser->id,
                            'name' => $referralUser->name,
                            'email' => $referralUser->email,
                        ] : null,
                        'order_num' => (int) ($item->exchanges_count ?? 0),
                        'order_total_exchanges' =>  (float) ($item->exchanges_sum_usd ?? 0),
                        'balance_def' => (isset($item->user_balance) ? $item->user_balance->balance : 0),
                        'balance' => iex_number_format((isset($item->user_balance) ? $item->user_balance->balance : 0), 2, true),
                        'balance_code' => \Config::get('partners-bonus.name'),
                        'is_deactivated' => $item->deactivation,
                       'is_banned' => $item->isBanned() ? ($item->bans ? $item->bans->map(function($ban) {
                           return [
                               'id' => $ban->id,
                               'attributes' => [
                                   'is_permanent' => is_null($ban->expired_at),
                                   'expired_at' => $ban->expired_at ? \Illuminate\Support\Carbon::parse($ban->expired_at)->translatedFormat('j F Y, H:i') : null,
                                   'comment' => $ban->comment,
                               ],
                           ];
                       }) : []) : null,
                        'first_role' => $firstRole ?? '',
                        'other_roles' => $otherRoles ?? [],

                        'roles' => $item->roles->select('id', 'title', 'name') ?? [],
                        'role_expired_at' => $item->role_expired_at,
                        'created_at' => $item->created_at->translatedFormat('d M Y'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                        'updated_at' => $item->updated_at,

                        'is_guest' => $item->is_guest,
                    ],
                ];
            }),
            ...$this->pagination
        ];
    }
}
