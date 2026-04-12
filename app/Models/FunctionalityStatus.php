<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunctionalityStatus extends Model
{
    use HasUuids;

    protected $connection = 'tenant';
    protected $table = 'functionality_statuses';

    protected $fillable = [
        'agent_id', 'period_month',
        'crit_presence', 'crit_knowledge', 'crit_stock', 'crit_community',
        'status_global', 'validation_status',
        'entered_by', 'entered_at',
        'validated_by_supervisor', 'validated_supervisor_at',
        'read_by_director', 'read_director_at',
        'remarks',
    ];

    protected $casts = [
        'crit_presence'   => 'boolean',
        'crit_knowledge'  => 'boolean',
        'crit_stock'      => 'boolean',
        'crit_community'  => 'boolean',
        'entered_at'      => 'datetime',
        'validated_supervisor_at' => 'datetime',
        'read_director_at'        => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function enteredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function validatedBySupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_supervisor');
    }

    public function scopeFunctional($query)
    {
        return $query->where('status_global', 'functional');
    }
}
