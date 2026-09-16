<?php
require_once 'config.php';
if (empty($_SESSION['reset_user_id'])) { header('Location: forgot-password.php'); exit; }
 $userId=(int)$_SESSION['reset_user_id'];
 $s=$pdo->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$userId]); $user=$s->fetch();
if (!$user) { unset($_SESSION['reset_user_id']); header('Location: forgot-password.php'); exit; }
 $sentAt=$user['otp_sent_at']?strtotime($user['otp_sent_at']):0;
 $waitLeft=max(0,60-(time()-$sentAt));
 $blocked=(int)$user['reset_attempts']>=5;
if ($_SERVER['REQUEST_METHOD']==='POST'&&!$blocked) {
    if ($user['reset_otp_expiry']===null||$user['reset_otp_expiry']<date('Y-m-d H:i:s')) { flash('⏰ Code expired. Request a new one.','error'); header('Location: reset-password.php'); exit; }
    $otp=trim($_POST['otp']??''); $p1=$_POST['password']??''; $p2=$_POST['password2']??'';
    if (strlen($p1)<6) flash('New password must be at least 6 characters.','error');
    elseif ($p1!==$p2) flash('Passwords do not match.','error');
    elseif ($otp!==$user['reset_otp']) {
        $att=(int)$user['reset_attempts']+1;
        $pdo->prepare("UPDATE users SET reset_attempts=? WHERE id=?")->execute([$att,$userId]);
        $left=5-$att;
        flash($left>0?"❌ Incorrect code. $left attempts remaining.":'🚫 Too many attempts. Request a new code.','error');
    } else {
        $pdo->prepare("UPDATE users SET password_hash=?,reset_otp=NULL,reset_otp_expiry=NULL,reset_attempts=0 WHERE id=?")->execute([password_hash($p1,PASSWORD_DEFAULT),$userId]);
        notifyPasswordChanged($user);
        unset($_SESSION['reset_user_id'],$_SESSION['dev_email_otp']);
        flash('🎉 Password changed! Login with your new password.');
        header('Location: login.php'); exit;
    }
    header('Location: reset-password.php'); exit;
}
require 'includesheader.php';
?>
<div class="card" style="max-width:460px;margin:0 auto;"><h2>🔑 Set New Password</h2>
<p class="muted">Code sent to <b><?= maskEmail($user['email']) ?></b>
<?php if (OTP_DEV_MODE&&isset($_SESSION['dev_email_otp'])): ?><br><b>DEV: OTP=<?= $_SESSION['dev_email_otp'] ?></b><?php endif; ?></p>
<?php if ($blocked): ?><p class="alert error">🔒 Max attempts reached. Request a new code.</p>
<?php else: ?>
<form method="post" class="f">
<label>6-Digit Code</label><input type="text" name="otp" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required>
<label>New Password</label><input type="password" name="password" minlength="6" required>
<label>Confirm New Password</label><input type="password" name="password2" minlength="6" required>
<button class="btn btn-accent">Change Password</button></form>
<?php endif; ?>
<p style="text-align:center;margin-top:14px;"><?php if ($waitLeft>0): ?><button class="btn btn-outline-dark" disabled>🔄 Resend in <?= $waitLeft ?>s</button><?php else: ?><a href="resend-reset-otp.php" class="btn btn-outline-dark">🔄 Resend Code</a><?php endif; ?></p></div>
<?php require 'includesfooter.php'; ?>