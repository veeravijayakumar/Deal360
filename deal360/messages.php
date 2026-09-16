<?php
require_once 'config.php';
requireLogin();
 $uid=$_SESSION['user_id'];
 $s=$pdo->prepare("SELECT c.id,l.id lid,l.title ad_title,w.id wid,w.category wcat,uw.name wname,
 CASE WHEN c.buyer_id=? THEN u2.name ELSE u1.name END other_name,
 (SELECT message FROM messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) last_msg,
 (SELECT created_at FROM messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) last_at,
 (SELECT COUNT(*) FROM messages m WHERE m.conversation_id=c.id AND m.sender_id<>? AND m.is_read=0) unread
 FROM conversations c LEFT JOIN listings l ON l.id=c.listing_id LEFT JOIN workers w ON w.id=c.worker_id
 LEFT JOIN users uw ON uw.id=w.user_id JOIN users u1 ON u1.id=c.buyer_id JOIN users u2 ON u2.id=c.seller_id
 WHERE c.buyer_id=? OR c.seller_id=? ORDER BY c.last_message_at DESC");
 $s->execute([$uid,$uid,$uid,$uid]); $rows=$s->fetchAll();
require 'includesheader.php';
?>
<div class="card"><h2>📬 My Messages</h2><table>
<tr><th>About</th><th>Chat With</th><th>Last Message</th><th>When</th><th></th></tr>
<?php foreach($rows as $c): $link=$c['lid']?'listing.php?id='.$c['lid']:'worker.php?id='.$c['wid'];
 $about=$c['lid']?htmlspecialchars($c['ad_title']):'🔧 '.htmlspecialchars($c['wcat']).' — '.htmlspecialchars($c['wname']); ?>
<tr><td><a href="<?= $link ?>"><?= $about ?></a></td><td><b><?= htmlspecialchars($c['other_name']) ?></b></td>
<td><?= htmlspecialchars(mb_strimwidth($c['last_msg']??'—',0,55,'...')) ?></td>
<td class="muted"><?= $c['last_at']?date('d M, h:i A',strtotime($c['last_at'])):'—' ?></td>
<td><a class="btn btn-primary" href="chat.php?id=<?= $c['id'] ?>">Open<?php if($c['unread']>0): ?><span class="tag no"><?= $c['unread'] ?> new</span><?php endif; ?></a></td></tr>
<?php endforeach; if(!$rows) echo '<tr><td colspan="5">No conversations yet.</td></tr>'; ?>
</table></div>
<?php require 'includesfooter.php'; ?>