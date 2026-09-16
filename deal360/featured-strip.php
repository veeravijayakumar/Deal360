<?php
require_once 'config.php';
requireLogin();
 $uid=$_SESSION['user_id'];
 $sub=activeSubscription($pdo,$uid,'seller'); $plan=planInfo($pdo,$sub['plan_code'],'seller');
 $creditsLeft=max(0,(int)$plan['featured_limit']-(int)$sub['featured_used']);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $lid=(int)($_POST['listing_id']??0); $action=$_POST['action']??'';
    $s=$pdo->prepare("SELECT * FROM listings WHERE id=? AND user_id=? AND status='approved'"); $s->execute([$lid,$uid]); $ad=$s->fetch();
    if (!$ad) { flash('Only approved ads can be boosted.','error'); header('Location: boost-ad.php'); exit; }
    $base=($ad['is_featured_until']&&strtotime($ad['is_featured_until'])>time())?strtotime($ad['is_featured_until']):time();
    if ($action==='credit') {
        if ($creditsLeft<=0) { flash('No credits left — buy a package.','error'); header('Location: boost-ad.php'); exit; }
        $days=7; $amount=0; $pkgId=null; $source='plan_credit'; $txn='CREDIT-'.uniqid();
        $pdo->prepare("UPDATE subscriptions SET featured_used=featured_used+1 WHERE id=?")->execute([$sub['id']]);
        $exp=date('Y-m-d H:i:s',$base+$days*86400);
        $pdo->prepare("INSERT INTO featured_orders (listing_id,user_id,package_id,source,amount,days,starts_at,expires_at,txn_id) VALUES (?,?,?,'plan_credit',0,?,NOW(),?,?)")->execute([$lid,$uid,null,$days,$exp,$txn]);
        $pdo->prepare("UPDATE listings SET is_featured=1,is_featured_until=? WHERE id=?")->execute([$exp,$lid]);
        flash("⚡ Boost active until ".date('d M Y, h:i A',strtotime($exp)));
        header('Location: boost-ad.php'); exit;
    } elseif ($action==='package') {
        $s=$pdo->prepare("SELECT * FROM boost_packages WHERE id=? AND is_active=1"); $s->execute([(int)($_POST['package_id']??0)]); $pkg=$s->fetch();
        if (!$pkg) { flash('Invalid package.','error'); header('Location: boost-ad.php'); exit; }
        try {
            $pid=createPendingPayment($pdo,$uid,'seller','boost','boost_'.(int)$pkg['days'].'d',(float)$pkg['price'],['listing'=>$lid,'pkg'=>(int)$pkg['id'],'days'=>(int)$pkg['days']]);
            header('Location: checkout.php?id='.$pid); exit;
        } catch (Exception $e) { flash('Payment could not start: '.htmlspecialchars($e->getMessage()),'error'); header('Location: boost-ad.php'); exit; }
    }
    header('Location: boost-ad.php'); exit;
}
refreshFeaturedFlags($pdo);
 $preselect=(int)($_GET['listing_id']??0);
 $ads=$pdo->prepare("SELECT id,title,price,segment,is_featured,is_featured_until FROM listings WHERE user_id=? AND status='approved' ORDER BY created_at DESC");
 $ads->execute([$uid]); $myAds=$ads->fetchAll();
 $packages=$pdo->query("SELECT * FROM boost_packages WHERE is_active=1 ORDER BY price")->fetchAll();
require 'includesheader.php';
?>
<div class="card"><h2>⚡ Boost Your Ads</h2>
<p class="muted">Boosted ads appear <b>at the top</b> with a ⚡ badge — up to 5× more views. You have <b><?= $creditsLeft ?></b> free credit(s) (1 = 7 days).</p></div>
<?php if (!$myAds): ?><div class="card" style="text-align:center;"><p>No approved ads yet.</p><a href="post-ad.php" class="btn btn-accent">+ Post Ad</a></div>
<?php else: ?>
<form method="post" id="boostForm">
<div class="card"><h2>1️⃣ Choose Your Ad</h2><table>
<tr><th></th><th>Ad</th><th>Price</th><th>Boost Status</th></tr>
<?php foreach ($myAds as $a): $b=$a['is_featured']&&strtotime($a['is_featured_until'])>time(); ?>
<tr><td><input type="radio" name="listing_id" value="<?= $a['id'] ?>" required <?= $preselect===(int)$a['id']?'checked':'' ?>></td>
<td><b><?= htmlspecialchars($a['title']) ?></b></td><td>₹<?= number_format((float)$a['price']) ?></td>
<td><?php if ($b): ?><span class="tag ok">⚡ till <?= date('d M, h:i A',strtotime($a['is_featured_until'])) ?></span> <span class="muted">(stacks)</span><?php else: ?><span class="tag no">Not boosted</span><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<div class="card"><h2>2️⃣ Choose Duration</h2>
<div class="grid2" style="margin-bottom:14px;">
<?php foreach ($packages as $p): ?>
<label style="border:2px solid #e2e8f0;border-radius:12px;padding:16px;cursor:pointer;display:block;">
<input type="radio" name="package_id" value="<?= $p['id'] ?>" style="margin-right:8px;" <?= $p['days']==7?'checked':'' ?>>
<b><?= htmlspecialchars($p['name']) ?></b><br><span style="font-size:1.4rem;font-weight:800;color:#ea580c;">₹<?= number_format((float)$p['price']) ?></span><br>
<span class="muted"><?= $p['days'] ?> days top placement</span></label>
<?php endforeach; ?></div>
<button name="action" value="package" class="btn btn-accent" onclick="return confirmBoost('package')">💳 Pay & Boost Now</button>
<?php if ($creditsLeft>0): ?><button name="action" value="credit" class="btn btn-primary" onclick="return confirmBoost('credit')">🎁 Use 1 Plan Credit (7 days free)</button><?php endif; ?>
</div></form><?php endif; ?>
<script>
function confirmBoost(t){if(!document.querySelector('input[name=listing_id]:checked')){alert('Select an ad first.');return false;}
return confirm(t==='package'?'Pay for this boost?':'Use 1 featured credit on this ad?');}
</script>
<?php require 'includesfooter.php'; ?>