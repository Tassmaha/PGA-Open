<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'code', 'nom', 'prenom', 'date_naissance', 'sexe',
        'id_document_number', 'id_document_issued_at', 'id_document_expires_at',
        'telephone', 'telephone_alt',
        'geo_unit_id', 'distance_profile', 'distance_km',
        'date_recrutement', 'date_activation', 'date_desactivation',
        'statut', 'motif_desactivation', 'motif_desactivation_detail',
        'cree_par', 'valide_par', 'valide_le', 'desactive_par',
    ];

    protected $casts = [
        'date_naissance'          => 'date',
        'id_document_issued_at'   => 'date',
        'id_document_expires_at'  => 'date',
        'date_recrutement'        => 'date',
        'date_activation'         => 'date',
        'date_desactivation'      => 'date',
        'valide_le'               => 'datetime',
        'distance_km'             => 'float',
    ];

    // ── Accessors ──────────────────────────────────────────────────

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_naissance?->age;
    }

    public function getIdDocumentStatusAttribute(): string
    {
        if (!$this->id_document_expires_at) return 'unknown';
        if ($this->id_document_expires_at->isPast()) return 'expired';
        if ($this->id_document_expires_at->diffInDays(now()) <= config('pga.identity_document.expiration_alert_days', 90)) return 'expiring_soon';
        return 'valid';
    }

    public function getIdDocumentDaysRemainingAttribute(): ?int
    {
        return $this->id_document_expires_at?->diffInDays(now(), false);
    }

    // ── Relations ──────────────────────────────────────────────────

    public function geoUnit(): BelongsTo
    {
        return $this->belongsTo(GeoUnit::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function validatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function deactivatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desactive_par');
    }

    public function functionalityStatuses(): HasMany
    {
        return $this->hasMany(FunctionalityStatus::class);
    }

    public function paymentStatuses(): HasMany
    {
        return $this->hasMany(PaymentStatus::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeInactive($query)
    {
        return $query->where('statut', 'inactif');
    }

    public function scopePendingValidation($query)
    {
        return $query->where('statut', 'en_attente_validation');
    }

    public function scopeInGeoUnits($query, array $geoUnitIds)
    {
        return $query->whereIn('geo_unit_id', $geoUnitIds);
    }

    public function scopeIdDocumentExpired($query)
    {
        return $query->whereNotNull('id_document_expires_at')
            ->where('id_document_expires_at', '<', now());
    }

    public function scopeIdDocumentExpiringSoon($query, int $days = 90)
    {
        return $query->whereNotNull('id_document_expires_at')
            ->where('id_document_expires_at', '>=', now())
            ->where('id_document_expires_at', '<=', now()->addDays($days));
    }

    // ── Code g\u00e9n\u00e9ration ────────────────────────────────────────────

    public static function generateCode(): string
    {
        $prefix = config('pga.agent.code_prefix', 'AGT');
        $year = now()->format('Y');
        $last = static::withTrashed()
            ->where('code', 'like', "{$prefix}-{$year}-%")
            ->orderByRaw("CAST(SUBSTRING(code FROM '\\d+$') AS INTEGER) DESC")
            ->value('code');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $seq);
    }
}
