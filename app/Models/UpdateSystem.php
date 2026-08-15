<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $update_site_proxy_addr
 * @property string|null $update_site_proxy_port
 * @property string|null $update_site_proxy_user
 * @property string|null $update_site_proxy_pass
 * @property int $stable_versions_only
 * @property int $update_autocheck
 * @property int $update_stop_autocheck
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem query()
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem whereStableVersionsOnly($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem whereUpdateAutocheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem whereUpdateSiteProxyAddr($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem whereUpdateSiteProxyPass($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem whereUpdateSiteProxyPort($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem whereUpdateSiteProxyUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem whereUpdateStopAutocheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpdateSystem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class UpdateSystem extends Model
{
    protected $table = 'update_systems';

    protected $fillable = [
        'update_site_proxy_addr',
        'update_site_proxy_port',
        'update_site_proxy_user',
        'update_site_proxy_pass',
        'stable_versions_only',
        'update_autocheck',
        'update_stop_autocheck',
    ];
}
