<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // All money columns are whole taka (integers) so totals never drift by
        // floating point rounding.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // monthly = generated hall bill, other = admission fee, fine, damage etc.
            $table->string('kind', 20)->default('monthly');
            // Billing month as YYYY-MM, only for monthly bills
            $table->char('period', 7)->nullable();
            $table->string('title');
            $table->date('due_date');
            $table->unsignedInteger('total');
            $table->unsignedInteger('paid')->default(0);
            // unpaid | partial | paid | cancelled
            $table->string('status', 20)->default('unpaid')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One monthly bill per student per month. Rows with a NULL period
            // (kind = other) are not limited by this index.
            $table->unique(['user_id', 'kind', 'period']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('amount');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            // cash | bkash | nagad | rocket | bank
            $table->string('method', 20);
            $table->string('transaction_id', 40)->nullable()->index();
            // pending | verified | rejected
            $table->string('status', 20)->default('pending')->index();
            $table->string('note')->nullable();
            $table->string('review_note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
