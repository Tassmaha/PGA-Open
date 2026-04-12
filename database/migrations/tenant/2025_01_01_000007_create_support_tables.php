<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal d'audit
        Schema::connection('tenant')->create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('action', 30);
            $table->string('entity_type', 30);
            $table->uuid('entity_id')->nullable();
            $table->string('entity_label')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });

        // Notifications internes
        Schema::connection('tenant')->create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        // Sessions de saisie (p\u00e9riodes ouvertes/ferm\u00e9es)
        Schema::connection('tenant')->create('data_entry_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('period_month', 7);
            $table->foreignUuid('geo_unit_id')->nullable()->constrained('geo_units')->nullOnDelete();
            $table->string('status', 20)->default('open'); // open, closed, locked
            $table->timestamps();

            $table->unique(['period_month', 'geo_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('data_entry_sessions');
        Schema::connection('tenant')->dropIfExists('notifications');
        Schema::connection('tenant')->dropIfExists('audit_logs');
    }
};
