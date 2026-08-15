<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property string $is_cron
 * @property int $is_filter
 * @property int $is_allow_filter
 * @property string|null $format_export
 * @property int $count
 * @property string|null $export_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData query()
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData whereExportValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData whereFormatExport($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData whereIsAllowFilter($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData whereIsCron($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData whereIsFilter($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExportData whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ExportData extends Model
{
    protected $table = 'export_data';

    protected $fillable = [
        'name', 'format_export', 'is_cron', 'count', 'export_value', 'is_filter', 'is_allow_filter',
    ];
}
