<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentImport extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'period_month', 'file_name', 'file_path', 'status', 'closed', 'closed_at',
        'total_rows', 'success_count', 'failure_count', 'refund_count', 'not_found_count',
        'total_amount', 'success_rate', 'imported_by',
    ];

    protected $casts = [
        'closed'       => 'boolean',
        'closed_at'    => 'datetime',
        'total_amount' => 'float',
        'success_rate' => 'float',
    ];

    public function importedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function paymentStatuses(): HasMany
    {
        return $this->hasMany(PaymentStatus::class);
    }
}
