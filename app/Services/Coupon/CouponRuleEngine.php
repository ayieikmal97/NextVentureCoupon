<?php

namespace App\Services\Coupon;

use App\Models\CouponRules;
use App\Models\Cart;
use Carbon\Carbon;

class CouponRuleEngine
{
    public function validate(CouponRules $couponRules, int $userId, int $cartId,int $couponId): object
    {
        
        $cart = Cart::find($cartId);
        $rules = json_decode($couponRules->rules_json, true);
        
        // Map the JSON keys to specific validation methods
        foreach ($rules as $ruleKey => $ruleValue) {
            $result = $this->applyRule($ruleKey, $ruleValue, $cart, $userId,$couponId);
            
            if (!$result->passed) {
                return $result; // Fail fast
            }
        }

        return (object) ['passed' => true, 'reason' => ''];
    }

    private function applyRule(string $ruleKey, $ruleValue, $cart, int $userId,int $couponId): object
    {
        return match($ruleKey) {
            'is_active' => $this->checkIsActive($ruleValue),
            'expires_at' => $this->checkExpiry($ruleValue),
            'min_cart_value' => $this->checkMinCartValue($ruleValue, $cart),
            'first_time_user' => $this->checkFirstTimeUser($ruleValue, $userId),
            'max_user_uses' => $this->checkMaxUserUses($ruleValue, $userId, $couponId),
            
            // Ignore keys that aren't strict validation rules (like max_global_uses or discount_value)
            default => (object) ['passed' => true, 'reason' => '']
        };
    }

    private function checkIsActive($isActive): object
    {
        
        return (object) [
            'passed' => $isActive === true,
            'reason' => 'This coupon is no longer active.'
        ];
    }

    private function checkExpiry($expiresAt): object
    {
        return (object) [
            'passed' => Carbon::parse($expiresAt)->isFuture(),
            'reason' => 'This coupon has expired.'
        ];
    }

    private function checkMinCartValue($minValue, $cart): object
    {
        
        return (object) [
            // Assuming Cart is an Eloquent model with a 'total_amount' column
            'passed' => $cart->total_amount >= $minValue,
            'reason' => "Cart total must be at least {$minValue} to use this coupon."
        ];
    }

    private function checkFirstTimeUser($mustBeFirstTime, int $userId): object
    {
        if (!$mustBeFirstTime) return (object) ['passed' => true];

        
        $hasOrders = \App\Models\CouponUsage::where('user_id', $userId)->exists();
        //$hasOrders = false; 

        return (object) [
            'passed' => !$hasOrders,
            'reason' => 'This coupon is for first-time customers only.'
        ];
    }

    private function checkMaxUserUses(int $maxUses, int $userId, int $couponId): object
    {
        // If it's set to 0, assume unlimited per user
        if ($maxUses <= 0) {
            return (object) ['passed' => true];
        }

        // Count how many times this user has successfully used this coupon in the past.
        // Replace 'Order' with whatever model tracks your successful checkouts.
        $pastUses = \App\Models\CouponUsage::where('user_id', $userId)
            ->where('coupon_id', $couponId)
            ->count();

        return (object) [
            'passed' => $pastUses < $maxUses,
            'reason' => "You have already reached the maximum usage limit for this coupon."
        ];
    }
}