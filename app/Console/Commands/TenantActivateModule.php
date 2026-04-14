<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\ModuleManager;
use Illuminate\Console\Command;

class TenantActivateModule extends Command
{
    protected $signature = 'tenant:module
        {slug : Slug du tenant}
        {action : activate | deactivate | list}
        {module? : Nom du module (analytics, advanced_reports, integrations, multi_org)}
        {--expires= : Date d\'expiration YYYY-MM-DD (pour activate)}';

    protected $description = 'Active ou d\u00e9sactive un module premium pour un tenant';

    public function handle(ModuleManager $manager): int
    {
        $tenant = Tenant::where('slug', $this->argument('slug'))->firstOrFail();
        $action = $this->argument('action');

        if ($action === 'list') {
            $this->info("Modules disponibles :");
            foreach ($manager->available() as $key => $info) {
                $active = data_get($tenant->config, "modules.{$key}.enabled", false);
                $expires = data_get($tenant->config, "modules.{$key}.expires_at", '\u2014');
                $status = $active ? "<fg=green>ACTIF</>" : "<fg=gray>inactif</>";
                $this->line("  [{$status}] <fg=cyan>{$key}</> \u2014 {$info['label']} (expire: {$expires})");
            }
            return 0;
        }

        $module = $this->argument('module');
        if (!$module) {
            $this->error('Nom du module requis pour activate/deactivate');
            return 1;
        }

        if (!isset($manager->available()[$module])) {
            $this->error("Module \"{$module}\" inconnu. Utilisez 'list' pour voir les modules disponibles.");
            return 1;
        }

        $config = $tenant->config ?: [];
        $config['modules'] = $config['modules'] ?? [];

        if ($action === 'activate') {
            $expires = $this->option('expires') ?: now()->addYear()->toDateString();
            $config['modules'][$module] = [
                'enabled'    => true,
                'expires_at' => $expires,
                'label'      => $manager->available()[$module]['label'],
            ];
            $tenant->update(['config' => $config]);
            \Illuminate\Support\Facades\Cache::forget("tenant:{$tenant->slug}");
            $this->info("Module '{$module}' activ\u00e9 pour '{$tenant->name}' (expire: {$expires}).");
        } elseif ($action === 'deactivate') {
            $config['modules'][$module] = ['enabled' => false];
            $tenant->update(['config' => $config]);
            \Illuminate\Support\Facades\Cache::forget("tenant:{$tenant->slug}");
            $this->info("Module '{$module}' d\u00e9sactiv\u00e9 pour '{$tenant->name}'.");
        } else {
            $this->error("Action inconnue : {$action}");
            return 1;
        }

        return 0;
    }
}
