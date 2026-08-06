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
        // Dormant booking module — see room_availabilities. With payment
        // off, a reservation is a request (pending -> confirmed/declined/
        // expired by the owner); with payment on, it is prepaid (pending,
        // held for a checkout window, confirmed only by successful
        // payment). commission_rate is nullable and carries no default: the
        // ledger records the answer either way, but whether an activated
        // portal charges commission on bookings is an unresolved business
        // decision, not a technical one, per the source specification's own
        // TBD.
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained('users');
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedInteger('party_size');
            $table->enum('status', [
                'pending', 'confirmed', 'declined', 'expired', 'payment_failed', 'cancelled',
            ]);
            $table->string('payment_reference')->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
