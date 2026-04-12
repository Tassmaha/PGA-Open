<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * R\u00e9sout le tenant courant et configure la connexion DB + config PGA.
 *
 * Ordre de r\u00e9solution :
 * 1. Header X-Tenant (pour API clients)
 * 2. Sous-domaine (bf.pga-open.org \u2192 slug "bf")
 * 3. Tenant par d\u00e9faut (config tenants.default)
 */
class TenantResolver
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->resolveSlug($request);

        if (!$slug) {
            return response()->json(['error' => 'Tenant non r\u00e9solu. Ajoutez le header X-Tenant.'], 400);
        }

        $tenant = Cache::remember("tenant:{$slug}", 300, function () use ($slug) {
            return Tenant::where('slug', $slug)
                ->orWhere('domain', $slug)
                ->where('active', true)
                ->first();
        });

        if (!$tenant) {
            return response()->json(['error' => "Tenant \"{$slug}\" introuvable ou inactif."], 404);
        }

        // Configurer la connexion DB du tenant
        Config::set('database.connections.tenant', $tenant->databaseConfig());
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');

        // Fusionner la config tenant dans config('pga')
        if (is_array($tenant->config)) {
            foreach ($tenant->config as $section => $values) {
                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        Config::set("pga.{$section}.{$key}", $value);
                    }
                } else {
                    Config::set("pga.{$section}", $values);
                }
            }
        }

        // Stocker le tenant sur la requ\u00eate
        $request->attributes->set('tenant', $tenant);
        app()->instance('tenant', $tenant);

        return $next($request);
    }

    private function resolveSlug(Request $request): ?string
    {
        // 1. Header X-Tenant
        if ($header = $request->header('X-Tenant')) {
            return $header;
        }

        // 2. Sous-domaine
        $host = $request->getHost();
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            return $parts[0]; // bf.pga-open.org \u2192 "bf"
        }

        // 3. Default
        return config('tenants.default');
    }
}
