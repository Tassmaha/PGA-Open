<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasUuids;

    protected $connection = 'central';

    protected $fillable = [
        'slug', 'name', 'domain',
        'db_host', 'db_port', 'db_database', 'db_username', 'db_password',
        'config', 'active',
    ];

    protected $casts = [
        'config' => 'array',
        'active' => 'boolean',
        'db_port' => 'integer',
    ];

    protected $hidden = ['db_password'];

    /**
     * R\u00e9cup\u00e8re une valeur de config tenant (dot notation).
     * Ex: $tenant->cfg('agent.type_name') => 'ASBC'
     */
    public function cfg(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Retourne les credentials de connexion DB pour ce tenant.
     */
    public function databaseConfig(): array
    {
        return [
            'driver'   => 'pgsql',
            'host'     => $this->db_host,
            'port'     => $this->db_port,
            'database' => $this->db_database,
            'username' => $this->db_username,
            'password' => $this->db_password,
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
            'sslmode'  => 'prefer',
        ];
    }
}
