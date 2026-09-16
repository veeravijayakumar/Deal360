<?php
require_once __DIR__.'/notifications_config.php';
require_once __DIR__.'/phpmailer/src/PHPMailer.php';
require_once __DIR__.'/phpmailer/src/SMTP.php';
require_once __DIR__.'/phpmailer/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;

/* ---------- Raw OTP senders ---------- */
function sendEmailOTP(string $email, string $otp): bool {
    if (OTP_DEV_MODE) { $_SESSION['dev_email_otp']=$otp; return true; }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host=SMTP_HOST; $mail->SMTPAuth=true;
        $mail->Username=SMTP_USER; $mail->Password=SMTP_PASS;
        $mail->SMTPSecure=SMTP_SECURE; $mail->Port=SMTP_PORT; $mail->CharSet='UTF-8';
        $mail->setFrom(MAIL_FROM,MAIL_FROM_NAME); $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject="Your Deal360.shop verification code: $otp";
        $mail->Body='<div style="font-family:Segoe UI,Arial;max-width:520px;margin:auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;"><div style="background:#1d4ed8;color:#fff;padding:22px;text-align:center;"><h2 style="margin:0;">deal<span style="color:#f97316;">360</span>.shop</h2></div><div style="padding:28px;text-align:center;"><p style="font-size:15px;color:#334155;">Use this code to verify your email:</p><div style="font-size:34px;letter-spacing:8px;font-weight:800;color:#1d4ed8;background:#eff6ff;border-radius:10px;padding:14px;margin:18px 0;">'.$otp.'</div><p style="font-size:13px;color:#64748b;">Valid 10 minutes. Never share this code.</p></div></div>';
        $mail->AltBody="Your Deal360.shop OTP is $otp. Valid 10 minutes.";
        return $mail->send();
    } catch (Exception $e) { error_log('[Mail] '.($mail->ErrorInfo??$e->getMessage())); return false; }
}
function sendMobileOTP(string $mobile, string $otp): bool {
    if (OTP_DEV_MODE) { $_SESSION['dev_mobile_otp']=$otp; return true; }
    $payload = json_encode(['template_id'=>MSG91_OTP_TEMPLATE_ID,'short_url'=>'0',
        'recipients'=>[['mobiles'=>'91'.$mobile,'OTP'=>$otp]]]);
    $ch = curl_init('https://control.msg91.com/api/v5/flow/');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_HTTPHEADER=>['authkey: '.MSG91_AUTH_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>10]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($res===false) { error_log('[SMS] '.$err); return false; }
    $d = json_decode($res,true);
    if (($d['type']??'')==='success') return true;
    error_log('[SMS] '.$res); return false;
}

/* ---------- App email core ---------- */
function sendAppEmail(array $to, string $subject, string $html, string $type, ?int $userId=null): bool {
    global $pdo; $status='sent'; $err=null;
    if (NOTIFY_DEV_MODE) { $status='dev_only'; }
    else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP(); $mail->Host=SMTP_HOST; $mail->SMTPAuth=true;
            $mail->Username=SMTP_USER; $mail->Password=SMTP_PASS;
            $mail->SMTPSecure=SMTP_SECURE; $mail->Port=SMTP_PORT; $mail->CharSet='UTF-8';
            $mail->setFrom(MAIL_FROM,MAIL_FROM_NAME); $mail->addAddress($to['email'],$to['name']??'');
            $mail->isHTML(true); $mail->Subject=$subject; $mail->Body=$html;
            $mail->AltBody=trim(strip_tags(preg_replace('/<br\s*\/?>/i',"\n",$html)));
            $mail->send();
        } catch (Exception $e) { $status='failed'; $err=substr(($mail->ErrorInfo??'')?:$e->getMessage(),0,250); }
    }
    try { $pdo->prepare("INSERT INTO email_log (user_id,email,subject,type,status,error_text) VALUES (?,?,?,?,?,?)")
        ->execute([$userId,$to['email']??'',$subject,$type,$status,$err]); } catch (Exception $e) {}
    return $status!=='failed';
}
function userWantsEmails(array $u): bool { return (int)($u['notify_emails']??1)===1; }
function chatEmailThrottled(int $userId): bool {
    global $pdo;
    $s=$pdo->prepare("SELECT COUNT(*) c FROM email_log WHERE user_id=? AND type='chat' AND created_at>DATE_SUB(NOW(),INTERVAL ".(int)CHAT_EMAIL_THROTTLE_MIN." MINUTE)");
    $s->execute([$userId]); return (int)$s->fetch()['c']>0;
}
function emailTemplate(string $heading, string $body, ?string $cta=null, ?string $url=null): string {
    $btn = $cta&&$url ? '<a href="'.$url.'" style="display:inline-block;background:#f97316;color:#fff;padding:13px 30px;border-radius:8px;font-weight:700;font-size:15px;text-decoration:none;margin-top:6px;">'.$cta.'</a>' : '';
    return '<div style="font-family:Segoe UI,Arial;max-width:540px;margin:auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;"><div style="background:#1d4ed8;color:#fff;padding:20px;text-align:center;"><h2 style="margin:0;font-size:22px;">deal<span style="color:#f97316;">360</span>.shop</h2></div><div style="padding:26px;"><h3 style="margin:0 0 12px;color:#0f172a;">'.$heading.'</h3><div style="font-size:14.5px;color:#334155;line-height:1.65;">'.$body.'</div><div style="text-align:center;margin-top:22px;">'.$btn.'</div></div><div style="background:#f1f5f9;padding:12px;text-align:center;font-size:12px;color:#94a3b8;">You\'re receiving this because you have a deal360.shop account.<br>Manage alerts in your <a href="'.SITE_URL.'/dashboard.php" style="color:#1d4ed8;">Dashboard</a> · Never share OTPs or passwords.</div></div>';
}

