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
        // A bump is scope-specific: it reorders an object within one
        // territory or one object-type list, never globally. scope_type/
        // scope_id is a polymorphic pair rather than two nullable foreign
        // keys because exactly one of Territory or ObjectType is the scope
        // on any given row, and the catalog's ordering query reads the most
        // recent bump for the scope being rendered, not a single global
        // timestamp — a design mistake this migration deliberately avoids.
        Schema::create('bump_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('placement_package_id')->constrained();
            $table->morphs('scope');
            $table->timestamp('occurred_at');
            $table->enum('type', ['free', 'paid', 'automatic', 'owner', 'administrator']);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('previous_position')->nullable();
            $table->unsignedInteger('new_position')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bump_events');
    }
};
