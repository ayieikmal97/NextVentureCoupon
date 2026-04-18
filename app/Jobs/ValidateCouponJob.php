<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use App\Models\Cart;
use Illuminate\Support\Facades\Cache;
use App\Models\CouponUsage;
use App\Models\CouponRules;

// App-Specific Services & Models
use App\Services\Coupon\CouponRuleEngine;
use App\Models\Coupon; // Replaced Repository with direct Model

class ValidateCouponJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $uniqueFor = 10; 

    public string $couponCode;
    public int $userId;
    public int $cartId;
    public string $jobId;

    public function __construct(string $couponCode, int $userId, int $cartId, string $jobId)
    {
        $this->couponCode = $couponCode;
        $this->userId = $userId;
        $this->cartId = $cartId;
        $this->jobId = $jobId;
    }

    public function uniqueId(): string
    {
        return $this->userId . '_' . $this->couponCode;
    }

    /**
     * Execute the job. Notice the repository is removed from the injection.
     */
    public function handle(CouponRuleEngine $engine): void
    {
        
        // 1. Fetch the LATEST rules directly using Eloquent
        $coupon = Coupon::where('code', $this->couponCode)
            ->where('is_active', true)
            ->with('rules') // Eager load the rules relationship
            ->first();
        
        if (!$coupon) {
            $this->failValidation('Coupon not found or inactive');
            return;
        }
        $rule=CouponRules::where('coupon_id',$coupon->id)->orderBy('id','desc')->first();
        $rules = json_decode($rule->rules_json, true);
        // 2. Run through Strategy/Chain of Responsibility rules
        $validationResult = $engine->validate($rule, $this->userId, $this->cartId,$coupon->id);
        
        if (!$validationResult->passed) {
            $this->failValidation($validationResult->reason, $coupon);
            return;
        }

        $maxGlobalUses = $rules['max_global_uses'] ?? 0;
        
        // 3. Attempt Atomic Reservation
        if ($this->reserveInRedis($coupon,$maxGlobalUses)) {
            UpdateCartJob::dispatch($this->cartId, $this->userId,$this->couponCode)->onQueue('default');
            $this->logEvent('reserved', $coupon);
            
            // Dispatch a delayed job to handle organic timeouts
            ReleaseExpiredReservationJob::dispatch($coupon->id, $this->userId,$this->cartId)
                ->delay(now()->addMinutes(5))
                ->onQueue('low');
            Cache::put("coupon_job_{$this->jobId}", ['status' => 'completed'], now()->addMinutes(5));
        } else {
            $this->failValidation('This coupon has reached its maximum global usage limit.', $coupon);
        }
    }

    protected function failValidation(string $reason, $coupon = null): void
    {
        $this->logEvent('failed', $coupon, ['reason' => $reason]);
        Cache::put("coupon_job_{$this->jobId}", [
            'status' => 'failed', 
            'reason' => $reason 
        ], now()->addMinutes(5));
    }

    protected function reserveInRedis($coupon, $maxGlobalUses): bool
    {
        $globalKey = "coupon:{$coupon->code}:global_uses";
        
        if (!Redis::exists($globalKey) && $maxGlobalUses > 0) {
            $this->globalCounter($coupon->id, $globalKey);
        }
        
        // If max_global_uses is 0 or missing, we assume it's unlimited.
        // We still reserve it for the user to prevent them from applying it twice.
        $isUnlimited = $maxGlobalUses <= 0 ? 1 : 0;
        
        $script = <<<LUA
        local global_key = KEYS[1]
        local user_reserve_key = KEYS[2]
        local max_uses = tonumber(ARGV[1])
        local ttl = tonumber(ARGV[2])
        local is_unlimited = tonumber(ARGV[3])

        if redis.call('EXISTS', user_reserve_key) == 1 then
            return 1 
        end

        if is_unlimited == 0 then
            local current_uses = tonumber(redis.call('GET', global_key) or '0')
            if current_uses >= max_uses then
                return 0 
            end
            redis.call('INCR', global_key)
        end

        redis.call('SETEX', user_reserve_key, ttl, 'reserved')
        return 1
LUA;

        $result = Redis::eval($script, 2, 
            "coupon:{$coupon->code}:global_uses", 
            "coupon:{$coupon->code}:reserved:{$this->userId}", 
            $maxGlobalUses, 
            300,
            $isUnlimited
        );

        return (bool) $result;
    }

    protected function globalCounter(int $couponId, string $globalKey): void
    {
        // Count how many times it was permanently consumed
        $consumedCount = CouponUsage::where('coupon_id', $couponId)
            ->count();
        
        // Restore the counter in Redis (set it to never expire organically)
        Redis::set($globalKey, $consumedCount);
    }

    protected function logEvent(string $eventName, $coupon = null, array $metadata = []): void
    {
        LogCouponEventJob::dispatch(
            $eventName, 
            $this->couponCode, 
            $this->userId, 
            $this->cartId, 
            $coupon ? $coupon->rules_snapshot : null, 
            $metadata
        )->onQueue('low');
    }
}