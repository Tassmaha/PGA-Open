<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class TenantMigrate extends Command
{
    protected $signature = 'tenant:migrate
        {slug : Slug du tenant}
        {--seed : Ex\u00e9cuter les seeders apr\u00e8s la migration}
        {--fresh : Supprimer et recr\u00e9er les tables}';

    protected $description = 'Ex\u00e9cute les migrations tenant sur la base du pays sp\u00e9cifi\u00e9';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('slug'))->firstOrFail();
        $this->info("Migration de la base tenant : {$tenant->name} ({$tenant->db_database})");

        // Configurer la connexion tenant
        Config::set('database.connections.tenant', $tenant->databaseConfig());
        DB::purge('tenant');

        // Ex\u00e9cuter les migrations du dossier tenant/
        $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';
        Artisan::call($command, [
            '--database' => 'tenant',
            '--path'     => 'database/migrations/tenant',
            '--force'    => true,
        ]);

        $this->info(Artisan::output());

        if ($this->option('seed')) {
            $this->call('tenant:seed', ['slug' => $tenant->slug]);
        }

        return 0;
    }
}
