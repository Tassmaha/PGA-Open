<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'action', 'entity_type', 'entity_id', 'entity_label',
        'before', 'after', 'user_id', 'ip_address',
    ];

    protected $casts = [
        'before' => 'array',
        'after'  => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $action, string $entityType, ?string $entityId = null,
        ?string $entityLabel = null, ?array $before = null, ?array $after = null
    ): static {
        return static::create([
            'action'       => $action,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'entity_label' => $entityLabel,
            'before'       => $before,
            'after'        => $after,
            'user_id'      => auth()->id(),
            'ip_address'   => request()->ip(),
        ]);
    }
}
