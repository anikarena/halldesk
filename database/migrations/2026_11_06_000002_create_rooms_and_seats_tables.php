<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('number', 10)->unique();
            $table->unsignedTinyInteger('floor');
            $table->unsignedTinyInteger('capacity');
            // Rent per seat per month, whole taka
            $table->unsignedInteger('monthly_rent');
            // available | maintenance
            $table->string('status', 20)->default('available')->index();
            $table->boolean('has_attached_bath')->default(false);
            $table->boolean('has_balcony')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('label', 2);
            $table->timestamps();

            $table->unique(['room_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
        Schema::dropIfExists('rooms');
    }
};
