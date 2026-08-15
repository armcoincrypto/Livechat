<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SumsubId extends Model
{
    use HasFactory;

    // Явное указание таблицы (если имя нестандартное)
    protected $table = 'sumsub_ids';

    // Какие поля можно массово заполнять (mass assignment)
    protected $fillable = [
        'user_id',
        'applicant_id',
        'status',
        'expire_at',
        'sumsub_data',
        'provider',
        'is_completed'
    ];

    // Типы для кастинга (например, json, datetime)
    protected $casts = [
        'expire_at' => 'datetime',
        'sumsub_data' => 'array',
    ];

    // Связь с пользователем (если есть таблица users)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
