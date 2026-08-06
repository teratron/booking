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
     */
    public function up(): void
    {
        // A module that cannot function without another declares it here —
        // e.g. booking depends on guest_accounts. The registry's own
        // detailed model declares this relation explicitly even though a
        // shorter summary elsewhere lists only the two core module tables;
        // treated as an omission in the shorter list, not a deliberate
        // exclusion.
        Schema::create('module_dependencies', function (Blueprint $table) {
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_module_id')->constrained('modules')->cascadeOnDelete();

            $table->primary(['module_id', 'depends_on_module_id']);
        });

        DB::statement(
            'alter table module_dependencies add constraint module_dependencies_not_self check '.
            '(module_id <> depends_on_module_id)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_dependencies');
    }
};
