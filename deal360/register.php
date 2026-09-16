<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name=trim($_POST['name']??''); $email=trim($_POST['email']??'');
    $mobile=preg_replace('/\D/','',$_POST['mobile']??''); $pass=$_POST['password']??'';
    $role=in_array($_POST['role']??'',['buyer','seller','worker'])?$_POST['role']:'buyer';
    $errors=[];
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Invalid email';
    if (strlen($mobile)!==10) $errors[]='Mobile must be 10 digits';
    if (strlen($pass)<6) $errors[]='Password min 6 characters';
    $s=$pdo->prepare("SELECT id FROM users WHERE email=? OR mobile=?"); $s->execute([$email,$mobile]);
    if ($s->fetch()) $errors[]='Email or mobile already registered';
    if (!$errors) {
        $eOtp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        $mOtp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO users (name,email,mobile,password_hash,role,email_otp,mobile_otp,otp_expiry,otp_attempts,otp_sent_at) VALUES (?,?,?,?,?,?,?,?,0,NOW())")
            ->execute([$name,$email,$mobile,password_hash($pass,PASSWORD_DEFAULT),$role,$eOtp,$mOtp,date('Y-m-d H:i:s',time()+600)]);
        $userId=$pdo->lastInsertId();
        $okE=sendEmailOTP($email,$eOtp); $okM=sendMobileOTP($mobile,$mOtp);
        if (!$okE||!$okM) { flash('⚠️ Could not send OTP — check notifications_config.php credentials.','error'); header('Location: register.php'); exit; }
        $_SESSION['pending_user_id']=$userId;
        flash('📬 OTPs sent to your email & mobile.'.((OTP_DEV_MODE)?" <b>DEV: Email=$eOtp · Mobile=$mOtp</b>":''),'info');
        header('Location: verify.php'); exit;
    }
    flash(implode('. ',$errors),'error');
}
require 'includesheader.php';
?>
<div class="card" style="max-width:520px;margin:0 auto;"><h2>Create Account</h2>
<form method="post" class="f">
<label>Full Name</label><input type="text" name="name" required>
<label>Email</label><input type="email" name="email" required>
<label>Mobile (10 digits)</label><input type="tel" name="mobile" pattern="[0-9]{10}" required>
<label>Password</label><input type="password" name="password" minlength="6" required>
<label>I am a</label><select name="role"><option value="buyer">Buyer</option><option value="seller">Seller</option><option value="worker">Worker / Service Provider</option></select>
<button class="btn btn-accent">Send OTP & Register</button>
<p class="muted">Already registered? <a href="login.php">Login</a></p>
</form></div>
<?php require 'includesfooter.php'; ?>