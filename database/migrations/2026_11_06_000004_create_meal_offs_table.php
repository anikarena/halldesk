<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Meals are ON by default for every resident. A row here means the student
        // turned one meal OFF for one date, so only the exceptions are stored.
        Schema::create('meal_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // lunch | dinner
            $table->string('meal', 10);
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'date', 'meal']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_offs');
    }
};
