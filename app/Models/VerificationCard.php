<?php

namespace App\Models;

use App\Models\Filters\VerificationCardFilter;
use App\Services\Verification\VerificationIdentifierVault;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use RuntimeException;

/**
 * @property int $id
 * @property int $id_user
 * @property int $id_order
 * @property string|null $card_number_ciphertext
 * @property string|null $card_number_string_ciphertext
 * @property string|null $card_number_lookup
 * @property string|null $card_number_last4
 * @property int $identifier_key_version
 * @property string|null $image
 * @property int $is_verified
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $hash_id
 * @property int $id_currency
 * @property string|null $name
 * @property int $status
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int $is_local_image
 * @property string|null $email
 * @property string|null $text_message
 * @property int $id_manager
 * @property string|null $language
 * @mixin \Eloquent
 */
class VerificationCard extends Model
{
    use Filterable, Notifiable;

    protected $table = 'verification_card';

    protected $fillable = [
        'id_user',
        'id_order',
        'card_number_ciphertext',
        'card_number_string_ciphertext',
        'card_number_lookup',
        'card_number_last4',
        'identifier_key_version',
        'name',
        'image',
        'image_preview',
        'email',
        'is_verified',
        'hash_id',
        'id_currency',
        'status',
        'ip_address',
        'user_agent',
        'is_local_image',
        'text_message',
        'id_manager',
        'language',
    ];

    /**
     * Encrypted identifier attributes for create/update (no plaintext columns).
     */
    public static function identifierAttributes(string $cardNumber, ?string $cardNumberString = null): array
    {
        return app(VerificationIdentifierVault::class)->packForStorage($cardNumber, $cardNumberString);
    }

    /**
     * Ciphertext-only identifier resolve. Null = unavailable.
     */
    public function resolvedCardNumber(): ?string
    {
        return app(VerificationIdentifierVault::class)->resolve(
            $this->card_number_ciphertext,
            null
        );
    }

    /**
     * Display form from string ciphertext, else primary ciphertext.
     */
    public function resolvedCardNumberString(): ?string
    {
        $vault = app(VerificationIdentifierVault::class);
        $fromString = $vault->resolve($this->card_number_string_ciphertext, null);
        if ($fromString !== null) {
            return $fromString;
        }

        return $this->resolvedCardNumber();
    }

    /**
     * Exact-match via lookup hash only.
     */
    public function scopeWhereIdentifier(Builder $query, ?string $rawIdentifier): Builder
    {
        $vault = app(VerificationIdentifierVault::class);
        $normalized = $vault->normalize($rawIdentifier);
        if ($normalized === '') {
            return $query->whereRaw('1 = 0');
        }

        try {
            $lookup = $vault->lookupHash($normalized);
        } catch (\Throwable) {
            throw new RuntimeException('verification_identifier_lookup_unavailable');
        }

        return $query->where('card_number_lookup', $lookup);
    }

    public function modelFilter()
    {
        return $this->provideFilter(VerificationCardFilter::class);
    }

    public function currency()
    {
        return $this->hasOne(Currency::class, 'id', 'id_currency');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }

    public function manager()
    {
        return $this->hasOne(User::class, 'id', 'id_manager');
    }

    public function tasks()
    {
        return $this->hasOne(Task::class, 'id', 'id_order');
    }
}
