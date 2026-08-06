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
        // Staff and limited managers, distinct from objects.owner_id (the
        // primary owner). A staff member with limited rights sees only what
        // this row's permissions grant — evaluated per object, not via the
        // portal-wide role/permission system spatie/laravel-permission
        // supplies. Moved here from the identity/access migration batch: it
        // needs objects to exist for its foreign key.
        Schema::create('object_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->jsonb('permissions')->nullable();
            $table->timestamps();

            $table->unique(['object_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('object_user');
    }
};
