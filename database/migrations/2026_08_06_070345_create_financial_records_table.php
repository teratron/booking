<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A ledger, not a payment system — it records that money changed
        // hands elsewhere, for both revenue streams this portal has: a
        // placement package sold to an object owner, or advertising sold to
        // an advertiser who may own no object at all. Exactly one of
        // object_id/banner_id is set, mirroring the favorites table's
        // exactly-one-identity pattern, since Postgres never treats two
        // NULLs as equal and a plain CHECK across both nullable columns is
        // both cheaper and clearer than a polymorphic pair for exactly two
        // known subject kinds.
        Schema::create('financial_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('banner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service');
            $table->foreignId('placement_package_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->timestamp('paid_at')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('document_number')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('responsible_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', [
                'awaiting_payment', 'paid', 'partially_paid', 'overdue', 'cancelled', 'granted_free',
            ]);
            $table->timestamps();
        });

        DB::statement(
            'alter table financial_records add constraint financial_records_exactly_one_subject check '.
            '((object_id is not null and banner_id is null) or (object_id is null and banner_id is not null))'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_records');
    }
};
