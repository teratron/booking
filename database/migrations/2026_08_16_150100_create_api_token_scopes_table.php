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
        // A token's geographic/category narrowing, resolved the same way an
        // administrative role grant is: {@see \App\Services\Authorization\ScopeConstraint}.
        // Deliberately narrower than `role_scopes` — a token has no
        // territory-subtree axis and no `none`-kind sentinel row, since
        // "unrestricted" is simply the absence of any row for that axis, and
        // "which resources" is carried by the token's own Sanctum abilities
        // instead of a third scope kind here.
        Schema::create('api_token_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_access_token_id')->constrained()->cascadeOnDelete();
            $table->enum('scope_kind', ['country', 'category']);

            // Soft reference, deliberately: the target table depends on
            // scope_kind (countries or object_types), so a single hard
            // foreign key cannot express it. Validity is enforced in the
            // issuing service, not the schema.
            $table->unsignedBigInteger('scope_reference_id');

            $table->timestamps();

            $table->unique(
                ['personal_access_token_id', 'scope_kind', 'scope_reference_id'],
                'api_token_scopes_unique_grant'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_token_scopes');
    }
};
