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
        // The notification is a record, not a send — it exists as stored
        // data with a read state independently of whether any channel
        // delivered it; NotificationDispatch (the next migration) is what
        // tracks delivery. title/body are rendered at creation, in the
        // recipient's language, and stored — a template edited later must
        // never retroactively change what an owner was told. created_by is
        // nullable: null means system-triggered (a scheduled job), not an
        // administrator action.
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('notification_type_id')->constrained();
            $table->nullableMorphs('related');
            $table->string('title');
            $table->text('body');
            $table->string('locale', 10);
            $table->timestamp('read_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('languages')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
