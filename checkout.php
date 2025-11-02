<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <title>Place Order</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>

    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <h2 class="text-3xl font-bold tracking-tight text-gray-900 mb-6">Place Order</h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <form action="place_order.php" method="POST" class="bg-white p-6 rounded-lg shadow-sm space-y-6">
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Contact</h3>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone number</label>
                        <input type="tel" name="phone" id="phone" required class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email (Optional)</label>
                        <input type="email" name="email" id="email" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Personal Info</h3>
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" name="full_name" id="full_name" required class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                        <textarea name="address" id="address" required class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label for="delivery_zone" class="block text-sm font-medium text-gray-700">Delivery zone</label>
                        <select 
                            name="delivery_zone" 
                            id="delivery_zone" 
                            class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            hx-get="checkout_summary.php"
                            hx-target="#order-summary"
                        >
                            <option value="Inside Dhaka">Inside Dhaka</option>
                            <option value="Outside Dhaka">Outside Dhaka</option>
                        </select>
                    </div>
                </div>
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Payment options</h3>
                    <div class="space-y-2">
                        <label class="flex items-center p-4 border border-gray-200 rounded-lg cursor-pointer has-[:checked]:bg-blue-50 has-[:checked]:border-blue-400">
                            <input type="radio" name="payment_method" value="cod" id="cod" checked onchange="onPaymentChange()" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <span class="ml-3 text-sm font-medium text-gray-900">Cash On Delivery</span>
                        </label>
                        <label class="flex items-center p-4 border border-gray-200 rounded-lg cursor-pointer has-[:checked]:bg-blue-50 has-[:checked]:border-blue-400">
                            <input type="radio" name="payment_method" value="Self MFS" id="bkash" onchange="onPaymentChange()" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <span class="ml-3 text-sm font-medium text-gray-900">bKash / MFS Payment</span>
                        </label>
                    </div>
                    <div id="bkash-instructions" class="hidden p-4 bg-gray-50 border border-gray-200 rounded-lg space-y-4">
                        <p class="text-sm">Send Money to: <strong class="text-base">0171100XXXX</strong> (Your bKash Number)</p>
                        <div>
                            <label for="payment_phone" class="block text-sm font-medium text-gray-700">Your payment phone number</label>
                            <input type="tel" name="payment_phone" id="payment_phone" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="payment_txid" class="block text-sm font-medium text-gray-700">TrxID (Transaction ID)</label>
                            <input type="text" name="payment_txid" id="payment_txid" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Add note</h3>
                    <div>
                        <textarea name="order_notes" placeholder="Add your delivery instructions" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                </div>
                <button type="submit" class="w-full inline-flex items-center justify-center rounded-md border border-transparent bg-green-600 px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-green-700">Confirm order</button>
            </form>

            <div 
                id="order-summary" 
                class="bg-white p-6 rounded-lg shadow-sm self-start lg:sticky lg:top-8"
                hx-get="checkout_summary.php"
                hx-trigger="load"
            >
                <!-- Order summary will be loaded here by HTMX -->
                <p>Loading order summary...</p>
            </div>
        </div>
    </div>
    <script>
    function onPaymentChange() {
        var method = document.querySelector('input[name="payment_method"]:checked').value;
        var instructions = document.getElementById('bkash-instructions');
        instructions.style.display = (method === 'Self MFS') ? 'block' : 'none';
    }
    onPaymentChange();
    </script>
</body>
</html>