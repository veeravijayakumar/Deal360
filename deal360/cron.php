<?php
/* Run: CLI "php cron.php" · URL "?key=CRON_SECRET_KEY" · admin "?manual=1" — hourly recommended */
require_once __DIR__.'/config.php';
define('CLEAN_UNVERIFIED_AFTER_DAYS',7);
define('VIEW_LOG_RETENTION_DAYS',0);
define('RUN_DAILY_ADMIN_REPORT',true);
define('EXPIRY_WARN_DAYS',3);

 $isCli=(php_sapi_name()==='cli'); $trigger='url';
if ($isCli) $trigger='cli';
elseif (isLoggedIn()&&($_SESSION['user']['role']??'')==='admin'&&isset($_GET['manual'])) $trigger='admin';
elseif (($_GET['key']??'')!==CRON_SECRET_KEY||CRON_SECRET_KEY==='CHANGE-ME-long-random-string-9f8a7b') { http_response_code(403); exit('Forbidden'); }

 $lockFp=fopen(__DIR__.'/cron.lock','c');
if (!flock($lockFp,LOCK_EX|LOCK_NB)) { echo 'Already running.'; exit; }
 $startT=microtime(true); $log=[];
function task(string $n, callable $fn): void { global $log; $t0=microtime(true);
try { $log[$n]=['ok'=>true,'result'=>(string)$fn(),'ms'=>(int)((microtime(true)-$t0)*1000)]; }
catch (Throwable $e) { $log[$n]=['ok'=>false,'error'=>$e->getMessage(),'ms'=>(int)((microtime(true)-$t0)*1000)]; } }

task('expire_subscriptions',fn()=>$pdo->exec("UPDATE subscriptions SET status='expired' WHERE status='active' AND valid_till<CURDATE()").' expired');
task('expire_boosts',function() use ($pdo) {
    $a=$pdo->exec("UPDATE listings SET is_featured=0 WHERE is_featured=1 AND (is_featured_until IS NULL OR is_featured_until<NOW())");
    $b=$pdo->exec("UPDATE featured_orders SET status='expired' WHERE status='active' AND expires_at<NOW()");
    return "$a flags cleared · $b orders closed"; });
task('plan_expiry_warnings',function() use ($pdo) {
    $d=(int)EXPIRY_WARN_DAYS; if ($d<=0) return 'disabled';
    $rows=$pdo->query("SELECT user_id,plan_code,user_type,valid_till FROM subscriptions WHERE status='active' AND valid_till=CURDATE()+INTERVAL $d DAY")->fetchAll();
    $sent=0;
    foreach ($rows as $s) {
        $q=$pdo->prepare("SELECT COUNT(*) c FROM email_log WHERE user_id=? AND type='plan_expiring' AND DATE(created_at)=CURDATE()");
        $q->execute([$s['user_id']]); if ((int)$q->fetch()['c']>0) continue;
        $u=getUserById((int)$s['user_id']); if (!$u) continue;
        $plan=planInfo($pdo,$s['plan_code'],$s['user_type']);
        if (notifyPlanExpiring($u,$plan['name'],$s['user_type'],$s['valid_till'])) $sent++; }
    return count($rows).' expiring · '.$sent.' warned'; });
