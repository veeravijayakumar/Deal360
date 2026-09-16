<?php
require_once 'config.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $s=$pdo->prepare("SELECT * FROM users WHERE email=?"); $s->execute([trim($_POST['email']??'')]); $user=$s->fetch();
    if (!$user) { flash('No account found with that email.','error'); header('Location: forgot-password.php'); exit; }
    $sentAt=$user['otp_sent_at']?strtotime($user['otp_sent_at']):0;
    if (time()-$sentAt<60) { flash('Please wait '.(60-(time()-$sentAt)).'s before requesting again.','error'); header('Location: forgot-password.php'); exit; }
    $otp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
    $pdo->prepare("UPDATE users SET reset_otp=?,reset_otp_expiry=DATE_ADD(NOW(),INTERVAL 10 MINUTE),reset_attempts=0,otp_sent_at=NOW() WHERE id=?")->execute([$otp,$user['id']]);
    if (!sendEmailOTP($user['email'],$otp)) { flash('Could not send email right now. Try again.','error'); header('Location: forgot-password.php'); exit; }
    $_SESSION['reset_user_id']=(int)$user['id'];
    flash('📬 Reset code sent!'.(OTP_DEV_MODE?' DEV code on next page.':''), 'info');
    header('Location: reset-password.php'); exit;
}
require 'includesheader.php';
?>
<div class="card" style="max-width:440px;margin:0 auto;"><h2>🔑 Forgot Password</h2>
<p class="muted">Enter your registered email — we'll send a 6-digit code.</p>
<form method="post" class="f"><label>Email Address</label><input type="email" name="email" required>
<button class="btn btn-accent">Send Reset Code</button>
<p class="muted">Remembered it? <a href="login.php">Back to Login</a></p></form></div>
<?php require 'includesfooter.php'; ?>