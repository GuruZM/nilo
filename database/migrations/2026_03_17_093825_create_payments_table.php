<?php

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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('ZMW');
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('pop_file_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
