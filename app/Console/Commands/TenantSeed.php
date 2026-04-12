<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantSeed extends Command
{
    protected $signature = 'tenant:seed {slug : Slug du tenant}';
    protected $description = 'Charge les donn\u00e9es initiales (g\u00e9ographie, admin) pour un tenant';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('slug'))->firstOrFail();
        $dataDir = database_path("seeders/tenant-data/{$tenant->slug}");

        if (!is_dir($dataDir)) {
            $this->error("Dossier de donn\u00e9es introuvable : {$dataDir}");
            $this->info("Cr\u00e9ez le dossier et ajoutez geo_levels.json + geo_units.json");
            return 1;
        }

        // Configurer connexion tenant
        Config::set('database.connections.tenant', $tenant->databaseConfig());
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');

        // 1. Charger les niveaux g\u00e9ographiques
        $levelsFile = "{$dataDir}/geo_levels.json";
        if (file_exists($levelsFile)) {
            $levels = json_decode(file_get_contents($levelsFile), true);
            $this->info("Chargement de " . count($levels) . " niveaux g\u00e9ographiques...");
            DB::table('geo_levels')->truncate();
            foreach ($levels as $level) {
                DB::table('geo_levels')->insert([
                    'id'                   => \Illuminate\Support\Str::uuid(),
                    'depth'                => $level['depth'],
                    'key'                  => $level['key'],
                    'name'                 => $level['name'],
                    'name_plural'          => $level['name_plural'],
                    'is_health_facility'   => $level['is_health_facility'] ?? false,
                    'is_assignment_level'  => $level['is_assignment_level'] ?? false,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }
            $this->info("Niveaux g\u00e9ographiques charg\u00e9s.");
        }

        // 2. Charger les unit\u00e9s g\u00e9ographiques
        $unitsFile = "{$dataDir}/geo_units.json";
        if (file_exists($unitsFile)) {
            $units = json_decode(file_get_contents($unitsFile), true);
            $this->info("Chargement de " . count($units) . " unit\u00e9s g\u00e9ographiques...");
            DB::table('geo_units')->truncate();

            // Map level key \u2192 level ID
            $levelMap = DB::table('geo_levels')->pluck('id', 'key')->toArray();
            // Map code \u2192 unit ID (pour r\u00e9soudre parent_code)
            $codeMap = [];

            foreach ($units as $unit) {
                $id = \Illuminate\Support\Str::uuid()->toString();
                $codeMap[$unit['code']] = $id;

                DB::table('geo_units')->insert([
                    'id'           => $id,
                    'geo_level_id' => $levelMap[$unit['level']] ?? null,
                    'parent_id'    => isset($unit['parent_code']) ? ($codeMap[$unit['parent_code']] ?? null) : null,
                    'code'         => $unit['code'],
                    'name'         => $unit['name'],
                    'status'       => $unit['status'] ?? 'active',
                    'extra'        => isset($unit['extra']) ? json_encode($unit['extra']) : null,
                    'latitude'     => $unit['latitude'] ?? null,
                    'longitude'    => $unit['longitude'] ?? null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
            $this->info("Unit\u00e9s g\u00e9ographiques charg\u00e9es.");
        }

        // 3. Cr\u00e9er l'admin
        $adminFile = "{$dataDir}/admin_user.json";
        if (file_exists($adminFile)) {
            $admin = json_decode(file_get_contents($adminFile), true);
            $this->info("Cr\u00e9ation de l'utilisateur admin...");
            DB::table('users')->updateOrInsert(
                ['email' => $admin['email']],
                [
                    'id'       => \Illuminate\Support\Str::uuid(),
                    'nom'      => $admin['nom'],
                    'prenom'   => $admin['prenom'],
                    'email'    => $admin['email'],
                    'password' => \Illuminate\Support\Facades\Hash::make($admin['password']),
                    'role'     => 'admin_dsc',
                    'actif'    => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $this->info("Admin cr\u00e9\u00e9 : {$admin['email']}");
        }

        $this->info("Seeding termin\u00e9 pour {$tenant->name}.");
        return 0;
    }
}
