<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table des agents de sant\u00e9 communautaire.
 * Remplacement de la table 'asbc' du PGA Burkina.
 * Le type d'agent (ASBC, ASC, Relais) est configurable par tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();       // ASBC-2024-0001
            $table->string('nom');
            $table->string('prenom');
            $table->date('date_naissance')->nullable();
            $table->string('sexe', 1);                  // M / F
            // Document d'identit\u00e9 (CNIB au Burkina, CNI au S\u00e9n\u00e9gal, etc.)
            $table->string('id_document_number', 20)->nullable();
            $table->date('id_document_issued_at')->nullable();
            $table->date('id_document_expires_at')->nullable();
            // T\u00e9l\u00e9phone
            $table->string('telephone', 20)->nullable();
            $table->string('telephone_alt', 20)->nullable();
            // Localisation : pointe vers le niveau d'affectation (village)
            $table->foreignUuid('geo_unit_id')->constrained('geo_units')->restrictOnDelete();
            $table->string('distance_profile', 20)->nullable(); // moins_5km, plus_5km
            $table->decimal('distance_km', 6, 2)->nullable();
            // Dates de vie
            $table->date('date_recrutement')->nullable();
            $table->date('date_activation')->nullable();
            $table->date('date_desactivation')->nullable();
            // Statut
            $table->string('statut', 30)->default('en_attente_validation')->index();
            $table->string('motif_desactivation', 50)->nullable();
            $table->text('motif_desactivation_detail')->nullable();
            // Workflow
            $table->foreignUuid('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->foreignUuid('desactive_par')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'geo_unit_id']);
            $table->index('id_document_expires_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('agents');
    }
};
