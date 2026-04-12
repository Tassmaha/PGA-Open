<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injecte les IDs des \u00e9tablissements de sant\u00e9 accessibles par l'utilisateur.
 * Utilise l'arbre g\u00e9ographique dynamique (GeoUnit) pour r\u00e9soudre la zone.
 *
 * R\u00e9sultat : $request->attributes->get('zone_facilities')
 * - null = acc\u00e8s national (admin)
 * - array = IDs des GeoUnit de type health_facility
 */
class CheckZoneAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Non authentifi\u00e9'], 401);
        }

        // Admin central sans zone = acc\u00e8s total
        if (!$user->zone_geo_unit_id) {
            $request->attributes->set('zone_facilities', null);
            return $next($request);
        }

        // R\u00e9cup\u00e9rer les IDs avec cache (5 min)
        $cacheKey = "zone_access_{$user->id}";
        $facilityIds = Cache::remember($cacheKey, 300, function () use ($user) {
            return $user->accessibleHealthFacilityIds();
        });

        $request->attributes->set('zone_facilities', $facilityIds);

        return $next($request);
    }
}