/* ---------- 1 Welcome ---------- */
function notifyWelcome(array $u): bool {
    if (!SEND_WELCOME) return false;
    $h = emailTemplate('Welcome, '.htmlspecialchars($u['name']).'! 🎉','Your email & mobile are <b style="color:#059669;">✔ verified</b> — your account is active.<br><br>🏠 <b>Buyers:</b> browse verified property, vehicles & electronics<br>🏪 <b>Sellers:</b> post your first ad — Free includes 2 ads/month<br>🔧 <b>Workers:</b> create a service profile & get enquiries','Go to My Dashboard',SITE_URL.'/dashboard.php');
    return sendAppEmail($u,'Welcome to Deal360.shop — your account is live! 🎉',$h,'welcome',(int)$u['id']);
}
/* ---------- 2 Ad status ---------- */
function notifyAdStatus(array $seller, array $ad, string $status): bool {
    if (!SEND_AD_STATUS) return false;
    if ($status==='approved') { $h='✅ Your ad is LIVE!';
        $b='Your ad <b>"'.htmlspecialchars($ad['title']).'"</b> (₹'.number_format((float)$ad['price']).') is now live on deal360.shop.';
        $c=['View Your Live Ad',SITE_URL.'/listing.php?id='.$ad['id']]; }
    else { $h='❌ Your ad was not approved';
        $b='Your ad <b>"'.htmlspecialchars($ad['title']).'"</b> did not pass review. Common reasons: unclear images, incomplete details, prohibited items. Update and repost.';
        $c=['Post a New Ad',SITE_URL.'/post-ad.php']; }
    return sendAppEmail($seller,($status==='approved'?'✅ ':'❌ ').'Your ad: '.$ad['title'],emailTemplate($h,$b,$c[0],$c[1]),'ad_status',(int)$seller['id']);
}
/* ---------- 3 Enquiry ---------- */
function notifyNewEnquiry(?array $seller, string $buyer, array $ad, string $msg): bool {
    if (!SEND_ENQUIRY||!$seller||!userWantsEmails($seller)) return false;
    $h = emailTemplate('🔔 New enquiry on your ad!','<b>'.htmlspecialchars($buyer).'</b> is interested in <b>"'.htmlspecialchars($ad['title']).'"</b>:<br><br><div style="background:#f1f5f9;border-left:4px solid #1d4ed8;padding:12px 14px;border-radius:6px;">'.nl2br(htmlspecialchars(mb_strimwidth($msg,0,300,'...'))).'</div><br>Reply quickly — fast responses convert 3× better!','Open Messages & Reply',SITE_URL.'/messages.php');
    return sendAppEmail($seller,'🔔 '.$buyer.' sent an enquiry on "'.$ad['title'].'"',$h,'enquiry',(int)$seller['id']);
}
/* ---------- 4 Chat (throttled) ---------- */
function notifyNewChatMessage(?array $rcpt, string $sender, string $about, string $msg, int $convId): bool {
    if (!SEND_CHAT||!$rcpt||!userWantsEmails($rcpt)) return false;
    if (chatEmailThrottled((int)$rcpt['id'])) return false;
    $h = emailTemplate('💬 New message from '.htmlspecialchars($sender),'About: <b>'.htmlspecialchars($about).'</b><br><br><div style="background:#f1f5f9;border-left:4px solid #f97316;padding:12px 14px;border-radius:6px;">'.nl2br(htmlspecialchars(mb_strimwidth($msg,0,200,'...'))).'</div><br>Log in to reply!','Open Chat',SITE_URL.'/chat.php?id='.$convId);
    return sendAppEmail($rcpt,'💬 New message from '.$sender.' — deal360.shop',$h,'chat',(int)$rcpt['id']);
}
/* ---------- 5 Plan receipt ---------- */
function notifyPlanActivated(array $u, string $plan, string $type, float $price, string $till): bool {
    if (!SEND_PLAN) return false;
    $h = emailTemplate('🧾 '.htmlspecialchars($plan).' plan activated!','Your <b>'.htmlspecialchars($type).'</b> subscription is active.<br><br><table style="width:100%;font-size:14px;background:#f8fafc;border-radius:8px;"><tr><td style="padding:8px 12px;">Plan</td><td style="padding:8px 12px;text-align:right;"><b>'.htmlspecialchars($plan).'</b></td></tr><tr><td style="padding:8px 12px;">Amount</td><td style="padding:8px 12px;text-align:right;"><b>₹'.number_format($price).'</b></td></tr><tr><td style="padding:8px 12px;">Valid till</td><td style="padding:8px 12px;text-align:right;"><b>'.$till.'</b></td></tr></table><br>Thank you for upgrading!','Go to Dashboard',SITE_URL.'/dashboard.php');
    return sendAppEmail($u,'🧾 Receipt: '.$plan.' plan (₹'.number_format($price).') — deal360.shop',$h,'plan',(int)$u['id']);
}
/* ---------- 6 Worker status ---------- */
function notifyWorkerStatus(array $u, string $status, int $workerId): bool {
    if (!SEND_WORKER_STATUS) return false;
    if ($status==='approved') { $h='🧑‍🔧 You\'re VERIFIED — you\'re live!';
        $b='Your ID is verified and your worker profile is <b>live</b>. Customers near you can now contact you.<br><br>💡 Tip: great reviews boost your ranking!';
        $c=['View My Public Profile',SITE_URL.'/worker.php?id='.$workerId]; }
    else { $h='Worker profile not approved';
        $b='Your profile did not pass verification. Ensure your ID proof is clear and valid, update and resubmit.';
        $c=['Update My Profile',SITE_URL.'/become-worker.php']; }
    return sendAppEmail($u,($status==='approved'?'🎉 You are verified on deal360.shop!':'Worker profile update needed'),emailTemplate($h,$b,$c[0],$c[1]),'worker_status',(int)$u['id']);
}
/* ---------- 7 Review ---------- */
function notifyNewReview(?array $wu, string $reviewer, int $rating, string $comment): bool {
    if (!SEND_REVIEW||!$wu||!userWantsEmails($wu)) return false;
    $h = emailTemplate('⭐ You received a new review!','<b>'.htmlspecialchars($reviewer).'</b> rated your work <b>'.str_repeat('⭐',$rating).'</b> ('.$rating.'/5)'.($comment!==''?'<br><div style="background:#f1f5f9;padding:12px 14px;border-radius:6px;margin-top:8px;">'.nl2br(htmlspecialchars($comment)).'</div>':''),'See My Reviews',SITE_URL.'/worker.php');
    return sendAppEmail($wu,'⭐ New '.$rating.'-star review from '.$reviewer,$h,'review',(int)$wu['id']);
}
/* ---------- 8 Password changed ---------- */
function notifyPasswordChanged(?array $u): bool {
    if (!$u) return false;
    $h = emailTemplate('🔐 Your password was changed','The password for <b>'.htmlspecialchars($u['email']).'</b> was changed on '.date('d M Y, h:i A').'.<br><br><b>Wasn\'t you?</b> Reset your password immediately and contact support.','Login',SITE_URL.'/login.php');
    return sendAppEmail($u,'🔐 Your deal360.shop password was changed',$h,'password_changed',(int)$u['id']);
}
/* ---------- 9 Plan expiring ---------- */
function notifyPlanExpiring(array $u, string $plan, string $type, string $till): bool {
    $h = emailTemplate('⏳ Your '.htmlspecialchars($plan).' plan expires in 3 days','Your <b>'.htmlspecialchars($type).'</b> plan <b>'.htmlspecialchars($plan).'</b> expires on <b>'.date('d M Y',strtotime($till)).'</b>.<br><br>After expiry you return to Free (5 views/day, 2 ads/month) and lose featured credits.','Renew Now',SITE_URL.'/upgrade.php');
    return sendAppEmail($u,'⏳ Your '.$plan.' plan expires soon — renew now',$h,'plan_expiring',(int)$u['id']);
}
/* ---------- 10 Admin daily report ---------- */
function notifyAdminDailyReport(array $admin, array $st): bool {
    $rows = ['New users today'=>number_format($st['new_users']),'New ads today'=>number_format($st['new_ads']),
        'Enquiries today'=>number_format($st['enquiries']),'Messages today'=>number_format($st['messages']),
        'Revenue today'=>'₹'.number_format($st['revenue']),'Active boosts'=>number_format($st['active_boosts']),
        '⚠️ Ads pending'=>number_format($st['pending_ads']),'⚠️ Workers pending'=>number_format($st['pending_workers'])];
    $t='<table style="width:100%;font-size:14px;background:#f8fafc;border-radius:8px;">';
    foreach ($rows as $k=>$v) $t.='<tr><td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;">'.$k.'</td><td style="padding:8px 12px;text-align:right;border-bottom:1px solid #e2e8f0;"><b>'.$v.'</b></td></tr>';
    $t.='</table>';
    $h = emailTemplate('📊 Daily Report — '.date('d M Y'),'Your deal360.shop snapshot:<br><br>'.$t.(($st['pending_ads']+$st['pending_workers'])>0?'<br>👉 Items awaiting your approval.':'<br>✅ Nothing pending!'),'Open Admin Panel',SITE_URL.'/admin.php');
    return sendAppEmail(['email'=>$admin['email'],'name'=>$admin['name']],'📊 Deal360 daily report — '.date('d M'),$h,'daily_report',(int)$admin['id']);
}