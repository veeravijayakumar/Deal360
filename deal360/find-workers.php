<?php
require_once 'config.php';
requireLogin();
 $cat=trim($_GET['category']??''); $city=trim($_GET['city']??'');
 $lat=($_GET['lat']??'')!==''?(float)$_GET['lat']:null; $lng=($_GET['lng']??'')!==''?(float)$_GET['lng']:null;
 $radius=(int)($_GET['radius']?:10);
 $CATS=['Electrician','Plumber','Carpenter','Painter','Mason','AC Technician','House Cleaner','Welder','Gardener','Pest Control','Others'];
 $where="w.status='approved'"; $p=[]; $mode='all'; $distSql="NULL AS dist";
if ($lat!==null&&$lng!==null) { $mode='gps';
 $distSql="(6371*ACOS(LEAST(1,COS(RADIANS(:lat1))*COS(RADIANS(w.latitude))*COS(RADIANS(w.longitude)-RADIANS(:lng1))+SIN(RADIANS(:lat2))*SIN(RADIANS(w.latitude))))) AS dist"; }
if ($cat&&in_array($cat,$CATS)) { $where.=" AND w.category=?"; $p[]=$cat; }
 $sql="SELECT w.id,w.category,w.rate_type,w.rate,w.negotiable,w.experience,w.service_radius_km,w.availability,w.bio,w.city,w.rating_avg,w.rating_count,u.name,u.mobile_verified,$distSql FROM workers w JOIN users u ON u.id=w.user_id WHERE $where";
if ($mode==='gps') {
    $sql.=" AND w.latitude IS NOT NULL HAVING dist<=:radius ORDER BY dist ASC";
    $st=$pdo->prepare($sql);
    $st->bindValue(':lat1',$lat); $st->bindValue(':lat2',$lat); $st->bindValue(':lng1',$lng); $st->bindValue(':lng2',$lng);
    foreach ($p as $i=>$v) $st->bindValue($i+1,$v);
    $st->bindValue(':radius',$radius,PDO::PARAM_INT); $st->execute();
} else {
    if ($city) { $mode='city'; $sql.=" AND (w.city LIKE ? OR w.pincode=?)"; $p[]="%$city"; $p[]=$city; }
    $sql.=" ORDER BY w.rating_avg DESC, w.id DESC";
    $st=$pdo->prepare($sql); $st->execute($p);
}
 $rows=$st->fetchAll();
require 'includesheader.php';
?>
<div class="card"><h2>🔧 Find Workers Near You</h2>
<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
<select name="category"><option value="">All Professions</option>
<?php foreach($CATS as $c): ?><option value="<?= $c ?>" <?= $cat===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?></select>
<select name="radius"><?php foreach([2,5,10,20,50] as $r): ?><option value="<?= $r ?>" <?= $radius===$r?'selected':'' ?>>Within <?= $r ?> km</option><?php endforeach; ?></select>
<input name="city" value="<?= htmlspecialchars($city) ?>" placeholder="Or city / pincode" style="padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
<button class="btn btn-primary">Search</button>
<button type="button" class="btn btn-accent" onclick="useLoc()">📍 Use My Location</button></form>
<p class="muted" style="margin-top:8px;"><?= $mode==='gps'?'🟢 Sorted by distance from you':($mode==='city'?'📍 Results for "'.htmlspecialchars($city).'" — use GPS for distance sorting':'💡 Tap <b>Use My Location</b> for distance sorting') ?></p></div>
<div class="adgrid">
<?php foreach ($rows as $w): $icons=['Electrician'=>'⚡','Plumber'=>'🚿','Carpenter'=>'🪚','Painter'=>'🎨','Mason'=>'🧱','AC Technician'=>'❄️','House Cleaner'=>'🧹','Welder'=>'🔥','Gardener'=>'🌱','Pest Control'=>'🐜','Others'=>'🛠️']; ?>
<div class="aditem">
<div class="thumb" style="background:linear-gradient(135deg,#d1fae5,#6ee7b7);"><?= $icons[$w['category']]??'🔧' ?></div>
<div class="body"><strong><?= htmlspecialchars($w['name']) ?></strong>
<span class="tag <?= $w['mobile_verified']?'ok':'pending' ?>"><?= $w['mobile_verified']?'✔ Verified':'⏳' ?></span>
<div style="font-weight:700;color:#059669;"><?= htmlspecialchars($w['category']) ?> <?= $w['rating_count']?'· ⭐ '.number_format((float)$w['rating_avg'],1).' ('.$w['rating_count'].')':'' ?></div>
<div class="price"><?= rateLabel($w['rate_type'],$w['rate']) ?> <?= $w['negotiable']?'<span class="tag pending">Neg.</span>':'' ?></div>
<div class="muted"><?= $w['dist']!==null?'📍 '.number_format((float)$w['dist'],1).' km away · ':'' ?><?= htmlspecialchars($w['experience']) ?> · <?= (int)$w['service_radius_km'] ?> km radius<br><?= $w['availability'] ?></div>
<a class="btn btn-primary" style="margin-top:10px;display:block;text-align:center;" href="worker.php?id=<?= $w['id'] ?>">View & Contact</a>
</div></div>
<?php endforeach; if(!$rows) echo '<p class="muted">No workers found. Try a bigger radius or city.</p>'; ?></div>
<script>
function useLoc(){if(!navigator.geolocation)return alert('GPS not supported');
navigator.geolocation.getCurrentPosition(function(p){
location.href='find-workers.php?lat='+p.coords.latitude.toFixed(6)+'&lng='+p.coords.longitude.toFixed(6)+'&radius='+document.querySelector('[name=radius]').value+'&category='+encodeURIComponent(document.querySelector('[name=category]').value);},
function(){alert('Permission denied — search by city.');});}
</script>
<?php require 'includesfooter.php'; ?>