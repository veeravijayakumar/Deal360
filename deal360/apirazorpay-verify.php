<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['ok' => false, 'error' => 'Not logged in']); exit; }

 $oid = $_POST['razorpay_order_id']   ?? '';
 $pid = $_POST['razorpay_payment_id'] ?? '';
 $sig = $_POST['razorpay_signature']  ?? '';
if (!$oid || !$pid || !$sig) { echo json_encode(['ok' => false, 'error' => 'Missing payment data']); exit; }

[$ok, $msg] = processSuccessfulPayment($pdo, $oid, $pid, $sig);
echo json_encode($ok ? ['ok' => true, 'redirect' => 'dashboard.php?paid=1']
                     : ['ok' => false, 'error' => $msg]);