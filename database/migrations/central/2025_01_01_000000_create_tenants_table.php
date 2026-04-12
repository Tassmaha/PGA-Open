<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table centrale des tenants.
 * Chaque tenant = 1 pays/organisation avec sa propre base de donn\u00e9es.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();          // "burkina-faso", "senegal"
            $table->string('name');                     // "Burkina Faso"
            $table->string('domain')->nullable();       // "bf.pga-open.org"

            // Connexion base de donn\u00e9es du tenant
            $table->string('db_host')->default('127.0.0.1');
            $table->integer('db_port')->default(5432);
            $table->string('db_database');
            $table->string('db_username');
            $table->string('db_password');

            // Configuration pays-sp\u00e9cifique (surcharge config/pga.php)
            $table->json('config')->default('{}');

            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
