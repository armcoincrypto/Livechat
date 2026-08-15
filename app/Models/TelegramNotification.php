<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;

class TelegramNotification extends Model
{
    use Filterable;


    protected $table = 'telegram_notifications';

    protected $fillable = [
        'name',
        'token_access',
        'id_channel',
        'channel_name',
        'status',
        'send_to_bot',
        'ext_params'
    ];

    /**
     * Атрибуты, которые должны быть приведены к нативным типам.
     *
     * @return string[]
     */
    protected function casts()
    {
        return [
            'ext_params' => 'array',
            'send_to_bot' => 'bool',
        ];
    }
}
