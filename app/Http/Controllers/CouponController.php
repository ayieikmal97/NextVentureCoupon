<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ValidateCouponJob;
use App\Models\Cart;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class CouponController extends Controller
{
    public function store(Request $request)
    {
        
        //just for default to create cart
        $cart=Cart::create([
            'coupon_code'=>$request->coupon_code,
            'total_amount'=>100,
        ]);

        $request->validate(['coupon_code' => 'required']);
    
        $jobId = (string) Str::uuid(); // Used for idempotency and frontend polling

        // Dispatch to the high-priority queue
        ValidateCouponJob::dispatch(
            $request->coupon_code, 
            1, 
            $cart->id, 
            $jobId
        )->onQueue('high');

        return response()->json([
            'status' => 'Coupon verification in progress',
            'job_id' => $jobId
        ], 202);
    }

    public function checkStatus($jobId)
    {
        // Check if the job has written a result to the cache yet
        $result = Cache::get("coupon_job_{$jobId}");

        if (!$result) {
            // Job is still sitting in the queue or currently processing
            return response()->json(['status' => 'pending']);
        }

        // Return 'completed' or 'failed'
        return response()->json($result);
    }
}
