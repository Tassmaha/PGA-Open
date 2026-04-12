<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Imports de fichiers (Orange Money, MTN, Wave, etc.)
        Schema::connection('tenant')->create('payment_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('period_month', 7);
            $table->string('file_name');
            $table->string('file_path');
            $table->string('status', 20)->default('processing'); // processing, completed, failed
            $table->boolean('closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->integer('total_rows')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->integer('refund_count')->default(0);
            $table->integer('not_found_count')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('success_rate', 5, 2)->default(0);
            $table->foreignUuid('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('period_month');
        });

        // Statuts de paiement individuels
        Schema::connection('tenant')->create('payment_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignUuid('payment_import_id')->constrained('payment_imports')->cascadeOnDelete();
            $table->string('period_month', 7);
            $table->string('phone_number', 20)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20);               // success, failure, refunded, not_found
            $table->string('raw_status')->nullable();    // Statut brut du fichier import
            $table->timestamps();

            $table->index(['agent_id', 'period_month']);
            $table->index(['period_month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payment_statuses');
        Schema::connection('tenant')->dropIfExists('payment_imports');
    }
};
