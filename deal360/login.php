<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $s=$pdo->prepare("SELECT * FROM users WHERE email=?"); $s->execute([trim($_POST['email']??'')]); $user=$s->fetch();
    if ($user&&password_verify($_POST['password']??'',$user['password_hash'])) {
        if (!$user['email_verified']||!$user['mobile_verified']) {
            $eOtp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
            $mOtp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
            $pdo->prepare("UPDATE users SET email_otp=?,mobile_otp=?,otp_attempts=0,otp_expiry=DATE_ADD(NOW(),INTERVAL 10 MINUTE),otp_sent_at=NOW() WHERE id=?")->execute([$eOtp,$mOtp,$user['id']]);
            sendEmailOTP($user['email'],$eOtp); sendMobileOTP($user['mobile'],$mOtp);
            $_SESSION['pending_user_id']=$user['id'];
            flash('Please verify first. Fresh OTPs sent!','info'); header('Location: verify.php'); exit;
        }
        session_regenerate_id(true);
        $_SESSION['user_id']=$user['id'];
        $_SESSION['user']=['id'=>$user['id'],'name'=>$user['name'],'role'=>$user['role']];
        header('Location: dashboard.php'); exit;
    }
    flash('Invalid email or password','error');
}
require 'includesheader.php';
?>
<div class="card" style="max-width:440px;margin:0 auto;"><h2>Login</h2>
<form method="post" class="f">
<label>Email</label><input type="email" name="email" required>
<label>Password</label><input type="password" name="password" required>
<button class="btn btn-primary">Login</button>
<p class="muted">New here? <a href="register.php">Create account</a> · <a href="forgot-password.php">Forgot password?</a></p>
</form></div>
<?php require 'includesfooter.php'; ?>


