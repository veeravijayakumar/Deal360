<?php
require_once 'config.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $code=$_POST['plan_code']??''; $type=in_array($_POST['type']??'',['buyer','seller'])?$_POST['type']:'buyer';
    $plan=planInfo($pdo,$code,$type);
    if ($plan) {
        if ((float)$plan['price']>0) {
            try {
                $pid=createPendingPayment($pdo,$_SESSION['user_id'],$type,'subscription',$code,(float)$plan['price'],['plan'=>$code,'days'=>30]);
                header('Location: checkout.php?id='.$pid); exit;
            } catch (Exception $e) { flash('Payment could not start: '.htmlspecialchars($e->getMessage()),'error'); }
        } else {
            $sub=activeSubscription($pdo,$_SESSION['user_id'],$type);
            $pdo->prepare("UPDATE subscriptions SET plan_code=?,views_used=0,ads_used=0,featured_used=0,valid_till=DATE_ADD(CURDATE(),INTERVAL 30 DAY) WHERE id=?")->execute([$code,$sub['id']]);
            flash("✅ {$plan['name']} ({$type}) activated!");
        }
    }
    header('Location: dashboard.php'); exit;
}
 $bp=$pdo->query("SELECT * FROM plans WHERE user_type='buyer' ORDER BY price")->fetchAll();
 $sp=$pdo->query("SELECT * FROM plans WHERE user_type='seller' ORDER BY price")->fetchAll();
require 'includesheader.php';
function planTable(array $plans,string $type) {
    echo '<table><tr><th>Plan</th><th>Price</th><th>Benefits</th><th></th></tr>';
    foreach ($plans as $p) { $b=[];
        if ($p['views_limit']) $b[]=$p['views_limit'].' views/day';
        if (!$p['views_limit']&&$type==='buyer') $b[]='Unlimited views';
        if ($p['ads_limit']) $b[]=$p['ads_limit'].' ads/month';
        if (!$p['ads_limit']&&$type==='seller') $b[]='Unlimited ads';
        if ($p['featured_limit']) $b[]=$p['featured_limit'].' featured credits';
        if ($p['can_enquire']) $b[]='✅ Enquiry + chat';
        echo '<tr><td><b>'.$p['name'].'</b></td><td>₹'.$p['price'].'</td><td>'.implode(' · ',$b).'</td>
        <td><form method="post"><input type="hidden" name="plan_code" value="'.$p['code'].'"><input type="hidden" name="type" value="'.$type.'">
        <button class="btn btn-accent">'.($p['price']>0?'Pay ₹'.$p['price']:'Activate').'</button></form></td></tr>'; }
    echo '</table>';
}
?>
<div class="card"><h2>👤 Buyer Plans</h2><?php planTable($bp,'buyer'); ?></div>
<div class="card"><h2>🏪 Seller Plans</h2><?php planTable($sp,'seller'); ?></div>
<?php require 'includesfooter.php'; ?>