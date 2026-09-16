<?php
require_once 'config.php';
requireLogin();
 $uid=$_SESSION['user_id'];
if (isset($_GET['paid'])) flash('🎉 Payment successful! Your upgrade is active.');
if ($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['save_prefs'])) {
    $pdo->prepare("UPDATE users SET notify_emails=? WHERE id=?")->execute([isset($_POST['optin'])?1:0,$uid]);
    flash('Notification preferences saved.'); header('Location: dashboard.php'); exit;
}
refreshFeaturedFlags($pdo);
 $bSub=activeSubscription($pdo,$uid,'buyer'); $bPlan=planInfo($pdo,$bSub['plan_code'],'buyer');
 $sSub=activeSubscription($pdo,$uid,'seller'); $sPlan=planInfo($pdo,$sSub['plan_code'],'seller');
 $viewsToday=viewsUsed($pdo,$uid,$bSub,$bPlan);
 $creditsLeft=max(0,(int)$sPlan['featured_limit']-(int)$sSub['featured_used']);
 $q=$pdo->prepare("SELECT (SELECT COUNT(*) FROM view_logs vl JOIN listings l ON l.id=vl.listing_id WHERE l.user_id=? AND vl.viewed_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)) v7,
 (SELECT COUNT(*) FROM enquiries e JOIN listings l ON l.id=e.listing_id WHERE l.user_id=?) enq,
 (SELECT COUNT(*) FROM conversations c WHERE c.seller_id=?) chats");
 $q->execute([$uid,$uid,$uid]); $quick=$q->fetch();
 $ads=$pdo->prepare("SELECT * FROM listings WHERE user_id=? ORDER BY created_at DESC"); $ads->execute([$uid]);
require 'includesheader.php';
?>
<div class="card"><h2>👋 <?= htmlspecialchars($_SESSION['user']['name']) ?></h2>
<div class="grid2">
<div><h3>👤 Buyer Plan</h3><p><b><?= $bPlan['name'] ?></b> — valid till <?= $bSub['valid_till'] ?></p><p>Views today: <b><?= $viewsToday ?></b> / <?= $bPlan['views_limit']?:'∞' ?></p><p>Enquiries: <?= $bPlan['can_enquire']?'✅ Allowed':'🔒 Upgrade to Gold' ?></p></div>
<div><h3>🏪 Seller Plan</h3><p><b><?= $sPlan['name'] ?></b> — valid till <?= $sSub['valid_till'] ?></p><p>Ads used: <b><?= $sSub['ads_used'] ?></b> / <?= $sPlan['ads_limit']?:'∞' ?></p><p>Featured credits left: <b><?= $creditsLeft ?></b></p></div>
</div>
<a href="upgrade.php" class="btn btn-accent">⬆ Upgrade Plans</a>
<a href="post-ad.php" class="btn btn-primary">+ Post Ad</a>
<a href="become-worker.php" class="btn btn-outline-dark">🧑‍🔧 Worker Profile</a></div>

<div class="card"><h2>📊 Quick Stats (last 7 days)</h2>
<div class="grid2" style="text-align:center;">
<div><h3><?= (int)$quick['v7'] ?></h3><p class="muted">Views this week</p></div>
<div><h3><?= (int)$quick['enq'] ?></h3><p class="muted">Total enquiries</p></div>
<div><h3><?= (int)$quick['chats'] ?></h3><p class="muted">Buyer chats</p></div></div>
<a href="analytics.php" class="btn btn-primary" style="margin-top:10px;">📊 Full Analytics</a>
<a href="messages.php" class="btn btn-outline-dark">📬 Messages</a></div>

<div class="card"><h2>⚡ Boost Center</h2>
<p>Featured credits left this month: <b><?= $creditsLeft ?></b> (1 credit = 7 days top placement)</p>
<a href="boost-ad.php" class="btn btn-accent">⚡ Boost an Ad</a></div>

<div class="card"><h2>🔔 Notification Settings</h2>
<form method="post">
<label><input type="checkbox" name="optin" <?= userWantsEmails(getUserById($uid))?'checked':'' ?>> Email me about new enquiries, chats & reviews</label><br><br>
<button name="save_prefs" value="1" class="btn btn-primary">Save Preferences</button></form>
<p class="muted" style="margin-top:8px;">Transactional emails (receipts, ad approval) are always sent.</p></div>

<div class="card"><h2>My Ads</h2><table>
<tr><th>ID</th><th>Title</th><th>Segment</th><th>Price</th><th>Boost</th><th>Status</th></tr>
<?php foreach($ads as $a): $boosted=$a['is_featured']&&strtotime($a['is_featured_until'])>time(); ?>
<tr><td><?= $a['id'] ?></td>
<td><a href="listing.php?id=<?= $a['id'] ?>"><?= htmlspecialchars($a['title']) ?></a></td>
<td><?= $a['segment'] ?></td><td>₹<?= number_format((float)$a['price']) ?></td>
<td><?php if ($boosted): ?><span class="tag ok">⚡ till <?= date('d M',strtotime($a['is_featured_until'])) ?></span>
<?php else: ?><a class="btn btn-outline-dark" style="padding:6px 12px;font-size:.8rem;" href="boost-ad.php?listing_id=<?= $a['id'] ?>">⚡ Boost</a><?php endif; ?></td>
<td><span class="tag <?= $a['status']==='approved'?'ok':($a['status']==='pending'?'pending':'no') ?>"><?= strtoupper($a['status']) ?></span></td></tr>
<?php endforeach; if(!$ads->rowCount()) echo '<tr><td colspan="6">No ads yet.</td></tr>'; ?>
</table></div>
<?php require 'includesfooter.php'; ?>