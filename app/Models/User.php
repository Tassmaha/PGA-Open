<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuids, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'nom', 'prenom', 'email', 'password', 'telephone',
        'role', 'zone_geo_unit_id', 'actif', 'derniere_connexion',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'actif' => 'boolean',
        'derniere_connexion' => 'datetime',
        'password' => 'hashed',
    ];

    // ── Accessors ──────────────────────────────────────────────────

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }

    // ── Relations ──────────────────────────────────────────────────

    public function zoneGeoUnit(): BelongsTo
    {
        return $this->belongsTo(GeoUnit::class, 'zone_geo_unit_id');
    }

    // ── Zone d'acc\u00e8s (via arbre g\u00e9ographique) ──────────────────────

    /**
     * IDs des \u00e9tablissements de sant\u00e9 accessibles par cet utilisateur.
     * null = acc\u00e8s national (admin).
     */
    public function accessibleHealthFacilityIds(): ?array
    {
        // Admin central : acc\u00e8s total
        if (!$this->zone_geo_unit_id) {
            return null;
        }

        $zone = $this->zoneGeoUnit;
        if (!$zone) return [];

        $facilityLevel = GeoLevel::healthFacilityLevel();
        if (!$facilityLevel) return [];

        // Si la zone est un \u00e9tablissement de sant\u00e9, retourner juste celui-ci
        if ($zone->geo_level_id === $facilityLevel->id) {
            return [$zone->id];
        }

        // Sinon, r\u00e9cup\u00e9rer tous les \u00e9tablissements descendants
        return $zone->descendantHealthFacilityIds();
    }

    /**
     * IDs de toutes les unit\u00e9s g\u00e9o au niveau d'affectation (villages) accessibles.
     */
    public function accessibleAssignmentUnitIds(): ?array
    {
        if (!$this->zone_geo_unit_id) return null;

        $zone = $this->zoneGeoUnit;
        if (!$zone) return [];

        $assignmentLevel = GeoLevel::assignmentLevel();
        if (!$assignmentLevel) return [];

        return $zone->descendants($assignmentLevel->key)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
