<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Append-only — a package change never deletes the previous grant.
        // Privilege-level enforcement is a later task; this migration only
        // shapes the columns. Status vocabulary matches the financial
        // ledger's, since a placement grant and its payment are the same
        // commercial event viewed from two tables.
        Schema::create('placement_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('placement_package_id')->constrained();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('document_number')->nullable();
            $table->enum('status', [
                'awaiting_payment', 'paid', 'partially_paid', 'overdue', 'cancelled', 'granted_free',
            ]);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('placement_histories');
    }
};
