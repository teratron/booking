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
        // First-class, ordered, per-object records — not fixed columns —
        // so "other social networks" and future channels are a data
        // operation. derived_link is computed from raw_value plus the
        // channel type's own link_template and cached here rather than
        // recomputed on every render. label is a single optional override,
        // not its own translation table: most channels leave it empty and
        // fall back to the channel type's own translated display name,
        // which already carries the per-language text this field would
        // otherwise duplicate.
        Schema::create('contact_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_channel_type_id')->constrained();
            $table->string('raw_value');
            $table->string('derived_link')->nullable();
            $table->string('label')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_channels');
    }
};
