<?php
require_once 'config.php';
if (empty($_SESSION['pending_user_id'])) { header('Location: register.php'); exit; }
 $userId=$_SESSION['pending_user_id'];
 $s=$pdo->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$userId]); $user=$s->fetch();
 $sentAt=$user['otp_sent_at']?strtotime($user['otp_sent_at']):0;
 $waitLeft=max(0,60-(time()-$sentAt));
 $blocked=(int)$user['otp_attempts']>=5;
if ($_SERVER['REQUEST_METHOD']==='POST'&&!$blocked) {
    if ($user['otp_expiry']<date('Y-m-d H:i:s')) { flash('⏰ OTP expired. Tap "Resend OTP".','error'); header('Location: verify.php'); exit; }
    $e=trim($_POST['email_otp']??''); $m=trim($_POST['mobile_otp']??'');
    if ($e===$user['email_otp']&&$m===$user['mobile_otp']) {
        $pdo->prepare("UPDATE users SET email_verified=1,mobile_verified=1,email_otp=NULL,mobile_otp=NULL,otp_expiry=NULL,otp_attempts=0 WHERE id=?")->execute([$userId]);
        session_regenerate_id(true);
        $_SESSION['user_id']=$userId;
        $_SESSION['user']=['id'=>$userId,'name'=>$user['name'],'role'=>$user['role']];
        unset($_SESSION['pending_user_id'],$_SESSION['dev_email_otp'],$_SESSION['dev_mobile_otp']);
        notifyWelcome($user);
        flash('🎉 Verification complete! Welcome to deal360.shop');
        header('Location: dashboard.php'); exit;
    }
    $att=(int)$user['otp_attempts']+1;
    $pdo->prepare("UPDATE users SET otp_attempts=? WHERE id=?")->execute([$att,$userId]);
    $left=5-$att;
    flash($left>0?"❌ Incorrect OTP. $left attempt".($left==1?'':'s')." remaining.":'🚫 Too many wrong attempts. Tap "Resend OTP".','error');
    header('Location: verify.php'); exit;
}
require 'includesheader.php';
?>
<div class="card" style="max-width:480px;margin:0 auto;"><h2>📧📱 Verify Email & Mobile</h2>
<p class="muted">We sent 6-digit codes to <b><?= htmlspecialchars($user['email']) ?></b> and <b>+91 <?= htmlspecialchars($user['mobile']) ?></b>.
<?php if (OTP_DEV_MODE&&isset($_SESSION['dev_email_otp'])): ?><br><b>DEV: Email OTP=<?= $_SESSION['dev_email_otp'] ?> · Mobile OTP=<?= $_SESSION['dev_mobile_otp'] ?></b><?php endif; ?></p>
<?php if ($blocked): ?><p class="alert error">🔒 Max attempts reached. Request new OTPs.</p><a href="resend-otp.php" class="btn btn-accent full">🔄 Resend OTP</a>
<?php else: ?>
<form method="post" class="f">
<label>Email OTP</label><input type="text" name="email_otp" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required>
<label>Mobile OTP</label><input type="text" name="mobile_otp" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required>
<button class="btn btn-accent">Verify & Continue</button></form>
<p style="text-align:center;margin-top:14px;"><?php if ($waitLeft>0): ?><button class="btn btn-outline-dark" disabled>🔄 Resend in <?= $waitLeft ?>s</button><?php else: ?><a href="resend-otp.php" class="btn btn-outline-dark">🔄 Resend OTP</a><?php endif; ?></p>
<?php endif; ?></div>
<?php require 'includesfooter.php'; ?>