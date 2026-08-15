<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $account_text
 * @property string|null $partners_text
 * @property string $block_text
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|SettingsReferrals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SettingsReferrals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SettingsReferrals query()
 * @method static \Illuminate\Database\Eloquent\Builder|SettingsReferrals whereAccountText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingsReferrals whereBlockText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingsReferrals whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingsReferrals whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingsReferrals wherePartnersText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingsReferrals whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SettingsReferrals extends Model
{
    protected $table = 'settings_referrals';

    protected $fillable = [
        'account_text', 'partners_text', 'block_text',
    ];
}
