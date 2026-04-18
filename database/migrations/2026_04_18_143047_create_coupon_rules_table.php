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
        Schema::create('coupon_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coupon_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->unsignedInteger('version');
            $table->json('rules_json');

            $table->timestamps();

            $table->unique(['coupon_id', 'version']);
            $table->index('coupon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_rules');
    }
};
