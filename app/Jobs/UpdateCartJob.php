<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
// use App\Events\CartUpdated; // If using websockets

class UpdateCartJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $cartId;

    public int $userId;
    public string $couponCode;

    public function __construct(int $cartId, int $userId,string $couponCode)
    {
        $this->cartId = $cartId;
        $this->userId = $userId;
        $this->couponCode = $couponCode;
    }

    public function handle(): void
    {
        $cart = Cart::find($this->cartId);
        $coupon = Coupon::where('code', $this->couponCode)->first();

        // If either is missing, or cart is already paid, abort safely
        if (!$cart || !$coupon || $cart->status === 'paid') {
            return;
        }

        // Calculate discount (Example logic)
        $discountAmount = 0;
        if ($coupon->discount_type === 'percentage') {
            $discountAmount = $cart->total_amount * ($coupon->discount_percentage / 100);
        } elseif ($coupon->discount_type === 'fixed') {
            $discountAmount = $coupon->discount_value;
        }

        // Ensure discount doesn't exceed cart subtotal
        $discountAmount = min($discountAmount, $cart->total_amount);
        
        // Update the cart
        $cart->update([
            'discount_amount' => $discountAmount,
            'coupon_id'=>$coupon->id,
        ]);

        CouponUsage::create([
            'coupon_id'=>$coupon->id,
            'user_id'=>$this->userId,
            'cart_id'=>$this->cartId,
        ]);
        // Broadcast to the frontend so the UI updates instantly
        // broadcast(new CartUpdated($this->cartId, $cart->total_amount));
    }
}