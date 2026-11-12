<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('student_id', 30)->unique();
            $table->string('department', 80);
            $table->string('program', 40)->default('BSc');
            $table->string('session', 20)->nullable();
            $table->unsignedTinyInteger('year_of_study')->default(1);
            $table->decimal('cgpa', 3, 2)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('home_district', 60)->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('guardian_name', 100)->nullable();
            $table->string('guardian_relation', 40)->nullable();
            $table->string('guardian_phone', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
