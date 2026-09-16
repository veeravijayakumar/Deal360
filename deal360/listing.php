<?php
require_once 'config.php';
requireLogin();
 $id=(int)($_GET['id']??0);
 $s=$pdo->prepare("SELECT l.*,u.name sname,u.mobile smobile FROM listings l JOIN users u ON u.id=l.user_id WHERE l.id=?");
 $s->execute([$id]); $ad=$s->fetch();
if (!$ad||$ad['status']!=='approved') { flash('Ad not found or not approved.','error'); header('Location: listings.php'); exit; }
 $isOwner=($ad['user_id']===$_SESSION['user_id']);
if (!$isOwner) {
    [$allowed,$msg]=checkViewAccess($pdo,$_SESSION['user_id']);
    if (!$allowed) {
        require 'includesheader.php';
        echo '<div class="card" style="text-align:center;max-width:520px;margin:40px auto;"><h2>🔒 View Limit Reached</h2><p class="alert error">'.htmlspecialchars($msg).'</p><a href="upgrade.php" class="btn btn-accent">⬆ Upgrade Plan</a> <a href="listings.php" class="btn btn-outline-dark">Back to Browse</a></div>';
        require 'includesfooter.php'; exit;
    }
    recordView($pdo,$_SESSION['user_id'],$id);
}
 $sub=activeSubscription($pdo,$_SESSION['user_id'],'buyer');
 $plan=planInfo($pdo,$sub['plan_code'],'buyer');
 $canEnquire=(bool)$plan['can_enquire'];
if ($_SERVER['REQUEST_METHOD']==='POST'&&$canEnquire&&!$isOwner) {
    $pdo->prepare("INSERT INTO enquiries (listing_id,buyer_id,message) VALUES (?,?,?)")->execute([$id,$_SESSION['user_id'],trim($_POST['message']??'')]);
    notifyNewEnquiry(getUserById((int)$ad['user_id']),$_SESSION['user']['name'],$ad,trim($_POST['message']??''));
    flash('✅ Enquiry sent to seller!'); header('Location: listing.php?id='.$id); exit;
}
 $i=$pdo->prepare("SELECT * FROM listing_images WHERE listing_id=?"); $i->execute([$id]); $imgs=$i->fetchAll();
 $d=$pdo->prepare("SELECT * FROM listing_details WHERE listing_id=?"); $d->execute([$id]); $det=$d->fetchAll();

 $isApproved=($ad['status']==='approved');
 $pageTitle=htmlspecialchars($ad['title']).' — ₹'.number_format((float)$ad['price']).' in '.htmlspecialchars($ad['city']).' | Deal360.shop';
 $pageDesc=mb_substr(trim(strip_tags($ad['description']))?:$ad['category'].' available in '.$ad['city'],0,155);
 $canonical=SITE_URL.'/ad/'.$ad['id'].'/'.slugify($ad['title']);
 $ogImage=!empty($imgs)?SITE_URL.'/'.$imgs[0]['image_path']:null;
 $noindex=!$isApproved;
require 'includesheader.php';
?>
<?php if ($isApproved): ?>
<script type="application/ld+json"><?= json_encode(['@context'=>'https://schema.org','@type'=>'Product','name'=>$ad['title'],'description'=>mb_substr(strip_tags($ad['description']??''),0,300)?:$ad['category'],'image'=>array_map(fn($x)=>SITE_URL.'/'.$x['image_path'],array_slice($imgs,0,3)),'category'=>$ad['segment'].' > '.$ad['category'],'offers'=>['@type'=>'Offer','price'=>(string)(float)$ad['price'],'priceCurrency'=>'INR','availability'=>'https://schema.org/InStock','url'=>$canonical]],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<div class="card">
<span class="tag ok"><?= strtoupper($ad['segment']) ?></span>
<h2 style="margin-top:10px;"><?= htmlspecialchars($ad['title']) ?></h2>
<p class="muted">📍 <?= htmlspecialchars($ad['locality'].', '.$ad['city'].' - '.$ad['pincode']) ?> · Posted <?= $ad['created_at'] ?></p>
<div class="imggrid" style="margin:18px 0;">
<?php if($imgs): foreach($imgs as $im): ?><img src="<?= $im['image_path'] ?>" alt=""><?php endforeach; else: echo '📦'; endif; ?></div>
<p class="price">₹ <?= number_format((float)$ad['price']) ?> <?= htmlspecialchars($ad['price_unit']) ?></p>
<p><?= nl2br(htmlspecialchars($ad['description'])) ?></p>
<?php if($det): ?><h3 style="margin:18px 0 8px;">Details</h3><table><tr><th>Feature</th><th>Value</th></tr>
<?php foreach($det as $dd) echo '<tr><td>'.ucwords(str_replace('_',' ',$dd['field_name'])).'</td><td>'.htmlspecialchars($dd['field_value']).'</td></tr>'; ?>
</table><?php endif; ?></div>
<div class="card"><h2>📞 Contact Seller</h2>
<?php if ($isOwner): ?><p>This is your own ad.</p>
<?php elseif ($canEnquire): ?>
<p class="alert success">✅ Seller Contact: <b>+91 <?= htmlspecialchars($ad['smobile']) ?></b></p>
<a href="start-chat.php?listing_id=<?= $id ?>" class="btn btn-primary" style="margin-bottom:10px;">💬 Chat with Seller</a>
<form method="post" class="f"><label>Send Enquiry</label><textarea name="message" rows="3" placeholder="Hi, I'm interested in this..." required></textarea>
<button class="btn btn-accent">Send Enquiry</button></form>
<?php else: ?>
<p class="alert info">🔒 Contact available on <b>Gold</b> plan+. Visible: <b><?= maskPhone($ad['smobile']) ?></b></p>
<a href="upgrade.php" class="btn btn-accent">⬆ Upgrade to Gold (₹299) to Enquire</a>
<?php endif; ?></div>
<?php require 'includesfooter.php'; ?>