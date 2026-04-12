<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoLevel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'depth', 'key', 'name', 'name_plural',
        'is_health_facility', 'is_assignment_level',
    ];

    protected $casts = [
        'depth'                => 'integer',
        'is_health_facility'   => 'boolean',
        'is_assignment_level'  => 'boolean',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(GeoUnit::class);
    }

    /**
     * Retourne le niveau qui repr\u00e9sente les \u00e9tablissements de sant\u00e9.
     */
    public static function healthFacilityLevel(): ?self
    {
        return static::where('is_health_facility', true)->first();
    }

    /**
     * Retourne le niveau d'affectation des agents (village).
     */
    public static function assignmentLevel(): ?self
    {
        return static::where('is_assignment_level', true)->first();
    }
}
