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
        // Moderation acts on changes, not on records — a pending edit never
        // overwrites the published version; both exist until a decision is
        // made. previous_data is nullable because a first-time submission
        // (e.g. a brand-new object awaiting its first review) has no prior
        // published state to snapshot; proposed_data is always present.
        // Storing the snapshot on the request itself, not as a reference to
        // the live record, is deliberate: if the live record changes
        // between submission and review, a reference-based diff would show
        // the moderator a comparison that never existed.
        Schema::create('moderation_requests', function (Blueprint $table) {
            $table->id();
            $table->morphs('target');
            $table->string('section');
            $table->jsonb('previous_data')->nullable();
            $table->jsonb('proposed_data');
            $table->foreignId('submitted_by')->constrained('users');
            $table->timestamp('submitted_at');
            $table->foreignId('assigned_moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('decision', ['pending', 'approved', 'rejected', 'revision_requested'])->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderation_requests');
    }
};
