<?php
/* ============ ENV: set to false in production ============ */
// define('APP_DEV', true);                 // true = show errors (localhost)
// define('CRON_SECRET_KEY', 'CHANGE-ME-long-random-string-9f8a7b');

ini_set('display_errors', APP_DEV ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');
// session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
// session_start();

// define('DB_HOST','localhost'); define('DB_NAME','deal360');
// define('DB_USER','root');      define('DB_PASS','');   // change on hosting!

try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch (Exception $e) { die('Database connection failed.'); }

// require_once __DIR__ . '/notifications.php';
// require_once __DIR__ . '/razorpay.php';

/* ============ AUTH / FLASH ============ */
//function isLoggedIn() { return isset($_SESSION['user_id']); }
//function requireLogin() { if (!isLoggedIn()) { header('Location: login.php'); exit; } }
//function flash($msg, $type='success') { $_SESSION['flash'] = ['msg'=>$msg,'type'=>$type]; }
//function getFlash() { if (isset($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; } return null; }

/* ============ SUBSCRIPTIONS & PLANS ============ */
// function activeSubscription(PDO $pdo, int $userId, string $type): array {
//     $s = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id=? AND user_type=? AND status='active' AND valid_till>=CURDATE() ORDER BY id DESC LIMIT 1");
//     $s->execute([$userId,$type]); $sub = $s->fetch();
//     if (!$sub) {
//         $pdo->prepare("INSERT INTO subscriptions (user_id,user_type,plan_code,valid_till) VALUES (?,?,?,DATE_ADD(CURDATE(),INTERVAL 100 YEAR))")
//             ->execute([$userId,$type,'free']);
//         return activeSubscription($pdo,$userId,$type);
//     }
//     return $sub;
// }
// function planInfo(PDO $pdo, string $code, string $type): array {
//     $s = $pdo->prepare("SELECT * FROM plans WHERE code=? AND user_type=?"); $s->execute([$code,$type]);
//     return $s->fetch() ?: ['views_limit'=>5,'daily_reset'=>1,'ads_limit'=>2,'featured_limit'=>0,'can_enquire'=>0,'name'=>'Free','price'=>0];
// }
// function getUserById(int $id): ?array {
//     global $pdo;
//     $s = $pdo->prepare("SELECT id,name,email,mobile,notify_emails FROM users WHERE id=?"); $s->execute([$id]);
//     return $s->fetch() ?: null;
// }

/* ============ BUYER VIEW LIMITS ============ */
// function viewsUsed(PDO $pdo, int $userId, array $sub, array $plan): int {
//     if ($plan['daily_reset']) {
//         $s = $pdo->prepare("SELECT COUNT(*) c FROM view_logs WHERE user_id=? AND DATE(viewed_at)=CURDATE()");
//         $s->execute([$userId]);
//     } else {
//         $s = $pdo->prepare("SELECT COUNT(*) c FROM view_logs WHERE user_id=? AND viewed_at>=?");
//         $s->execute([$userId,$sub['created_at']]);
//     }
//     return (int)$s->fetch()['c'];
// }
// function checkViewAccess(PDO $pdo, int $userId): array {
//     $sub = activeSubscription($pdo,$userId,'buyer');
//     $plan = planInfo($pdo,$sub['plan_code'],'buyer');
//     if ((int)$plan['views_limit']===0) return [true,'unlimited',PHP_INT_MAX];
//     $remaining = (int)$plan['views_limit'] - viewsUsed($pdo,$userId,$sub,$plan);
//     if ($remaining<=0) return [false,"Daily limit reached ({$plan['name']} = {$plan['views_limit']} views/day). Upgrade your plan.",0];
//     return [true,'ok',$remaining];
// }
// function recordView(PDO $pdo, int $userId, int $listingId): void {
//     $pdo->prepare("INSERT INTO view_logs (user_id,listing_id) VALUES (?,?)")->execute([$userId,$listingId]);
// }

// /* ============ SELLER AD LIMITS ============ */
// function checkAdLimit(PDO $pdo, int $userId): array {
//     $sub = activeSubscription($pdo,$userId,'seller');
//     $plan = planInfo($pdo,$sub['plan_code'],'seller');
//     if ((int)$plan['ads_limit']===0) return [true,PHP_INT_MAX,$sub,$plan];
//     $remaining = (int)$plan['ads_limit'] - (int)$sub['ads_used'];
//     return [$remaining>0, max($remaining,0), $sub, $plan];
// }

/* ============ HELPERS ============ */
// function maskPhone($p) { return 'XXXXXX' . substr($p, -4); }
// function maskEmail(string $e): string {
//     $p = explode('@',$e); $n = $p[0] ?? '';
//     return substr($n,0,1) . str_repeat('*', max(1,strlen($n)-1)) . '@' . ($p[1] ?? '');
// }
// function rateLabel($type,$rate) {
//     $u = ['hour'=>'hour','day'=>'day','job'=>'job'][$type] ?? 'hour';
//     return '₹'.number_format((float)$rate).' / '.$u;
// }
// function haversineKm($lat1,$lng1,$lat2,$lng2) {
//     $earth=6371; $dLat=deg2rad($lat2-$lat1); $dLng=deg2rad($lng2-$lng1);
//     $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)**2;
//     return $earth*2*atan2(sqrt($a),sqrt(1-$a));
// }
// function slugify(string $s): string {
//     $s = strtolower(trim($s)); $s = preg_replace('/[^a-z0-9]+/','-',$s);
//     return trim($s,'-') ?: 'item';
// }
// function refreshFeaturedFlags(PDO $pdo): void {
//     $pdo->exec("UPDATE listings SET is_featured=0 WHERE is_featured=1 AND (is_featured_until IS NULL OR is_featured_until<NOW())");
// }
// function activeBoostUntil(PDO $pdo, int $listingId): ?string {
//     $s = $pdo->prepare("SELECT is_featured_until FROM listings WHERE id=? AND is_featured=1 AND is_featured_until>NOW()");
//     $s->execute([$listingId]); return $s->fetchColumn() ?: null;
// }