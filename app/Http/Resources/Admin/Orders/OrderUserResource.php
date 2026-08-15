<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Orders;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

final class OrderUserResource extends JsonResource
{
    public static $wrap = null;

    private ?string $telegramId;
    private ?string $ip;
    private bool $minimizePii;

    public function __construct($resource, ?string $telegramId = null, ?string $ip = null, bool $minimizePii = false)
    {
        parent::__construct($resource);

        $this->telegramId = $telegramId;
        $this->ip = $ip;
        $this->minimizePii = $minimizePii;
    }

    public function toArray($request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $last = $user->last_activity_at ? Carbon::parse($user->last_activity_at) : null;

        if ($this->minimizePii) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'is_verify_account' => (int) ($user->is_verify_account ?? 0),
                'is_banned' => (bool) ($user->isBanned() ?? false),
                'order_num' => (int) ($user->exchanges_count ?? 0),
            ];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,

            'telegram_id' => $this->telegramId,
            'ip' => $this->ip,

            'is_verify_account' => (int) ($user->is_verify_account ?? 0),
            'is_banned' => (bool) ($user->isBanned() ?? false),

            'email' => $user->email,

            'order_num' => (int) ($user->exchanges_count ?? 0),
            'convert_to_usd' => (float) ($user->exchanges_sum_usd ?? 0),

            'is_online' => $last ? $last->copy()->addMinutes(3)->gt(Carbon::now()) : false,
            'last_activity_at' => $last?->translatedFormat('d M Y H:i'),
            'last_activity_at_human' => $last?->diffForHumans(),
        ];
    }
}
