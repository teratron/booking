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
        // A module is a registry record, not a code branch — its identity,
        // default state, and dependency graph are data, so enabling online
        // booking for one country is a data operation, never a deployment.
        // scopable_levels is the subset of the ladder (portal, country,
        // category, owner, object) a given module may be overridden at —
        // e.g. payment only scopes to portal/country, never to a single
        // object.
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->enum('default_state', ['enabled', 'disabled']);
            $table->jsonb('scopable_levels');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
