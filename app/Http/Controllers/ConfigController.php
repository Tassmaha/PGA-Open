<?php

namespace App\Http\Controllers;

use App\Models\GeoLevel;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/config
 * Endpoint public (sans auth) qui retourne la configuration tenant
 * pour le frontend. Permet au JS de charger dynamiquement les labels,
 * le branding, les niveaux g\u00e9ographiques, etc.
 */
class ConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        return response()->json([
            'country'           => config('pga.country.name'),
            'country_code'      => config('pga.country.code'),
            'currency'          => config('pga.country.currency'),
            'locale'            => config('pga.country.locale'),
            'agent_type'        => config('pga.agent.type_name'),
            'agent_type_full'   => config('pga.agent.type_name_full'),
            'agent_code_prefix' => config('pga.agent.code_prefix'),
            'id_document'       => config('pga.identity_document.name'),
            'id_document_full'  => config('pga.identity_document.name_full'),
            'health_facility'   => config('pga.health_facility.type_name'),
            'payment_provider'  => config('pga.payment.provider_name'),
            'organization'      => config('pga.organization'),
            'branding'          => [
                'app_name'      => config('pga.branding.app_name'),
                'primary_color' => config('pga.branding.primary_color'),
                'logo_url'      => $tenant
                    ? "/tenant-assets/{$tenant->slug}/" . config('pga.branding.logo')
                    : '/img/logo.webp',
                'coat_of_arms_url' => $tenant
                    ? "/tenant-assets/{$tenant->slug}/" . config('pga.branding.coat_of_arms')
                    : '/img/armoirie.webp',
            ],
            'geo_levels'        => GeoLevel::orderBy('depth')->get(['key', 'name', 'name_plural', 'is_health_facility', 'is_assignment_level']),
            'roles'             => config('pga.roles'),
            'phone_format'      => config('pga.validation.phone_regex'),
            'modules'           => app(\App\Services\ModuleManager::class)->active(),
        ]);
    }
}
