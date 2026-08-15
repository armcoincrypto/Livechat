<?php

namespace App\Models;

use App\Models\Filters\TaskRequisiteAttachedFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TaskRequisiteAttached extends Model
{
    use Filterable;

    protected $table = 'tasks_requisites_attached';

    protected $fillable = [
        'id_task',
        'id_manager',
        'wallet_number',
        'ip_address',
        'user_agent',
        'ext_params'
    ];

    protected function casts()
    {
        return [
            'ext_params' => 'array',
        ];
    }

    /**
     * Фильтры
     */
    public function modelFilter()
    {
        return $this->provideFilter(TaskRequisiteAttachedFilter::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_manager', 'id');
    }
}
