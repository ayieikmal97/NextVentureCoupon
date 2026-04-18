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
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coupon_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cart_id')->nullable();

            $table->decimal('discount_amount', 10, 2)->nullable();

            $table->timestamp('used_at')->useCurrent();

            $table->timestamps();

            $table->index('coupon_id');
            $table->index(['user_id', 'coupon_id']);
            $table->index('cart_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
