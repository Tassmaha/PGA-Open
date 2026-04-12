<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arbre g\u00e9ographique auto-r\u00e9f\u00e9renc\u00e9.
 * Remplace les 6 tables s\u00e9par\u00e9es (regions, provinces, districts, communes,
 * formations_sanitaires, villages) par un arbre g\u00e9n\u00e9rique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('geo_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('geo_level_id')->constrained('geo_levels')->restrictOnDelete();
            $table->uuid('parent_id')->nullable()->index();
            $table->foreign('parent_id')->references('id')->on('geo_units')->nullOnDelete();

            $table->string('code')->nullable()->index();
            $table->string('name');
            $table->string('status')->default('active')->index(); // active, displaced, archived
            $table->json('extra')->nullable();           // type CSPS/CMA, code_mfl, etc.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index compos\u00e9 pour les requ\u00eates courantes
            $table->index(['geo_level_id', 'status']);
            $table->index(['parent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('geo_units');
    }
};
