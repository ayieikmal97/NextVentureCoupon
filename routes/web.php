<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CouponController;
use App\Jobs\ConsumeCouponJob;
use Illuminate\Http\Request;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::post('/apply-coupon', [CouponController::class, 'store']);
Route::get('/coupon-status/{jobId?}', [CouponController::class, 'checkStatus']);
Route::post('/mock-checkout', [CouponController::class, 'mockCheckout']);
// Route::post('/mock-checkout', function (Request $request) {
//     // In a real app, this would happen after Stripe/PayPal confirms payment
    
//     // Dispatch the consumption job
//     ConsumeCouponJob::dispatch(
//         1, 
//         $request->user_id, // Hardcoded user ID for testing, replace with $request->user()->id later
//     )->onQueue('default');

//     return response()->json(['status' => 'Checkout simulated, coupon consumption in progress']);
// });