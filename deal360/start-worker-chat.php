<?php
require_once 'config.php';
requireLogin();
 $uid=$_SESSION['user_id']; $wid=(int)($_GET['worker_id']??0);
 $s=$pdo->prepare("SELECT w.*,u.name FROM workers w JOIN users u ON u.id=w.user_id WHERE w.id=? AND w.status='approved'");
 $s->execute([$wid]); $w=$s->fetch();
if (!$w) { flash('Worker not found.','error'); header('Location: find-workers.php'); exit; }
if ($w['user_id']==$uid) { header('Location: become-worker.php'); exit; }
 $sub=activeSubscription($pdo,$uid,'buyer'); $plan=planInfo($pdo,$sub['plan_code'],'buyer');
if (!$plan['can_enquire']) { flash('💬 Contacting workers needs Gold plan+.','error'); header('Location: upgrade.php'); exit; }
 $s=$pdo->prepare("SELECT id FROM conversations WHERE worker_id=? AND buyer_id=? LIMIT 1"); $s->execute([$wid,$uid]);
 $conv=$s->fetch();
if ($conv) $cid=$conv['id'];
else { $pdo->prepare("INSERT INTO conversations (listing_id,buyer_id,seller_id,worker_id) VALUES (NULL,?,?,?)")->execute([$uid,$w['user_id'],$wid]); $cid=$pdo->lastInsertId(); }
header('Location: chat.php?id='.$cid);