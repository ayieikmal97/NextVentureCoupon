<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use App\Models\Cart;
use App\Models\CouponUsage;

class ConsumeCouponJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $cartId;
    public int $userId;

    public function __construct(int $cartId, int $userId)
    {
        $this->cartId = $cartId;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $cart = Cart::find($this->cartId);
        
        if (!$cart || !$cart->coupon_id || $cart->status === 'paid') {
            return; // Nothing to do
        }

        
        // 1. Check if the user STILL holds the reservation in Redis
        $userReserveKey = "coupon:{$cart->coupon_code}:reserved:{$this->userId}";
        
        if (!Redis::exists($userReserveKey)) {
            // The reservation expired! They took too long.
            // Strip the discount from the cart.
            $cart->update([
                'coupon_code' => null,
                'coupon_id' => null,
                'discount_amount' => 0,
            ]);

            // You could optionally throw an exception here so the frontend knows to show an error
            // throw new \Exception('Coupon reservation expired. Please apply it again.');
            return;
        }

        // 2. If the reservation is still active, officially consume it
        CouponUsage::create([
            'coupon_id' => $cart->coupon_id,
            'user_id' => $this->userId,
            'cart_id' => $cart->id,
            'discount_amount' => $cart->discount_amount,
        ]);

        // 3. Mark cart as paid
        $cart->update(['status' => 'paid']);

        // 4. Log the event
        LogCouponEventJob::dispatch(
            'consumed', 
            $cart->coupon_code, 
            $this->userId, 
            $this->cartId, 
            null, 
            ['message' => 'Checkout success']
        )->onQueue('low');
    }
}
