<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeoUnit extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'geo_level_id', 'parent_id', 'code', 'name',
        'status', 'extra', 'latitude', 'longitude',
    ];

    protected $casts = [
        'extra'     => 'array',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    // ── Relations ──────────────────────────────────────────────────

    public function level(): BelongsTo
    {
        return $this->belongsTo(GeoLevel::class, 'geo_level_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->where('status', 'active');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAtLevel($query, string $levelKey)
    {
        return $query->whereHas('level', fn($q) => $q->where('key', $levelKey));
    }

    // ── Arbre : anc\u00eatres et descendants ──────────────────────────────

    /**
     * Retourne tous les anc\u00eatres de cette unit\u00e9 (du parent direct \u00e0 la racine).
     * Utilise une CTE r\u00e9cursive PostgreSQL pour performance optimale.
     */
    public function ancestors(): Collection
    {
        if (!$this->parent_id) {
            return collect();
        }

        $results = DB::connection('tenant')->select("
            WITH RECURSIVE ancestors AS (
                SELECT id, parent_id, geo_level_id, code, name, status, extra
                FROM geo_units WHERE id = ?
                UNION ALL
                SELECT g.id, g.parent_id, g.geo_level_id, g.code, g.name, g.status, g.extra
                FROM geo_units g
                INNER JOIN ancestors a ON g.id = a.parent_id
            )
            SELECT a.*, gl.key as level_key, gl.name as level_name, gl.depth
            FROM ancestors a
            JOIN geo_levels gl ON gl.id = a.geo_level_id
            WHERE a.id != ?
            ORDER BY gl.depth ASC
        ", [$this->id, $this->id]);

        return collect($results);
    }

    /**
     * Retourne tous les descendants de cette unit\u00e9 (enfants, petits-enfants, etc.).
     * Optionnel : filtrer par level_key pour n'obtenir que les villages, par ex.
     */
    public function descendants(?string $levelKey = null): Collection
    {
        $results = DB::connection('tenant')->select("
            WITH RECURSIVE descendants AS (
                SELECT id, parent_id, geo_level_id, code, name, status
                FROM geo_units WHERE parent_id = ?
                UNION ALL
                SELECT g.id, g.parent_id, g.geo_level_id, g.code, g.name, g.status
                FROM geo_units g
                INNER JOIN descendants d ON g.parent_id = d.id
            )
            SELECT d.*, gl.key as level_key, gl.depth
            FROM descendants d
            JOIN geo_levels gl ON gl.id = d.geo_level_id
            " . ($levelKey ? "WHERE gl.key = ?" : "") . "
            ORDER BY gl.depth, d.name
        ", $levelKey ? [$this->id, $levelKey] : [$this->id]);

        return collect($results);
    }

    /**
     * Retourne les IDs de tous les \u00e9tablissements de sant\u00e9 descendants.
     * Utilis\u00e9 pour le filtrage par zone (CheckZoneAccess).
     */
    public function descendantHealthFacilityIds(): array
    {
        $facilityLevel = GeoLevel::healthFacilityLevel();
        if (!$facilityLevel) return [];

        return $this->descendants($facilityLevel->key)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    /**
     * Chemin complet de localisation (ex: "Village > CSPS > Commune > District > Province > R\u00e9gion").
     */
    public function locationPath(): string
    {
        $ancestors = $this->ancestors();
        $parts = $ancestors->sortByDesc('depth')->pluck('name')->toArray();
        $parts[] = $this->name;
        return implode(' > ', array_reverse($parts));
    }
}
