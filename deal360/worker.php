<?php
require_once 'config.php';
requireLogin();
 $id=(int)($_GET['id']??0); $uid=$_SESSION['user_id'];
 $s=$pdo->prepare("SELECT w.*,u.name,u.mobile FROM workers w JOIN users u ON u.id=w.user_id WHERE w.id=?");
 $s->execute([$id]); $w=$s->fetch();
if (!$w||($w['status']!=='approved'&&$w['user_id']!=$uid)) { flash('Worker not found.','error'); header('Location: find-workers.php'); exit; }
 $isOwner=($w['user_id']==$uid);
 $dist=null;
if (!$isOwner&&isset($_GET['lat'],$_GET['lng'])&&$w['latitude'])
    $dist=haversineKm((float)$_GET['lat'],(float)$_GET['lng'],(float)$w['latitude'],(float)$w['longitude']);
 $sub=activeSubscription($pdo,$uid,'buyer'); $plan=planInfo($pdo,$sub['plan_code'],'buyer');
 $canEnquire=(bool)$plan['can_enquire'];
if ($_SERVER['REQUEST_METHOD']==='POST'&&!$isOwner&&in_array($_POST['rating']??'',['1','2','3','4','5'])) {
    $pdo->prepare("INSERT INTO worker_reviews (worker_id,user_id,rating,comment) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating),comment=VALUES(comment)")
        ->execute([$id,$uid,(int)$_POST['rating'],trim($_POST['comment']??'')]);
    $pdo->prepare("UPDATE workers w SET rating_avg=(SELECT AVG(rating) FROM worker_reviews r WHERE r.worker_id=w.id),rating_count=(SELECT COUNT(*) FROM worker_reviews r WHERE r.worker_id=w.id) WHERE w.id=?")->execute([$id]);
    notifyNewReview(getUserById((int)$w['user_id']),$_SESSION['user']['name'],(int)$_POST['rating'],trim($_POST['comment']??''));
    flash('⭐ Thanks for your review!'); header('Location: worker.php?id='.$id); exit;
}
 $rv=$pdo->prepare("SELECT r.*,u.name rname FROM worker_reviews r JOIN users u ON u.id=r.user_id WHERE r.worker_id=? ORDER BY r.created_at DESC LIMIT 20");
 $rv->execute([$id]);
 $icons=['Electrician'=>'⚡','Plumber'=>'🚿','Carpenter'=>'🪚','Painter'=>'🎨','Mason'=>'🧱','AC Technician'=>'❄️','House Cleaner'=>'🧹','Welder'=>'🔥','Gardener'=>'🌱','Pest Control'=>'🐜','Others'=>'🛠️'];
require 'includesheader.php';
?>
<div class="card"><h2><?= $icons[$w['category']]??'🔧' ?> <?= htmlspecialchars($w['name']) ?> — <?= htmlspecialchars($w['category']) ?></h2>
<p><?= $w['status']==='approved'?'<span class="tag ok">✔ ID Verified</span>':'<span class="tag pending">Pending</span>' ?>
<?= $w['rating_count']?' ⭐ <b>'.number_format((float)$w['rating_avg'],1).'</b> ('.$w['rating_count'].')':'' ?></p>
<table style="margin:14px 0;">
<tr><th>Rate</th><td><b style="color:#ea580c;"><?= rateLabel($w['rate_type'],$w['rate']) ?></b> <?= $w['negotiable']?'(negotiable)':'' ?></td></tr>
<tr><th>Experience</th><td><?= htmlspecialchars($w['experience']) ?></td></tr>
<tr><th>Availability</th><td><?= htmlspecialchars($w['availability']) ?> · up to <?= (int)$w['service_radius_km'] ?> km</td></tr>
<?php if($dist!==null): ?><tr><th>Distance</th><td><b><?= number_format($dist,1) ?> km from you</b></td></tr><?php endif; ?>
<?php if($w['bio']): ?><tr><th>About</th><td><?= htmlspecialchars($w['bio']) ?></td></tr><?php endif; ?></table>
<?php if ($isOwner): ?><a href="become-worker.php" class="btn btn-primary">✏️ Edit My Profile</a>
<?php elseif ($canEnquire): ?>
<p class="alert success">📞 Contact: <b>+91 <?= maskPhone($w['mobile']) ?></b> (full number in chat)</p>
<a class="btn btn-accent" href="start-worker-chat.php?worker_id=<?= $w['id'] ?>">💬 Chat</a>
<?php else: ?>
<p class="alert info">🔒 Contact on <b>Gold</b> plan+. Visible: <b><?= maskPhone($w['mobile']) ?></b></p>
<a href="upgrade.php" class="btn btn-accent">⬆ Upgrade to Chat</a><?php endif; ?></div>
<div class="card"><h2>⭐ Reviews (<?= $w['rating_count'] ?>)</h2>
<?php if (!$isOwner): ?>
<form method="post" class="f" style="max-width:420px;margin-bottom:20px;">
<label>Your Rating</label><select name="rating" required><option value="">Select</option>
<?php for($i=5;$i>=1;$i--): ?><option value="<?= $i ?>"><?= str_repeat('⭐',$i) ?> (<?= $i ?>)</option><?php endfor; ?></select>
<label>Comment (optional)</label><textarea name="comment" rows="2" maxlength="500"></textarea>
<button class="btn btn-primary">Submit Review</button></form><?php endif; ?>
<table><?php foreach($rv as $r): ?>
<tr><td><b><?= htmlspecialchars($r['rname']) ?></b> <?= str_repeat('⭐',(int)$r['rating']) ?> <span class="muted"><?= date('d M Y',strtotime($r['created_at'])) ?></span><br><span class="muted"><?= htmlspecialchars($r['comment']) ?></span></td></tr>
<?php endforeach; if(!$rv->rowCount()) echo '<tr><td>No reviews yet.</td></tr>'; ?></table></div>
<?php require 'includesfooter.php'; ?>