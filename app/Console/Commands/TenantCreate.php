<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantCreate extends Command
{
    protected $signature = 'tenant:create
        {slug : Identifiant du tenant (ex: burkina-faso)}
        {name : Nom complet (ex: "Burkina Faso")}
        {--db-host=127.0.0.1 : H\u00f4te de la base}
        {--db-port=5432 : Port}
        {--db-user=pga_user : Utilisateur DB}
        {--db-pass= : Mot de passe DB}
        {--create-db : Cr\u00e9er la base PostgreSQL automatiquement}';

    protected $description = 'Cr\u00e9e un nouveau tenant (pays/organisation)';

    public function handle(): int
    {
        $slug = Str::slug($this->argument('slug'));
        $name = $this->argument('name');
        $dbName = 'pga_' . str_replace('-', '_', $slug);

        if (Tenant::where('slug', $slug)->exists()) {
            $this->error("Le tenant \"{$slug}\" existe d\u00e9j\u00e0.");
            return 1;
        }

        $dbHost = $this->option('db-host');
        $dbPort = (int) $this->option('db-port');
        $dbUser = $this->option('db-user');
        $dbPass = $this->option('db-pass') ?: $this->secret('Mot de passe DB');

        // Cr\u00e9er la base PostgreSQL si demand\u00e9
        if ($this->option('create-db')) {
            $this->info("Cr\u00e9ation de la base \"{$dbName}\"...");
            try {
                DB::connection('central')->statement("CREATE DATABASE \"{$dbName}\"");
                $this->info("Base \"{$dbName}\" cr\u00e9\u00e9e.");
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $this->warn("La base \"{$dbName}\" existe d\u00e9j\u00e0.");
                } else {
                    $this->error("Erreur : {$e->getMessage()}");
                    return 1;
                }
            }
        }

        $tenant = Tenant::create([
            'slug'        => $slug,
            'name'        => $name,
            'db_host'     => $dbHost,
            'db_port'     => $dbPort,
            'db_database' => $dbName,
            'db_username' => $dbUser,
            'db_password' => $dbPass,
            'config'      => [],
            'active'      => true,
        ]);

        $this->info("Tenant cr\u00e9\u00e9 : {$tenant->name} (slug: {$tenant->slug}, db: {$dbName})");
        $this->info("Lancez maintenant : php artisan tenant:migrate {$slug}");

        return 0;
    }
}
