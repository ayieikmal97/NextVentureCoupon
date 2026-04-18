<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use App\Models\CouponUsage; // Or whatever model tracks consumed coupons

class ReleaseExpiredReservationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $couponCode;
    public int $userId;

    public int $cartId;

    public function __construct(string $couponCode, int $userId, int $cartId)
    {
        $this->couponCode = $couponCode;
        $this->userId = $userId;
        $this->cartId = $cartId;
    }

    public function handle(): void
    {
        // 1. Check if the coupon was officially consumed.
        // Assuming when checkout succeeds, an Order is created linking the user and coupon.
        $wasConsumed = CouponUsage::where('user_id', $this->userId)
            ->where('cart_id',$this->cartId)
            ->where('coupon_id', $this->couponCode)
            ->where('created_at', '>=', now()->subMinutes(10)) // Look at recent orders
            ->exists();

        if ($wasConsumed) {
            // The user successfully checked out. The ConsumeCouponJob (not shown here) 
            // should have already handled finalizing everything. We do nothing.
            return;
        }

        // 2. If NOT consumed, the user abandoned the cart. We must release the reservation.
        $this->releaseInRedis();
    
    }

    /**
     * Decrement the global counter and clean up any lingering user reservation keys.
     */
    protected function releaseInRedis(): void
    {
        $globalKey = "coupon:{$this->couponCode}:global_uses";
        $userReserveKey = "coupon:{$this->couponCode}:reserved:{$this->userId}";

        // A simple Lua script to ensure we don't drop the global counter below zero
        $script = <<<LUA
        local global_key = KEYS[1]
        local user_reserve_key = KEYS[2]

        -- Delete the user reservation key if it hasn't expired organically yet
        redis.call('DEL', user_reserve_key)

        -- Decrement global uses safely (don't go below 0)
        local current_uses = tonumber(redis.call('GET', global_key) or '0')
        if current_uses > 0 then
            redis.call('DECR', global_key)
        end
        
        return 1
LUA;

        Redis::eval($script, 2, $globalKey, $userReserveKey);
    }
}