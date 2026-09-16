<?php
require_once __DIR__.'/razorpay_config.php';

function rzpApi(string $path, array $payload=[]): array {
    $ch = curl_init('https://api.razorpay.com/v1/'.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_USERPWD=>RZP_KEY_ID.':'.RZP_KEY_SECRET,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>15]);
    if ($payload) { curl_setopt($ch,CURLOPT_POST,true); curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload)); }
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($res===false) throw new Exception('Razorpay unreachable: '.$err);
    $d = json_decode($res,true);
    if (isset($d['error'])) throw new Exception($d['error']['description']??'Razorpay error');
    return $d;
}
function rzpCreateOrder(float $inr, string $receipt, array $notes=[]): array {
    return rzpApi('orders',['amount'=>(int)round($inr*100),'currency'=>'INR','receipt'=>substr($receipt,0,40),'notes'=>$notes]);
}
function rzpFetchPayment(string $id): ?array {
    try { return rzpApi('payments/'.urlencode($id)); } catch (Exception $e) { return null; }
}
function rzpVerifySignature(string $oid, string $pid, string $sig): bool {
    return $sig!=='' && hash_equals(hash_hmac('sha256',$oid.'|'.$pid,RZP_KEY_SECRET),$sig);
}
function rzpVerifyWebhook(string $raw, string $sig): bool {
    return $sig!=='' && hash_equals(hash_hmac('sha256',$raw,RZP_WEBHOOK_SECRET),$sig);
}
function createPendingPayment(PDO $pdo, int $userId, string $userType, string $purpose, string $label, float $amount, array $meta): int {
    $receipt='D360-'.uniqid();
    $order = rzpCreateOrder($amount,$receipt,['purpose'=>$purpose,'user'=>(string)$userId]);
    $pdo->prepare("INSERT INTO payments (user_id,user_type,plan_code,amount,txn_id,status,purpose,meta,rzp_order_id) VALUES (?,?,?,?,?,'pending',?,?,?)")
        ->execute([$userId,$userType,$label,$amount,$receipt,$purpose,json_encode($meta),$order['id']]);
    return (int)$pdo->lastInsertId();
}
function activatePaidPayment(PDO $pdo, array $pay, string $rzpPaymentId, ?string $sig=null): void {
    if ($pay['status']==='paid') return;
    $pdo->prepare("UPDATE payments SET status='paid', rzp_payment_id=?, rzp_signature=COALESCE(?,rzp_signature) WHERE id=?")
        ->execute([$rzpPaymentId,$sig,$pay['id']]);
    $meta = json_decode($pay['meta']?:'{}',true);
    if ($pay['purpose']==='subscription') {
        $type=$pay['user_type'];
        $sub = activeSubscription($pdo,(int)$pay['user_id'],$type);
        $pdo->prepare("UPDATE subscriptions SET plan_code=?,views_used=0,ads_used=0,featured_used=0,valid_till=DATE_ADD(CURDATE(),INTERVAL 30 DAY) WHERE id=?")
            ->execute([$pay['plan_code'],$sub['id']]);
        $u = getUserById((int)$pay['user_id']);
        if ($u && function_exists('notifyPlanActivated')) {
            $plan = planInfo($pdo,$pay['plan_code'],$type);
            notifyPlanActivated($u,$plan['name'],$type,(float)$pay['amount'],date('d M Y',strtotime('+30 days')));
        }
    } elseif ($pay['purpose']==='boost') {
        $s=$pdo->prepare("SELECT * FROM listings WHERE id=? AND user_id=?");
        $s->execute([(int)($meta['listing']??0),$pay['user_id']]); $ad=$s->fetch();
        if ($ad) {
            $days=(int)($meta['days']??7);
            $base=($ad['is_featured_until']&&strtotime($ad['is_featured_until'])>time())?strtotime($ad['is_featured_until']):time();
            $exp=date('Y-m-d H:i:s',$base+$days*86400);
            $pdo->prepare("INSERT INTO featured_orders (listing_id,user_id,package_id,source,amount,days,starts_at,expires_at,txn_id) VALUES (?,?,?,'package',?,?,NOW(),?,?)")
                ->execute([(int)$ad['id'],$pay['user_id'],(int)($meta['pkg']??0),$pay['amount'],$days,$exp,$rzpPaymentId]);
            $pdo->prepare("UPDATE listings SET is_featured=1,is_featured_until=? WHERE id=?")->execute([$exp,$ad['id']]);
        }
    }
}
function processSuccessfulPayment(PDO $pdo, string $oid, string $pid, string $sig): array {
    $s=$pdo->prepare("SELECT * FROM payments WHERE rzp_order_id=?"); $s->execute([$oid]); $pay=$s->fetch();
    if (!$pay) return [false,'Unknown order'];
    if ($pay['status']==='paid') return [true,'Already processed'];
    if (!rzpVerifySignature($oid,$pid,$sig)) return [false,'Signature verification failed'];
    activatePaidPayment($pdo,$pay,$pid,$sig);
    return [true,'OK'];
}
function processWebhookPayment(PDO $pdo, string $oid, string $pid): bool {
    $s=$pdo->prepare("SELECT * FROM payments WHERE rzp_order_id=?"); $s->execute([$oid]); $pay=$s->fetch();
    if (!$pay||$pay['status']==='paid') return true;
    $r = rzpFetchPayment($pid);
    if (!$r||$r['status']!=='captured'||($r['order_id']??'')!==$oid) return false;
    activatePaidPayment($pdo,$pay,$pid,null);
    return true;
}