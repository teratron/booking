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
        // spatie/laravel-permission supplies roles, permissions, and the
        // model_has_roles pivot, but not [TZ] §121's scoping of a grant to a
        // country, a territory subtree, or an object category. This table is
        // this project's addition, consumed by Policies to narrow a role
        // grant that spatie's own tables treat as unscoped.
        Schema::create('role_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->enum('scope_kind', ['none', 'country', 'territory', 'category']);

            // Soft reference, deliberately: the target table depends on
            // scope_kind (countries, territories, or object_types), so a
            // single hard foreign key cannot express it. Validity is
            // enforced in the granting service and in Policies, not the
            // schema.
            $table->unsignedBigInteger('scope_reference_id')->nullable();

            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('granted_at');
            $table->timestamps();

            $table->index(['scope_kind', 'scope_reference_id']);
            $table->unique(
                ['user_id', 'role_id', 'scope_kind', 'scope_reference_id'],
                'role_scopes_unique_grant'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_scopes');
    }
};
