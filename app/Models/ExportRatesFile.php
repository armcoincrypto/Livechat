<?php

namespace App\Models;

use App\Models\Casts\JsonCasts;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ExportRatesFile extends Model
{
    use HasTranslations;

    protected $table = 'export_rates_files';

    protected $fillable = [
        'filename',
        'number_format',
        'type_number_format',
        'in_type_tofee',
        'in_type_fromfee',
        'cron_update',
        'is_offline_operator',
        'status',
        'type_file',
        'ids_excluded_directions',
        'is_view',
        'description'
    ];

    protected $translatable = [
        'description'
    ];


    protected $casts = [
        'ids_excluded_directions' => JsonCasts::class,
    ];
}
