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
        Schema::create('coupon_events', function (Blueprint $table) {
           $table->id();
            $table->string('event_type')->index(); // e.g., reserved, failed, released
            $table->string('coupon_code')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('cart_id')->nullable();
            
            // JSON columns are crucial here so you can search/audit historic rule states
            $table->json('rule_snapshot')->nullable(); 
            $table->json('metadata')->nullable();      
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_events');
    }
};
