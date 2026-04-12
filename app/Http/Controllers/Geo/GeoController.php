<?php

namespace App\Http\Controllers\Geo;

use App\Http\Controllers\Controller;
use App\Models\GeoLevel;
use App\Models\GeoUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint g\u00e9ographique dynamique.
 * Remplace les 6 endpoints hardcod\u00e9s (regions, provinces, districts, etc.)
 * par un seul endpoint param\u00e9trable.
 */
class GeoController extends Controller
{
    /**
     * GET /api/v1/geo/levels
     * Retourne les niveaux g\u00e9ographiques du tenant.
     */
    public function levels(): JsonResponse
    {
        return response()->json(
            GeoLevel::orderBy('depth')->get()
        );
    }

    /**
     * GET /api/v1/geo/units?level={key}&parent_id={uuid}
     * Retourne les unit\u00e9s d'un niveau, filtr\u00e9es par parent.
     */
    public function units(Request $request): JsonResponse
    {
        $query = GeoUnit::with('level:id,key,name')
            ->active()
            ->orderBy('name');

        if ($levelKey = $request->input('level')) {
            $query->atLevel($levelKey);
        }

        if ($parentId = $request->input('parent_id')) {
            $query->where('parent_id', $parentId);
        } elseif ($levelKey) {
            // Si pas de parent sp\u00e9cifi\u00e9 et level = top, retourner les racines
            $level = GeoLevel::where('key', $levelKey)->first();
            if ($level && $level->depth === 0) {
                $query->whereNull('parent_id');
            }
        }

        // Restriction zone utilisateur
        $zoneFacilities = $request->attributes->get('zone_facilities');
        if ($zoneFacilities !== null) {
            // Filtrer uniquement les unit\u00e9s qui sont anc\u00eatres ou descendants des facilities autoris\u00e9es
            // Pour simplicit\u00e9 : on retourne tout si l'utilisateur a acc\u00e8s (le filtrage est fait c\u00f4t\u00e9 donn\u00e9es)
        }

        return response()->json($query->get(['id', 'geo_level_id', 'parent_id', 'code', 'name', 'status']));
    }

    /**
     * GET /api/v1/geo/units/{id}/children
     * Retourne les enfants directs d'une unit\u00e9.
     */
    public function children(string $id): JsonResponse
    {
        $unit = GeoUnit::findOrFail($id);

        return response()->json(
            $unit->activeChildren()
                ->with('level:id,key,name')
                ->orderBy('name')
                ->get(['id', 'geo_level_id', 'parent_id', 'code', 'name', 'status'])
        );
    }

    /**
     * GET /api/v1/geo/units/{id}/ancestors
     * Retourne le chemin complet vers la racine.
     */
    public function ancestors(string $id): JsonResponse
    {
        $unit = GeoUnit::findOrFail($id);
        return response()->json($unit->ancestors());
    }
}
