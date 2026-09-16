<?php
/* Server-to-server. Razorpay calls this — no session, no HTML output. */
require_once __DIR__ . '/config.php';

 $raw = file_get_contents('php://input');
 $sig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (!rzpVerifyWebhook($raw, $sig)) { http_response_code(400); echo 'bad signature'; exit; }

 $event = json_decode($raw, true);
 $type  = $event['event'] ?? '';
 $ent   = $event['payload']['payment']['entity'] ?? [];

if ($type === 'payment.captured') {
    processWebhookPayment($pdo, $ent['order_id'] ?? '', $ent['id'] ?? '');
}
elseif ($type === 'payment.failed') {
    $pdo->prepare("UPDATE payments SET status='failed'
                   WHERE rzp_order_id=? AND status='pending'")
        ->execute([$ent['order_id'] ?? '']);
}
echo 'ok';