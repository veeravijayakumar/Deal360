<?php
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['error'=>'login']); exit; }
 $uid=$_SESSION['user_id']; $cid=(int)($_POST['cid']??0); $text=trim($_POST['message']??'');
if ($text===''||mb_strlen($text)>1000) { echo json_encode(['error'=>'invalid']); exit; }
 $s=$pdo->prepare("SELECT id FROM conversations WHERE id=? AND (buyer_id=? OR seller_id=?)"); $s->execute([$cid,$uid,$uid]);
if (!$s->fetch()) { echo json_encode(['error'=>'not_found']); exit; }
 $s=$pdo->prepare("SELECT COUNT(*) c FROM messages WHERE sender_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 MINUTE)"); $s->execute([$uid]);
if ((int)$s->fetch()['c']>=20) { echo json_encode(['error'=>'rate']); exit; }
 $pdo->prepare("INSERT INTO messages (conversation_id,sender_id,message) VALUES (?,?,?)")->execute([$cid,$uid,$text]);
 $pdo->prepare("UPDATE conversations SET last_message_at=NOW() WHERE id=?")->execute([$cid]);
 $cq=$pdo->prepare("SELECT c.*,l.title ad_title,w.category wcat FROM conversations c LEFT JOIN listings l ON l.id=c.listing_id LEFT JOIN workers w ON w.id=c.worker_id WHERE c.id=?");
 $cq->execute([$cid]);
if ($c=$cq->fetch()) {
    $rid=((int)$c['buyer_id']===$uid)?(int)$c['seller_id']:(int)$c['buyer_id'];
    $about=$c['listing_id']?'Ad: '.$c['ad_title']:'Worker: '.$c['wcat'];
    notifyNewChatMessage(getUserById($rid),$_SESSION['user']['name']??'Someone',$about,$text,$cid);
}
echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId(),'time'=>date('d M, h:i A')]);