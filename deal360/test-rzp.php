<?php
require 'config.php';
echo '<pre>';

echo '1. cURL: ' . (extension_loaded('curl') ? '✅ loaded' : "❌ NOT loaded → Fix A\n") . "\n";

echo '2. Keys: ' . (RZP_KEY_ID === 'rzp_test_XXXXXXXXXXXX'
    ? "❌ still placeholders → Fix B\n"
    : '✅ set (' . substr(RZP_KEY_ID, 0, 14) . "...)\n");

echo "3. Testing Razorpay API...\n";
try {
    $o = rzpApi('orders', ['amount' => 100, 'currency' => 'INR', 'receipt' => 'TEST1']);
    echo "   ✅ SUCCESS — order created: {$o['id']}\n   Payment system is READY!\n";
} catch (Exception $e) {
    echo '   ❌ ' . $e->getMessage() . "\n";
    echo "   → 'unreachable' = Fix A (curl) | 'Authentication' = Fix B (keys)\n";
}
echo '</pre>';