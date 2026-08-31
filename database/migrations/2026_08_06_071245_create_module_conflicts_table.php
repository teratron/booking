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
        // Reserved — no launch module declares a conflict; the module
        // registry has none today. Shaped now rather than retrofitted
        // later, mirroring module_dependencies exactly, since the source
        // model declares both "dependencies" and "conflicts" as
        // first-class relations on Module.
        Schema::create('module_conflicts', function (Blueprint $table) {
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conflicts_with_module_id')->constrained('modules')->cascadeOnDelete();

            $table->primary(['module_id', 'conflicts_with_module_id']);
        });

        DB::statement(
            'alter table module_conflicts add constraint module_conflicts_not_self check '.
            '(module_id <> conflicts_with_module_id)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_conflicts');
    }
};
