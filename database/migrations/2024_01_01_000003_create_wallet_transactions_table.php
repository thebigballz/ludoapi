<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['deposit', 'withdrawal', 'stake', 'win', 'refund', 'fee']);
            $table->enum('status', ['pending', 'completed', 'failed', 'reversed'])->default('pending');
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference')->unique();
            $table->string('description')->nullable();
            $table->string('transactionable_type')->nullable();
            $table->unsignedBigInteger('transactionable_id')->nullable();
            $table->index(['transactionable_type', 'transactionable_id'], 'transactionable_index');
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};