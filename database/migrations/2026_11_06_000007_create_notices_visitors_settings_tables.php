<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            // general | dining | academic | maintenance | event
            $table->string('category', 20)->default('general');
            // normal | important | urgent
            $table->string('priority', 20)->default('normal')->index();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_public')->default(false);
            $table->timestamp('published_at')->index();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            // The resident being visited
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('relation', 40);
            $table->string('purpose')->nullable();
            $table->timestamp('check_in_at');
            $table->timestamp('check_out_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('check_out_at');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('notices');
    }
};
