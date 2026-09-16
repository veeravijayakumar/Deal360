<?php
require_once 'config.php';
requireLogin();

 $pid = (int)($_GET['id'] ?? 0);
 $stmt = $pdo->prepare("SELECT * FROM payments WHERE id=? AND user_id=? AND status='pending'");
 $stmt->execute([$pid, $_SESSION['user_id']]);
 $pay = $stmt->fetch();
if (!$pay) {
    flash('Payment session not found (already paid or expired). Start again.', 'error');
    header('Location: dashboard.php'); exit;
}
 $me = getUserById((int)$_SESSION['user_id']);

if ($pay['purpose'] === 'subscription') {
    $plan = planInfo($pdo, $pay['plan_code'], $pay['user_type']);
    $desc = $plan['name'] . ' ' . $pay['user_type'] . ' plan — 30 days';
} else {
    $days = json_decode($pay['meta'] ?: '{}', true)['days'] ?? 7;
    $desc = "Featured Ad Boost — {$days} days";
}
require 'includesheader.php';
?>
<div class="card" style="max-width:480px;margin:40px auto;text-align:center;">
  <h2>💳 Secure Payment</h2>
  <p class="muted"><?= htmlspecialchars($desc) ?></p>
  <div style="font-size:2.4rem;font-weight:800;color:#ea580c;margin:14px 0;">
    ₹<?= number_format((float)$pay['amount']) ?></div>
  <p class="muted">Order: <b><?= htmlspecialchars($pay['rzp_order_id']) ?></b><br>
     Pay securely via UPI · Cards · NetBanking · Wallets</p>
  <button id="payBtn" class="btn btn-accent" style="width:100%;margin-top:14px;">Pay ₹<?= number_format((float)$pay['amount']) ?> Now</button>
  <p class="muted" style="margin-top:12px;"><a href="dashboard.php">Cancel & go back</a></p>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
function openRzp(){
  var rzp = new Razorpay({
    key: '<?= RZP_KEY_ID ?>',
    amount: <?= (int)round($pay['amount'] * 100) ?>,
    currency: 'INR',
    name: 'deal360.shop',
    description: '<?= htmlspecialchars($desc) ?>',
    order_id: '<?= $pay['rzp_order_id'] ?>',
    prefill: {
      name: '<?= htmlspecialchars($me['name']) ?>',
      email: '<?= htmlspecialchars($me['email']) ?>',
      contact: '<?= htmlspecialchars($me['mobile'] ?? '') ?>'
    },
    theme: { color: '#1d4ed8' },
    handler: function (resp) {
      document.getElementById('payBtn').disabled = true;
      document.getElementById('payBtn').textContent = 'Verifying...';
      fetch('apirazorpay-verify.php', { method: 'POST',
            body: new URLSearchParams(resp) })
        .then(r => r.json())
        .then(d => {
          if (d.ok) location.href = d.redirect;
          else { alert('Verification failed: ' + d.error); location.href = 'dashboard.php'; }
        });
    }
  });
  rzp.on('payment.failed', function(){ alert('Payment failed — you can retry.'); });
  rzp.open();
}
document.getElementById('payBtn').onclick = openRzp;
openRzp();   // auto-open for smooth UX
</script>
<?php require 'includesfooter.php'; ?>