<?php
require_once 'config.php';
requireLogin();
refreshFeaturedFlags($pdo);
 $segment=$_GET['segment']??''; $q=trim($_GET['q']??'');
 $page=max(1,(int)($_GET['page']??1)); $per=12; $off=($page-1)*$per;
 $sql="FROM listings l WHERE l.status='approved'"; $p=[];
if (in_array($segment,['property','vehicles','electronics'])) { $sql.=" AND l.segment=?"; $p[]=$segment; }
if ($q) { $sql.=" AND (l.title LIKE ? OR l.city LIKE ? OR l.category LIKE ?)"; $p[]="%$q";$p[]="%$q";$p[]="%$q"; }
 $c=$pdo->prepare("SELECT COUNT(*) c ".$sql); $c->execute($p); $total=(int)$c->fetch()['c'];
 $rows=$pdo->prepare("SELECT l.*,(SELECT image_path FROM listing_images i WHERE i.listing_id=l.id AND is_primary=1 LIMIT 1) img ".$sql." ORDER BY (l.is_featured_until IS NOT NULL AND l.is_featured_until>NOW()) DESC, l.created_at DESC LIMIT $per OFFSET $off");
 $rows->execute($p);
require 'includesheader.php';
?>
<div class="card"><form method="get" style="display:flex;gap:10px;flex-wrap:wrap;">
<select name="segment" style="padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
<option value="">All Segments</option>
<option value="property" <?= $segment==='property'?'selected':'' ?>>🏠 Property</option>
<option value="vehicles" <?= $segment==='vehicles'?'selected':'' ?>>🚗 Vehicles</option>
<option value="electronics" <?= $segment==='electronics'?'selected':'' ?>>📱 Electronics</option></select>
<input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search..." style="flex:1;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
<button class="btn btn-primary">Search</button><a href="post-ad.php" class="btn btn-accent">+ Post Ad</a></form></div>
<div class="adgrid">
<?php foreach ($rows as $a): ?>
<a class="aditem" href="ad/<?= $a['id'] ?>/<?= slugify($a['title']) ?>">
<div class="thumb"><?php if($a['img']): ?><img src="<?= $a['img'] ?>"><?php else: echo ['property'=>'🏠','vehicles'=>'🚗','electronics'=>'📱'][$a['segment']]; endif; ?></div>
<div class="body"><strong><?= htmlspecialchars($a['title']) ?></strong>
<div class="price">₹<?= number_format((float)$a['price']) ?> <?= htmlspecialchars($a['price_unit']) ?></div>
<div class="muted">📍 <?= htmlspecialchars($a['city']) ?> · <?= $a['created_at'] ?>
<?= ($a['is_featured']&&strtotime($a['is_featured_until'])>time())?'· <span class="tag pending">⚡ FEATURED</span>':'' ?></div>
</div></a>
<?php endforeach; if(!$total) echo '<p class="muted">No ads found.</p>'; ?></div>
<?php if ($total>$per): ?><div class="card" style="text-align:center;">
<?php for($i=1;$i<=ceil($total/$per);$i++): ?>
<a class="btn <?= $i==$page?'btn-accent':'btn-outline-dark' ?>" href="?segment=<?= urlencode($segment) ?>&q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a>
<?php endfor; ?></div><?php endif; ?>
<?php require 'includes/footer.php'; ?>