<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds what the object-owner management screen needs on `users`, and
     * relaxes `objects.owner_id` to nullable so a deliberate detachment (or,
     * as a side effect, removing a user row for any other reason) leaves an
     * object ownerless instead of deleting it. `doctrine/dbal` is not a
     * project dependency, and adding it solely to let Laravel's fluent
     * `->nullable()->change()` alter an existing column is not worth the
     * extra package — the column change is raw SQL instead, with the
     * foreign key dropped and recreated under the new delete behaviour.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company')->nullable()->after('name');
            $table->string('phone')->nullable()->after('company');
            $table->foreignId('country_id')->nullable()->after('phone')->constrained('countries')->nullOnDelete();

            // blocked_at is the single source of truth for account status —
            // null means active. blocked_by mirrors the deleted_by /
            // availability_changed_by pattern already used elsewhere in this
            // schema: an accountability column sitting next to the timestamp
            // it explains.
            $table->timestamp('blocked_at')->nullable()->after('remember_token');
            $table->foreignId('blocked_by')->nullable()->after('blocked_at')->constrained('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE objects ALTER COLUMN owner_id DROP NOT NULL');

        Schema::table('objects', function (Blueprint $table) {
            // Laravel's conventional constraint name for a fluent
            // ->constrained() call on this column.
            $table->dropForeign('objects_owner_id_foreign');
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('objects', function (Blueprint $table) {
            $table->dropForeign('objects_owner_id_foreign');
        });

        DB::statement('ALTER TABLE objects ALTER COLUMN owner_id SET NOT NULL');

        Schema::table('objects', function (Blueprint $table) {
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['blocked_by']);
            $table->dropForeign(['country_id']);
            $table->dropColumn(['company', 'phone', 'country_id', 'blocked_at', 'blocked_by']);
        });
    }
};
