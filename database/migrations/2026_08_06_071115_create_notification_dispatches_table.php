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
        // Separating this from notifications is what makes retry and
        // multi-channel delivery cheap: a channel can be retried without
        // duplicating the notification, and a bounced email is discoverable
        // here rather than silently lost. A "suppressed" status (an
        // optional-class notification the recipient disabled) is recorded
        // rather than skipped, so an administrator can answer "why didn't
        // this owner know".
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_channel_id')->constrained();
            $table->enum('status', ['queued', 'sent', 'failed', 'suppressed'])->default('queued');
            $table->timestamp('attempted_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};
