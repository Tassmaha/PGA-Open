<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentStatus extends Model
{
    use HasUuids;

    protected $connection = 'tenant';
    protected $table = 'payment_statuses';

    protected $fillable = [
        'agent_id', 'payment_import_id', 'period_month',
        'phone_number', 'amount', 'status', 'raw_status',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function paymentImport(): BelongsTo
    {
        return $this->belongsTo(PaymentImport::class);
    }
}
