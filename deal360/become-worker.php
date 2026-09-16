<?php
require_once 'config.php';
requireLogin();
 $uid=$_SESSION['user_id'];
 $s=$pdo->prepare("SELECT * FROM workers WHERE user_id=?"); $s->execute([$uid]); $worker=$s->fetch();
 $CATS=['Electrician','Plumber','Carpenter','Painter','Mason','AC Technician','House Cleaner','Welder','Gardener','Pest Control','Others'];
 $EXP=['0-1 Year','1-3 Years','3-5 Years','5-10 Years','10+ Years'];
 $AVAIL=['All Days','Weekdays Only','Weekends Only'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $category=trim($_POST['category']??'');
    $rt=in_array($_POST['rate_type']??'',['hour','day','job'])?$_POST['rate_type']:'hour';
    $rate=(float)($_POST['rate']??0); $neg=isset($_POST['negotiable'])?1:0;
    $exp=trim($_POST['experience']??''); $radius=(int)($_POST['service_radius_km']?:5);
    $avail=trim($_POST['availability']??''); $bio=trim($_POST['bio']??'');
    $city=trim($_POST['city']??''); $pin=trim($_POST['pincode']??'');
    $lat=($_POST['latitude']??'')!==''?(float)$_POST['latitude']:null;
    $lng=($_POST['longitude']??'')!==''?(float)$_POST['longitude']:null;
    $errors=[];
    if (!in_array($category,$CATS)) $errors[]='Select a valid category';
    if ($rate<=0) $errors[]='Enter your rate';
    if (!$lat&&!$city) $errors[]='Capture GPS OR enter city';
    $idPath=$worker['id_proof']??'';
    if (!empty($_FILES['id_proof']['name'])) {
        $ext=strtolower(pathinfo($_FILES['id_proof']['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png','webp','pdf'])&&$_FILES['id_proof']['size']<=3*1024*1024) {
            if (!is_dir('uploads/idproofs')) mkdir('uploads/idproofs',0775,true);
            $idPath='uploads/idproofs/'.uniqid('id_').'.'.$ext;
            move_uploaded_file($_FILES['id_proof']['tmp_name'],$idPath);
        } else $errors[]='ID proof must be JPG/PNG/PDF under 3MB';
    } elseif (!$worker) $errors[]='ID proof upload is required';
    if (!$errors) {
        if ($worker) {
            $pdo->prepare("UPDATE workers SET category=?,rate_type=?,rate=?,negotiable=?,experience=?,service_radius_km=?,availability=?,bio=?,city=?,pincode=?,latitude=?,longitude=?,id_proof=? WHERE id=?")
                ->execute([$category,$rt,$rate,$neg,$exp,$radius,$avail,$bio,$city,$pin,$lat,$lng,$idPath,$worker['id']]);
            flash('✅ Profile updated!');
        } else {
            $pdo->prepare("INSERT INTO workers (user_id,category,rate_type,rate,negotiable,experience,service_radius_km,availability,bio,city,pincode,latitude,longitude,id_proof) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$uid,$category,$rt,$rate,$neg,$exp,$radius,$avail,$bio,$city,$pin,$lat,$lng,$idPath]);
            flash('🎉 Profile submitted! Live after admin approval.');
        }
        $pdo->prepare("UPDATE users SET role='worker' WHERE id=? AND role='buyer'")->execute([$uid]);
        header('Location: become-worker.php'); exit;
    }
    flash(implode('. ',$errors),'error'); $worker=array_merge($worker?:[],$_POST);
}
require 'includesheader.php';
?>
<div class="card" style="max-width:640px;margin:0 auto;"><h2>🧑‍🔧 Worker Profile</h2>
<?php if ($worker): ?><p class="alert <?= $worker['status']==='approved'?'success':($worker['status']==='pending'?'info':'error') ?>">
<?php if ($worker['status']==='approved'): ?>🟢 LIVE — customers can find you<?php elseif ($worker['status']==='pending'): ?>🟡 Awaiting admin approval<?php else: ?>🔴 Rejected — update & contact support<?php endif; ?></p>
<?php else: ?><p class="alert info">Set <b>your own price</b> — per hour, day, or job.</p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="f">
<label>My Profession *</label><select name="category"><?php foreach($CATS as $c): ?><option <?= ($worker['category']??'')===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?></select>
<label>My Rate Type *</label><select name="rate_type"><option value="hour" <?= ($worker['rate_type']??'')==='hour'?'selected':'' ?>>Per Hour</option><option value="day" <?= ($worker['rate_type']??'')==='day'?'selected':'' ?>>Per Day</option><option value="job" <?= ($worker['rate_type']??'')==='job'?'selected':'' ?>>Per Job</option></select>
<label>Rate (₹) *</label><input type="number" name="rate" min="10" value="<?= htmlspecialchars($worker['rate']??'') ?>" required>
<label><input type="checkbox" name="negotiable" <?= !empty($worker['negotiable'])?'checked':'' ?>> Negotiable</label>
<label>Experience</label><select name="experience"><?php foreach($EXP as $e): ?><option <?= ($worker['experience']??'')===$e?'selected':'' ?>><?= $e ?></option><?php endforeach; ?></select>
<label>Service radius</label><select name="service_radius_km"><?php foreach([2,5,10,20,50] as $r): ?><option value="<?= $r ?>" <?= (int)($worker['service_radius_km']??5)===$r?'selected':'' ?>><?= $r ?> km</option><?php endforeach; ?></select>
<label>Availability</label><select name="availability"><?php foreach($AVAIL as $a): ?><option <?= ($worker['availability']??'')===$a?'selected':'' ?>><?= $a ?></option><?php endforeach; ?></select>
<label>About / Skills</label><textarea name="bio" rows="3" maxlength="500"><?= htmlspecialchars($worker['bio']??'') ?></textarea>
<label>📍 Location</label><button type="button" class="btn btn-primary" onclick="gps()">📍 Capture GPS</button><span class="muted" id="gpsStatus"></span>
<input type="hidden" name="latitude" id="lat" value="<?= htmlspecialchars($worker['latitude']??'') ?>">
<input type="hidden" name="longitude" id="lng" value="<?= htmlspecialchars($worker['longitude']??'') ?>">
<label>City (if no GPS)</label><input type="text" name="city" value="<?= htmlspecialchars($worker['city']??'') ?>">
<label>Pincode</label><input type="text" name="pincode" maxlength="6" value="<?= htmlspecialchars($worker['pincode']??'') ?>">
<label>🪪 ID Proof <?= $worker?'':'*' ?></label><input type="file" name="id_proof" accept=".jpg,.jpeg,.png,.webp,.pdf">
<button class="btn btn-accent"><?= $worker?'Update Profile':'Submit for Verification' ?></button>
</form></div>
<script>
function gps(){if(!navigator.geolocation)return alert('GPS not supported');
document.getElementById('gpsStatus').textContent='Detecting...';
navigator.geolocation.getCurrentPosition(function(p){
document.getElementById('lat').value=p.coords.latitude.toFixed(6);
document.getElementById('lng').value=p.coords.longitude.toFixed(6);
document.getElementById('gpsStatus').innerHTML='✅ Captured!';},
function(){document.getElementById('gpsStatus').textContent='❌ Denied — enter city';});}
</script>
<?php require 'includesfooter.php'; ?>