<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Niveaux g\u00e9ographiques dynamiques.
 * Chaque pays d\u00e9finit ses propres niveaux (3 \u00e0 8).
 * Ex Burkina : R\u00e9gion \u2192 Province \u2192 District \u2192 Commune \u2192 CSPS \u2192 Village
 * Ex S\u00e9n\u00e9gal : R\u00e9gion \u2192 District \u2192 Poste de Sant\u00e9 \u2192 Village
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('geo_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('depth')->index();          // 0 = top level
            $table->string('key')->unique();            // "region", "district", etc.
            $table->string('name');                     // Nom affich\u00e9 singulier
            $table->string('name_plural');              // Nom affich\u00e9 pluriel
            $table->boolean('is_health_facility')->default(false); // Niveau \u00e9tablissement de sant\u00e9
            $table->boolean('is_assignment_level')->default(false); // Niveau d'affectation des agents
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('geo_levels');
    }
};
