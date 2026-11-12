<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Preferred room size, null means any room
            $table->unsignedTinyInteger('preferred_capacity')->nullable();
            $table->text('reason');
            $table->string('special_needs')->nullable();
            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending')->index();
            $table->text('decision_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('seat_applications')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            // active | ended
            $table->string('status', 20)->default('active')->index();
            $table->string('end_reason')->nullable();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();

            // These two columns hold the seat id and user id only while the allocation
            // is active and are set to NULL when it ends. Because both are UNIQUE, the
            // database itself refuses a second active allocation for the same seat or
            // the same student, even if two admins click "Allocate" at the same moment.
            $table->unsignedBigInteger('active_seat_id')->nullable()->unique();
            $table->unsignedBigInteger('active_user_id')->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('seat_applications');
    }
};
