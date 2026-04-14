<?php

namespace App\Services;

use App\Models\Tenant;
use Carbon\Carbon;

/**
 * Gestionnaire des modules premium.
 *
 * Les modules sont activ\u00e9s par tenant via la colonne `config.modules` :
 * {
 *   "modules": {
 *     "analytics":        { "enabled": true, "expires_at": "2027-04-11" },
 *     "advanced_reports": { "enabled": true, "expires_at": "2027-04-11" },
 *     "integrations":     { "enabled": false },
 *     "multi_org":        { "enabled": false }
 *   }
 * }
 */
class ModuleManager
{
    /**
     * V\u00e9rifie si un module est actif pour le tenant courant.
     */
    public function has(string $moduleName): bool
    {
        $tenant = $this->currentTenant();
        if (!$tenant) return false;

        $module = $tenant->cfg("modules.{$moduleName}");
        if (!$module || !($module['enabled'] ?? false)) return false;

        // V\u00e9rifier la date d'expiration
        if ($expiresAt = $module['expires_at'] ?? null) {
            try {
                if (Carbon::parse($expiresAt)->isPast()) return false;
            } catch (\Throwable) {
                return false;
            }
        }

        // V\u00e9rifier que le package composer est bien install\u00e9
        $providerClass = $this->providerFor($moduleName);
        if ($providerClass && !class_exists($providerClass)) return false;

        return true;
    }

    /**
     * Retourne la liste des modules actifs du tenant.
     */
    public function active(): array
    {
        $tenant = $this->currentTenant();
        if (!$tenant) return [];

        $modules = $tenant->cfg('modules', []);
        $result = [];

        foreach ($modules as $name => $config) {
            if ($this->has($name)) {
                $result[$name] = [
                    'name'       => $name,
                    'label'      => $config['label'] ?? ucfirst($name),
                    'expires_at' => $config['expires_at'] ?? null,
                ];
            }
        }

        return $result;
    }

    /**
     * Liste tous les modules disponibles (pour l'UI admin).
     */
    public function available(): array
    {
        return [
            'analytics' => [
                'label'       => 'Analytics & IA',
                'description' => 'Assistant IA conversationnel, rapports narratifs auto-g\u00e9n\u00e9r\u00e9s, pr\u00e9diction d\'abandon',
                'provider'    => 'PgaOpen\\Analytics\\AnalyticsServiceProvider',
            ],
            'advanced_reports' => [
                'label'       => 'Rapports avanc\u00e9s',
                'description' => 'Export Excel format\u00e9, rapports planifi\u00e9s, comparatifs, dashboards personnalisables',
                'provider'    => 'PgaOpen\\AdvancedReports\\AdvancedReportsServiceProvider',
            ],
            'integrations' => [
                'label'       => 'Int\u00e9grations',
                'description' => 'SMS gateway, WhatsApp Bot, DHIS2 sync, webhooks',
                'provider'    => 'PgaOpen\\Integrations\\IntegrationsServiceProvider',
            ],
            'multi_org' => [
                'label'       => 'Multi-organisation',
                'description' => 'Super-admin, SSO/LDAP, comparaison inter-pays',
                'provider'    => 'PgaOpen\\MultiOrg\\MultiOrgServiceProvider',
            ],
        ];
    }

    /**
     * V\u00e9rification pour throw si un module requis n'est pas dispo.
     */
    public function requireModule(string $moduleName): void
    {
        if (!$this->has($moduleName)) {
            abort(403, "Le module '{$moduleName}' n'est pas activ\u00e9 pour ce tenant.");
        }
    }

    private function providerFor(string $moduleName): ?string
    {
        return $this->available()[$moduleName]['provider'] ?? null;
    }

    private function currentTenant(): ?Tenant
    {
        return app()->bound('tenant') ? app('tenant') : null;
    }
}
