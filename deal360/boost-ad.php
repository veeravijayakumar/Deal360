<?php
require_once 'config.php';
requireLogin();
 $uid = $_SESSION['user_id'];

/* Plan featured credits available */
 $sub  = activeSubscription($pdo, $uid, 'seller');
 $plan = planInfo($pdo, $sub['plan_code'], 'seller');
 $creditsLeft = max(0, (int)$plan['featured_limit'] - (int)$sub['featured_used']);

/* ================= HANDLE BOOST PURCHASE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lid    = (int)($_POST['listing_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM listings WHERE id=? AND user_id=? AND status='approved'");
    $stmt->execute([$lid, $uid]);
    $ad = $stmt->fetch();
    if (!$ad) {
        flash('Ad not found or not approved yet — only approved ads can be boosted.', 'error');
        header('Location: boost-ad.php'); exit;
    }

    /* Stack: if already boosted, new time adds on top of existing expiry */
    $base = ($ad['is_featured_until'] && strtotime($ad['is_featured_until']) > time())
          ? strtotime($ad['is_featured_until']) : time();

    if ($action === 'credit') {
        if ($creditsLeft <= 0) {
            flash('No featured credits left on your plan — buy a boost package instead.', 'error');
            header('Location: boost-ad.php'); exit;
        }
        $days = 7; $amount = 0; $pkgId = null; $source = 'plan_credit';
        $txn  = 'CREDIT-' . uniqid();
        $pdo->prepare("UPDATE subscriptions SET featured_used = featured_used + 1 WHERE id=?")
            ->execute([$sub['id']]);

    } elseif ($action === 'package') {
        $stmt = $pdo->prepare("SELECT * FROM boost_packages WHERE id=? AND is_active=1");
        $stmt->execute([(int)($_POST['package_id'] ?? 0)]);
        $pkg = $stmt->fetch();
        if (!$pkg) { flash('Invalid package.', 'error'); header('Location: boost-ad.php'); exit; }
        $days = (int)$pkg['days']; $amount = (float)$pkg['price'];
        $pkgId = (int)$pkg['id'];  $source = 'package';
        $txn = 'BOOST-' . uniqid();

        /* 💡 RAZORPAY HOOK: create Razorpay order → checkout → verify signature
           BEFORE this insert. Dev mode activates instantly, like upgrade.php */
        $pdo->prepare("INSERT INTO payments (user_id,user_type,plan_code,amount,txn_id,status)
                       VALUES (?,?,?,?,?,'paid')")
            ->execute([$uid, 'seller', 'boost_' . $days . 'd', $amount, $txn]);
    } else {
        header('Location: boost-ad.php'); exit;
    }

    $newExpiry = date('Y-m-d H:i:s', $base + $days * 86400);
    $pdo->prepare("INSERT INTO featured_orders
                   (listing_id,user_id,package_id,source,amount,days,starts_at,expires_at,txn_id)
                   VALUES (?,?,?,?,?,?,NOW(),?,?)")
        ->execute([$lid, $uid, $pkgId, $source, $amount, $days, $newExpiry, $txn]);
    $pdo->prepare("UPDATE listings SET is_featured=1, is_featured_until=? WHERE id=?")
        ->execute([$newExpiry, $lid]);

    flash("⚡ Boost active! <b>" . htmlspecialchars($ad['title']) . "</b> is featured until "
        . date('d M Y, h:i A', strtotime($newExpiry)));
    header('Location: boost-ad.php'); exit;
}

/* ================= DISPLAY ================= */
refreshFeaturedFlags($pdo);
 $preselect = (int)($_GET['listing_id'] ?? 0);

 $ads = $pdo->prepare("SELECT id,title,price,segment,is_featured,is_featured_until
                      FROM listings WHERE user_id=? AND status='approved' ORDER BY created_at DESC");
 $ads->execute([$uid]);
 $myAds = $ads->fetchAll();

 $packages = $pdo->query("SELECT * FROM boost_packages WHERE is_active=1 ORDER BY price")->fetchAll();

require 'includesheader.php';
?>
<div class="card">
  <h2>⚡ Boost Your Ads</h2>
  <p class="muted">Boosted ads appear <b>at the top of listings</b> with a ⚡ FEATURED badge — up to
     <b>5× more views</b>. You have <b><?= $creditsLeft ?></b> free plan credit<?= $creditsLeft==1?'':'s' ?>
     (1 credit = 7 days) this month.</p>
</div>

<?php if (!$myAds): ?>
  <div class="card" style="text-align:center;">
    <p>You have no approved ads to boost yet.</p>
    <a href="post-ad.php" class="btn btn-accent">+ Post Your First Ad</a>
  </div>
<?php else: ?>

<form method="post" id="boostForm">
  <div class="card">
    <h2>1️⃣ Choose Your Ad</h2>
    <table>
      <tr><th></th><th>Ad</th><th>Segment</th><th>Price</th><th>Boost Status</th></tr>
      <?php foreach ($myAds as $a):
            $boosted = $a['is_featured'] && strtotime($a['is_featured_until']) > time(); ?>
      <tr>
        <td><input type="radio" name="listing_id" value="<?= $a['id'] ?>" required
                   <?= $preselect===(int)$a['id'] ? 'checked' : '' ?>></td>
        <td><b><?= htmlspecialchars($a['title']) ?></b></td>
        <td><?= ucfirst($a['segment']) ?></td>
        <td>₹<?= number_format((float)$a['price']) ?></td>
        <td><?php if ($boosted): ?>
              <span class="tag ok">⚡ Active till <?= date('d M, h:i A', strtotime($a['is_featured_until'])) ?></span>
              <span class="muted">(buying again adds time)</span>
            <?php else: ?><span class="tag no">Not boosted</span><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card">
    <h2>2️⃣ Choose Boost Duration</h2>
    <div class="grid2" style="margin-bottom:14px;">
      <?php foreach ($packages as $p): ?>
      <label style="border:2px solid #e2e8f0;border-radius:12px;padding:16px;cursor:pointer;display:block;">
        <input type="radio" name="package_id" value="<?= $p['id'] ?>" style="margin-right:8px;" <?= $p['days']==7?'checked':'' ?>>
        <b><?= htmlspecialchars($p['name']) ?></b><br>
        <span style="font-size:1.4rem;font-weight:800;color:#ea580c;">₹<?= number_format((float)$p['price']) ?></span><br>
        <span class="muted"><?= $p['days'] ?> days top placement</span>
      </label>
      <?php endforeach; ?>
    </div>

    <button name="action" value="package" class="btn btn-accent"
            onclick="return confirmBoost('package')">💳 Pay & Boost Now</button>

    <?php if ($creditsLeft > 0): ?>
      <button name="action" value="credit" class="btn btn-primary"
              onclick="return confirmBoost('credit')">🎁 Use 1 Plan Credit (7 days free)</button>
    <?php endif; ?>
  </div>
</form>
<?php endif; ?>

<script>
function confirmBoost(type){
  if (!document.querySelector('input[name=listing_id]:checked')) {
    alert('Please select an ad first.'); return false;
  }
  return type === 'package'
    ? confirm('Pay for this boost and feature your ad at the top?')
    : confirm('Use 1 featured credit (7 days) on this ad?');
}
</script>
<?php require 'includesfooter.php'; ?>