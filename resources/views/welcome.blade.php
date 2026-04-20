<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Apply Coupon</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f9fafb; padding: 40px; }
        .checkout-container { max-width: 400px; background: white; padding: 24px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin: 0 auto; }
        .input-group { display: flex; gap: 8px; margin-top: 10px; }
        input[type="text"] { flex: 1; padding: 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 16px; text-transform: uppercase; }
        button { background-color: #000; color: #fff; border: none; padding: 10px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; transition: background 0.2s; }
        button:hover { background-color: #333; }
        button:disabled { background-color: #9ca3af; cursor: not-allowed; }
        
        /* Message states */
        .message { margin-top: 12px; font-size: 14px; display: none; align-items: center; gap: 8px;}
        .message.loading { display: flex; color: #6b7280; }
        .message.success { display: flex; color: #059669; }
        .message.error { display: flex; color: #dc2626; }

        /* Simple Spinner */
        .spinner { width: 16px; height: 16px; border: 2px solid #e5e7eb; border-top-color: #6b7280; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="checkout-container">
    <h3>Order Summary</h3>
    <p>Subtotal: $100.00</p>
    
    <label for="coupon-input" style="font-size: 14px; font-weight: 600;">Promo Code</label>
    <div class="input-group">
        <input type="hidden" id="cart_id" name="cart_id">
        <input type="text" id="coupon-input" placeholder="e.g. SUMMER20" autocomplete="off">
        <button id="apply-btn">Apply</button>
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e5e7eb;">
    <button id="checkout-btn" style="width: 100%; background-color: #059669;">Simulate Checkout & Pay</button>
    </div>

    <div id="status-message" class="message">
        <div id="spinner" class="spinner" style="display: none;"></div>
        <span id="status-text"></span>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
    const applyBtn = document.getElementById('apply-btn');
    const couponInput = document.getElementById('coupon-input');
    const statusMessage = document.getElementById('status-message');
    const statusText = document.getElementById('status-text');
    const spinner = document.getElementById('spinner');

    // Mock cart ID for this example
    
    const USER_ID=1;
    applyBtn.addEventListener('click', async () => {
        const code = couponInput.value.trim();
        if (!code) return;

        // 1. Set UI to "Loading" state
        setUIState('loading', 'Coupon verification in progress...');
        applyBtn.disabled = true;

        try {
            // 2. Send the initial request to the backend
            const response = await fetch('/apply-coupon', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                     'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ coupon_code: code, user_id:USER_ID })
            });

            if (response.status === 202) {
                const data = await response.json();
                
                $('#cart_id').val(data.cart_id)
                // 3. Start polling the backend using the returned job_id
                pollForCouponResult(data.job_id);
            } else {
                setUIState('error', 'Failed to communicate with server.');
                applyBtn.disabled = false;
            }
        } catch (error) {
            console.log(error)
            setUIState('error', 'Network error occurred.');
            applyBtn.disabled = false;
        }
    });

    async function pollForCouponResult(jobId) {
        const maxAttempts = 10; // Stop polling after 10 tries (e.g., 10 seconds)
        let attempts = 0;

        const interval = setInterval(async () => {
            attempts++;
            
            try {
                // You will need a simple GET route in Laravel to check the status of this job/cart
                const response = await fetch(`/coupon-status/${jobId}`);
                const data = await response.json();

                if (data.status === 'completed') {
                    clearInterval(interval);
                    setUIState('success', 'Coupon applied successfully!');
                    applyBtn.disabled = false;
                    // Trigger a UI refresh of the cart totals here
                } else if (data.status === 'failed') {
                    clearInterval(interval);
                    setUIState('error', data.reason || 'Coupon invalid or expired.');
                    applyBtn.disabled = false;
                } else if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    setUIState('error', 'Verification timed out. Please try again.');
                    applyBtn.disabled = false;
                }
                // If status is 'pending', the interval just runs again next second
            } catch (error) {
                clearInterval(interval);
                setUIState('error', 'Lost connection while verifying.');
                applyBtn.disabled = false;
            }
        }, 1000); // Poll every 1 second
    }

    function setUIState(state, message) {
        statusMessage.className = `message ${state}`;
        statusText.textContent = message;
        spinner.style.display = state === 'loading' ? 'block' : 'none';
    }

    const checkoutBtn = document.getElementById('checkout-btn');

    checkoutBtn.addEventListener('click', async () => {
        checkoutBtn.disabled = true;
        checkoutBtn.textContent = 'Processing Payment...';

        try {
            const response = await fetch('/mock-checkout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' // Make sure CSRF is passed
                },
                body: JSON.stringify({ cart_id: $('#cart_id').val(),user_id:USER_ID })
            });

            if (response.ok) {
                checkoutBtn.textContent = 'Payment Successful! Coupon Consumed.';
            }
        } catch (error) {
            checkoutBtn.textContent = 'Checkout Failed';
        }
    });
</script>
</body>
</html>