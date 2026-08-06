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
        // The grant of one promotional label to one object for a window of
        // time — distinct from the "promotions" table (a content-publishing
        // entity: title, description, image), which is an unrelated concept
        // that happens to share a name in the source specification.
        // weight feeds the catalog ordering's promotion term: a label is
        // decoration, but its presence can still break ties within a tier.
        Schema::create('object_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_label_id')->constrained();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('weight')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('object_promotions');
    }
};
