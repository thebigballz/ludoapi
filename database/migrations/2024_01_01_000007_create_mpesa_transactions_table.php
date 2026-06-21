<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['stk_push', 'b2c']);
            $table->string('merchant_request_id')->unique();
            $table->string('checkout_request_id')->nullable();
            $table->string('mpesa_receipt')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('phone', 20);
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->json('raw_callback')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};