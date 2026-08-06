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
        // Whether a reviewer must be a registered visitor or may post as a
        // named guest is unresolved upstream, so author_id stays nullable
        // and author_name carries the guest-supplied name when it is. An
        // owner may reply and may report, never edit or delete; hiding a
        // review after an upheld report reuses soft delete rather than a
        // parallel "hidden" state, with hidden_reason recording why —
        // administrator removal always carries a reason, never a silent one.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name')->nullable();

            $table->text('owner_reply')->nullable();
            $table->timestamp('owner_replied_at')->nullable();

            $table->enum('status', ['pending', 'published', 'rejected'])->default('pending');

            $table->timestamp('reported_at')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('report_reason')->nullable();
            $table->text('hidden_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
