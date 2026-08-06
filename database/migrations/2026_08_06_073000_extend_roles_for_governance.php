<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `spatie/laravel-permission`'s own `roles` table carries only `name`
     * and `guard_name` — it has no room for the translated display name or
     * the system flag the role registry needs, so both are added here
     * rather than by editing the package's published migration, which is
     * never touched after being applied.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Protects the launch role set from deletion through the back
            // office — administrators may still create and delete their
            // own custom roles, which ship with this flag false.
            $table->boolean('is_system')->default(false)->after('guard_name');
        });

        Schema::create('role_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('display_name');
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('languages')->cascadeOnDelete();
            $table->unique(['role_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_translations');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
