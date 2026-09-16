<?php
require_once 'config.php';
requireLogin();
[$canPost,$remaining]=checkAdLimit($pdo,$_SESSION['user_id']);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!$canPost) { flash('Ad limit reached — upgrade your seller plan.','error'); header('Location: upgrade.php'); exit; }
    $segment=in_array($_POST['segment']??'',['property','vehicles','electronics'])?$_POST['segment']:'property';
    $category=trim($_POST['category']??''); $title=trim($_POST['title']??''); $desc=trim($_POST['description']??'');
    $price=(float)($_POST['price']??0); $unit=trim($_POST['price_unit']??'');
    $city=trim($_POST['city']??''); $locality=trim($_POST['locality']??''); $pin=trim($_POST['pincode']??'');
    if ($title&&$category&&$price>0) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO listings (user_id,segment,category,title,description,price,price_unit,city,locality,pincode) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$_SESSION['user_id'],$segment,$category,$title,$desc,$price,$unit,$city,$locality,$pin]);
            $lid=(int)$pdo->lastInsertId();
            $d=$pdo->prepare("INSERT INTO listing_details (listing_id,field_name,field_value) VALUES (?,?,?)");
            foreach (['ptype','listfor','bhk','furnishing','facing','page','brand','year','fuel','km','owners','condition','warranty'] as $k)
                if (!empty($_POST[$k])) $d->execute([$lid,$k,$_POST[$k]]);
            if (!is_dir('uploads')) mkdir('uploads',0775,true);
            $allowed=['jpg','jpeg','png','webp']; $first=true;
            $imgs=$_FILES['images']??null;
            if ($imgs&&is_array($imgs['name'])) {
                for ($i=0;$i<min(5,count($imgs['name']));$i++) {
                    if (($imgs['error'][$i]??1)!==UPLOAD_ERR_OK) continue;
                    $ext=strtolower(pathinfo($imgs['name'][$i],PATHINFO_EXTENSION));
                    if (!in_array($ext,$allowed)||$imgs['size'][$i]>3*1024*1024) continue;
                    $fname='uploads/'.uniqid('ad_').'.'.$ext;
                    if (move_uploaded_file($imgs['tmp_name'][$i],$fname)) {
                        $pdo->prepare("INSERT INTO listing_images (listing_id,image_path,is_primary) VALUES (?,?,?)")->execute([$lid,$fname,$first?1:0]);
                        $first=false;
                    }
                }
            }
            //$pdo->prepare("UPDATE subscriptions SET ads_used=ads_used+1 WHERE id=?")->execute([$canPost?$remaining:$remaining]);
            $pdo->prepare("UPDATE subscriptions SET ads_used=ads_used+1 WHERE id=?")->execute([checkAdLimit($pdo,$_SESSION['user_id'])[2]['id']]);
            $pdo->commit();
            flash('✅ Ad submitted! Live after admin approval.');
            header('Location: dashboard.php'); exit;
        } catch (Exception $e) { $pdo->rollBack(); flash('Error saving ad. Try again.','error'); }
    } else flash('Title, category and price are required.','error');
}
require 'includesheader.php';
?>
<div class="card">
<div class="topbar"><h2 style="margin:0;">📤 Post New Ad</h2>
<span class="tag <?= $canPost?'ok':'no' ?>">Ads remaining: <?= $canPost?(is_int($remaining)?'∞ (unlimited)':$remaining):0 ?></span></div>
<form method="post" enctype="multipart/form-data" class="f" style="max-width:640px;">
<label>Segment</label><select id="segment" name="segment" onchange="showSeg()" required>
<option value="property">🏠 Property</option><option value="vehicles">🚗 Vehicles</option><option value="electronics">📱 Electronics</option></select>
<label>Category</label><select name="category" id="category" required></select>
<label>Ad Title</label><input type="text" name="title" placeholder="e.g. 3BHK House in Kukatpally" required>
<label>Description</label><textarea name="description" rows="4"></textarea>
<label>Price (₹)</label><input type="number" name="price" min="1" step="0.01" required>
<label>Price Unit</label><select name="price_unit"><option value="">Full price</option><option>per month</option><option>per year</option><option>negotiable</option></select>
<label>City</label><input type="text" name="city" required>
<label>Locality</label><input type="text" name="locality">
<label>Pincode</label><input type="text" name="pincode" maxlength="6">
<fieldset id="seg-property"><legend>🏠 Property Details</legend>
<label>Property Type</label><select name="ptype"><option>House</option><option>Apartment</option><option>Villa</option><option>Land / Plot</option><option>Commercial</option></select>
<label>Listing For</label><select name="listfor"><option value="Sale">Sale</option><option value="Rent">Rent</option><option value="Lease">Lease</option></select>
<label>Bedrooms</label><select name="bhk"><option>1 BHK</option><option>2 BHK</option><option>3 BHK</option><option>4 BHK</option><option>4+ BHK</option><option>N/A (Land)</option></select>
<label>Furnishing</label><select name="furnishing"><option>Unfurnished</option><option>Semi-Furnished</option><option>Fully Furnished</option></select>
<label>Facing</label><select name="facing"><option>East</option><option>West</option><option>North</option><option>South</option><option>North-East</option><option>South-East</option></select>
<label>Property Age</label><select name="page"><option>New</option><option>0-1 Year</option><option>1-5 Years</option><option>5-10 Years</option><option>10+ Years</option></select></fieldset>
<fieldset id="seg-vehicles" style="display:none;"><legend>🚗 Vehicle Details</legend>
<label>Brand</label><select name="brand"><option>Maruti Suzuki</option><option>Hyundai</option><option>Honda</option><option>Toyota</option><option>Tata</option><option>Mahindra</option><option>Bajaj</option><option>TVS</option><option>Royal Enfield</option><option>Hero</option><option>Other</option></select>
<label>Year</label><select name="year"><?php for($y=date('Y');$y>=2005;$y--) echo "<option>$y</option>"; ?></select>
<label>Fuel</label><select name="fuel"><option>Petrol</option><option>Diesel</option><option>CNG</option><option>Electric</option><option>Hybrid</option></select>
<label>KM Driven</label><select name="km"><option>0-10,000</option><option>10,000-30,000</option><option>30,000-50,000</option><option>50,000-80,000</option><option>80,000+</option></select>
<label>Owners</label><select name="owners"><option>1st Owner</option><option>2nd Owner</option><option>3rd+ Owner</option></select></fieldset>
<fieldset id="seg-electronics" style="display:none;"><legend>📱 Electronics Details</legend>
<label>Brand</label><select name="brand"><option>Apple</option><option>Samsung</option><option>OnePlus</option><option>Xiaomi</option><option>Realme</option><option>Sony</option><option>LG</option><option>Dell</option><option>HP</option><option>Other</option></select>
<label>Condition</label><select name="condition"><option>Like New</option><option>Good</option><option>Fair</option><option>Needs Repair</option></select>
<label>Warranty Remaining</label><select name="warranty"><option>None</option><option>Less than 6 months</option><option>6-12 months</option><option>More than 1 year</option></select></fieldset>
<label>📷 Images (max 5, JPG/PNG/WEBP, 3MB each)</label>
<input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
<button class="btn btn-accent" style="margin-top:8px;">Submit Ad for Approval</button>
</form></div>
<script>
const CATS={property:['House for Sale','Land / Plot','Rental House','Lease House'],vehicles:['Car','Bike','Spare Parts'],electronics:['Mobile','TV','Laptop','Home Appliance','Audio','Accessories']};
function showSeg(){const s=document.getElementById('segment').value;
document.getElementById('seg-property').style.display=s==='property'?'block':'none';
document.getElementById('seg-vehicles').style.display=s==='vehicles'?'block':'none';
document.getElementById('seg-electronics').style.display=s==='electronics'?'block':'none';
document.getElementById('category').innerHTML=CATS[s].map(x=>`<option>${x}</option>`).join('');}
showSeg();
</script>
<?php require 'includesfooter.php'; ?>