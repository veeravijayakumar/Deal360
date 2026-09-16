<?php
require_once 'config.php';
if (empty($_SESSION['pending_user_id'])) { header('Location: register.php'); exit; }
 $uid=$_SESSION['pending_user_id'];
 $s=$pdo->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$uid]); $user=$s->fetch();
 $sentAt=$user['otp_sent_at']?strtotime($user['otp_sent_at']):0;
if (time()-$sentAt<60) { flash('Please wait '.(60-(time()-$sentAt)).'s before requesting a new OTP.','error'); header('Location: verify.php'); exit; }
 $eOtp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
 $mOtp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
 $pdo->prepare("UPDATE users SET email_otp=?,mobile_otp=?,otp_attempts=0,otp_expiry=DATE_ADD(NOW(),INTERVAL 10 MINUTE),otp_sent_at=NOW() WHERE id=?")->execute([$eOtp,$mOtp,$uid]);
 $okE=sendEmailOTP($user['email'],$eOtp); $okM=sendMobileOTP($user['mobile'],$mOtp);
flash($okE&&$okM?'📬 Fresh OTPs sent.':'Some OTPs failed to send — check credentials.',$okE&&$okM?'info':'error');
header('Location: verify.php');