<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rulebook sections and notices with their embeddings, searched by Ask AI.
     */
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // "rules:1-rent-and-payment" or "notice:12"
            $table->string('title');
            $table->text('body');
            $table->string('url');
            $table->string('hash', 40)->default(''); // empty until the embedding is saved
            $table->longText('embedding')->nullable(); // JSON list of floats
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
