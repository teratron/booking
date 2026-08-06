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
        // Append-only — the record of every transition, distinct from the
        // "current" state embedded on objects itself. Written in the same
        // transaction as the availability update, never on its own: a
        // status whose history has a gap cannot answer who changed it and
        // when. Privilege-level append-only enforcement (revoking UPDATE
        // and DELETE) is the dedicated retention-rules task's concern, not
        // this one — this migration only shapes the columns.
        Schema::create('availability_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->enum('to_status', ['available', 'unavailable', 'unspecified']);
            $table->timestamp('changed_at');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('source', ['owner', 'administrator', 'automatic']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_histories');
    }
};
