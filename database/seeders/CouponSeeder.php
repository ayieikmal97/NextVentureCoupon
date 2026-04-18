<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        // -----------------------------
        // COUPON 1: FIRST10
        // -----------------------------
        $coupon1Id = DB::table('coupons')->insertGetId([
            'code' => 'FIRST10',
            'name' => 'First Order 10% Off',
            'description' => '10% discount for first-time users',
            'discount_percentage'=>10,
            'usage_limit' => 1000,
            'per_user_limit' => 1,
            'valid_from' => Carbon::now()->subDay(),
            'valid_to' => Carbon::now()->addMonth(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Version 1
        DB::table('coupon_rules')->insert([
            'coupon_id' => $coupon1Id,
            'version' => 1,
            'rules_json' => json_encode([
                'is_active' => true,
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'max_global_uses' => 1000,
                'max_user_uses' => 1,
                'min_cart_value' => 50,
                'valid_categories' => ['shoes', 'apparel'],
                'expires_at' => Carbon::now()->addMonths(1)->toIso8601String(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Version 2 (rule changed)
        DB::table('coupon_rules')->insert([
            'coupon_id' => $coupon1Id,
            'version' => 2,
            'rules_json' => json_encode([
                'is_active' => true,
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'max_global_uses' => 1000,
                'max_user_uses' => 1,
                'min_cart_value' => 100,
                'valid_categories' => ['shoes', 'apparel'],
                'expires_at' => Carbon::now()->addMonths(1)->toIso8601String(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // -----------------------------
        // COUPON 2: ELECTRO20
        // -----------------------------
        $coupon2Id = DB::table('coupons')->insertGetId([
            'code' => 'ELECTRO20',
            'name' => 'Electronics RM20 Off',
            'description' => 'Flat RM20 off electronics category',
            'usage_limit' => 500,
            'per_user_limit' => 2,
            'discount_percentage'=>20,
            'valid_from' => Carbon::now()->subDay(),
            'valid_to' => Carbon::now()->addWeeks(2),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('coupon_rules')->insert([
            'coupon_id' => $coupon2Id,
            'version' => 1,
            'rules_json' => json_encode([
                'is_active' => true,
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'max_global_uses' => 1000,
                'max_user_uses' => 1,
                'min_cart_value' => 100,
                'valid_categories' => ['electronic'],
                'expires_at' => Carbon::now()->addMonths(1)->toIso8601String(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // -----------------------------
        // COUPON 3: FLASH50
        // -----------------------------
        $coupon3Id = DB::table('coupons')->insertGetId([
            'code' => 'FLASH50',
            'name' => 'Flash Sale RM50 Off',
            'description' => 'Limited flash sale coupon',
            'usage_limit' => 100,
            'per_user_limit' => 1,
            'discount_percentage'=>50,
            'valid_from' => Carbon::now()->subMinutes(10),
            'valid_to' => Carbon::now()->addHours(2),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('coupon_rules')->insert([
            'coupon_id' => $coupon3Id,
            'version' => 1,
            'rules_json' => json_encode([
                'min_cart_value' => 300,
                'first_time_user' => false,
                'discount_type' => 'fixed',
                'discount_value' => 50,
                'time_window' => [
                    'start' => Carbon::now()->subMinutes(10)->toDateTimeString(),
                    'end' => Carbon::now()->addHours(2)->toDateTimeString()
                ]
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}