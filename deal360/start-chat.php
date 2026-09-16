<?php
require_once 'config.php';
requireLogin();
 $uid=$_SESSION['user_id']; $lid=(int)($_GET['listing_id']??0);
 $s=$pdo->prepare("SELECT * FROM listings WHERE id=? AND status='approved'"); $s->execute([$lid]); $ad=$s->fetch();
if (!$ad) { flash('Ad not found.','error'); header('Location: listings.php'); exit; }
if ($ad['user_id']==$uid) { header('Location: listing.php?id='.$lid); exit; }
 $sub=activeSubscription($pdo,$uid,'buyer'); $plan=planInfo($pdo,$sub['plan_code'],'buyer');
if (!$plan['can_enquire']) { flash('💬 Chat available on Gold plan+. Please upgrade.','error'); header('Location: upgrade.php'); exit; }
 $s=$pdo->prepare("SELECT id FROM conversations WHERE listing_id=? AND buyer_id=?"); $s->execute([$lid,$uid]);
 $conv=$s->fetch();
if ($conv) $cid=$conv['id'];
else { $pdo->prepare("INSERT INTO conversations (listing_id,buyer_id,seller_id) VALUES (?,?,?)")->execute([$lid,$uid,$ad['user_id']]); $cid=$pdo->lastInsertId(); }
header('Location: chat.php?id='.$cid);