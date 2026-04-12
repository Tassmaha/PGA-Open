<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('functionality_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('period_month', 7);          // 2026-04
            // Crit\u00e8res de fonctionnalit\u00e9
            $table->boolean('crit_presence')->default(false);
            $table->boolean('crit_knowledge')->default(false);
            $table->boolean('crit_stock')->default(false);
            $table->boolean('crit_community')->default(false);
            // Calcul\u00e9
            $table->string('status_global', 20)->default('incomplete'); // functional, non_functional, incomplete
            // Validation workflow
            $table->string('validation_status', 20)->default('draft'); // draft, validated_supervisor, read_director, locked
            $table->foreignUuid('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entered_at')->nullable();
            $table->foreignUuid('validated_by_supervisor')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_supervisor_at')->nullable();
            $table->foreignUuid('read_by_director')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('read_director_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(['agent_id', 'period_month']);
            $table->index(['period_month', 'status_global']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('functionality_statuses');
    }
};