task('admin_daily_report',function() use ($pdo) {
    if (!RUN_DAILY_ADMIN_REPORT) return 'disabled';
    if ((int)$pdo->query("SELECT COUNT(*) c FROM email_log WHERE type='daily_report' AND DATE(created_at)=CURDATE()")->fetch()['c']>0) return 'already sent today';
    $admin=$pdo->query("SELECT id,name,email FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetch();
    if (!$admin) return 'no admin';
    $st=['new_users'=>(int)$pdo->query("SELECT COUNT(*) c FROM users WHERE DATE(created_at)=CURDATE()")->fetch()['c'],
    'new_ads'=>(int)$pdo->query("SELECT COUNT(*) c FROM listings WHERE DATE(created_at)=CURDATE()")->fetch()['c'],
    'pending_ads'=>(int)$pdo->query("SELECT COUNT(*) c FROM listings WHERE status='pending'")->fetch()['c'],
    'pending_workers'=>(int)$pdo->query("SELECT COUNT(*) c FROM workers WHERE status='pending'")->fetch()['c'],
    'enquiries'=>(int)$pdo->query("SELECT COUNT(*) c FROM enquiries WHERE DATE(created_at)=CURDATE()")->fetch()['c'],
    'messages'=>(int)$pdo->query("SELECT COUNT(*) c FROM messages WHERE DATE(created_at)=CURDATE()")->fetch()['c'],
    'revenue'=>(float)$pdo->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='paid' AND DATE(created_at)=CURDATE()")->fetch()['s'],
    'active_boosts'=>(int)$pdo->query("SELECT COUNT(*) c FROM featured_orders WHERE expires_at>NOW()")->fetch()['c']];
    notifyAdminDailyReport($admin,$st);
    return 'sent · ₹'.number_format($st['revenue']).' today'; });
task('cleanup_otp_data',function() use ($pdo) {
    $a=$pdo->exec("UPDATE users SET email_otp=NULL,mobile_otp=NULL,otp_expiry=NULL,otp_attempts=0 WHERE otp_expiry IS NOT NULL AND otp_expiry<NOW()-INTERVAL 1 DAY");
    $b=$pdo->exec("UPDATE users SET reset_otp=NULL,reset_otp_expiry=NULL,reset_attempts=0 WHERE reset_otp_expiry IS NOT NULL AND reset_otp_expiry<NOW()-INTERVAL 1 DAY");
    return "$a reg · $b reset cleaned"; });
task('fail_abandoned_payments',fn()=>$pdo->exec("UPDATE payments SET status='failed' WHERE status='pending' AND created_at<NOW()-INTERVAL 24 HOUR").' closed');
task('clean_unverified_users',function() use ($pdo) {
    $d=(int)CLEAN_UNVERIFIED_AFTER_DAYS; if ($d<=0) return 'disabled';
    return $pdo->exec("DELETE FROM users WHERE email_verified=0 AND mobile_verified=0 AND created_at<NOW()-INTERVAL $d DAY").' removed'; });
task('prune_view_logs',function() use ($pdo) {
    $d=(int)VIEW_LOG_RETENTION_DAYS; if ($d<=0) return 'disabled';
    $cut=date('Y-m-d H:i:s',time()-$d*86400);
    $pdo->prepare("INSERT INTO view_daily_stats (stat_date,listing_id,views) SELECT DATE(viewed_at),listing_id,COUNT(*) FROM view_logs WHERE viewed_at<? GROUP BY DATE(viewed_at),listing_id ON DUPLICATE KEY UPDATE views=views+VALUES(views)")->execute([$cut]);
    $del=$pdo->prepare("DELETE FROM view_logs WHERE viewed_at<? LIMIT 5000"); $deleted=0;
    do { $del->execute([$cut]); $n=$del->rowCount(); $deleted+=$n; } while ($n===5000);
    return "archived+removed $deleted"; });

 $duration=(int)((microtime(true)-$startT)*1000);
 $ok=0; $fail=0; $lines=[];
foreach ($log as $n=>$l) { $l['ok']?$ok++:$fail++; $lines[]=($l['ok']?'✅':'❌')." $n: ".($l['ok']?$l['result']:$l['error']); }
try { $pdo->prepare("INSERT INTO cron_runs (trigger_type,tasks_done,results,duration_ms) VALUES (?,?,?,?)")->execute([$trigger,$ok,json_encode($log),$duration]); } catch (Throwable $e) {}
flock($lockFp,LOCK_UN); fclose($lockFp);
if ($isCli) echo implode("\n",$lines)."\nDone: $ok ok · $fail failed · {$duration}ms\n";
else echo '<meta charset="utf-8"><div style="font-family:Segoe UI;max-width:720px;margin:40px auto;"><h2>🧹 Maintenance Run</h2><p>'.$ok.' ok · '.$fail.' failed · '.$duration.'ms</p><ul>'.implode('',array_map(fn($x)=>'<li>'.htmlspecialchars($x).'</li>',$lines)).'</ul><a href="cron-log.php">← Back</a></div>';