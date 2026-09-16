<?php
require_once 'config.php';
requireLogin();
if ($_SESSION['user']['role']!=='admin') { flash('Admins only.','error'); header('Location: dashboard.php'); exit; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (isset($_POST['lid'])) {
        $action=$_POST['action']==='approve'?'approved':'rejected';
        $pdo->prepare("UPDATE listings SET status=? WHERE id=?")->execute([$action,(int)$_POST['lid']]);
        $s=$pdo->prepare("SELECT l.*,u.id owner_id FROM listings l JOIN users u ON u.id=l.user_id WHERE l.id=?"); $s->execute([(int)$_POST['lid']]);
        if ($adRow=$s->fetch()) notifyAdStatus(getUserById((int)$adRow['owner_id']),$adRow,$action);
        flash('Ad '.$action.' — seller notified.');
    }
    if (isset($_POST['wid'])) {
        $action=$_POST['action']==='approve'?'approved':'rejected';
        $pdo->prepare("UPDATE workers SET status=? WHERE id=?")->execute([$action,(int)$_POST['wid']]);
        $s=$pdo->prepare("SELECT w.id,u.id owner_id FROM workers w JOIN users u ON u.id=w.user_id WHERE w.id=?"); $s->execute([(int)$_POST['wid']]);
        if ($wRow=$s->fetch()) notifyWorkerStatus(getUserById((int)$wRow['owner_id']),$action,(int)$wRow['id']);
        flash('Worker '.$action.' — notified.');
    }
    header('Location: admin.php'); exit;
}
 $pendingAds=$pdo->query("SELECT l.*,u.name uname FROM listings l JOIN users u ON u.id=l.user_id WHERE l.status='pending' ORDER BY l.created_at")->fetchAll();
 $pendingW=$pdo->query("SELECT w.*,u.name uname,u.mobile FROM workers w JOIN users u ON u.id=w.user_id WHERE w.status='pending' ORDER BY w.id")->fetchAll();
require 'includes/header.php';
?>
<div class="card"><h2>🛡️ Pending Ads (<?= count($pendingAds) ?>)</h2><table>
<tr><th>Title</th><th>By</th><th>Segment</th><th>Price</th><th>Action</th></tr>
<?php foreach($pendingAds as $a): ?>
<tr><td><a href="listing.php?id=<?= $a['id'] ?>"><?= htmlspecialchars($a['title']) ?></a></td><td><?= htmlspecialchars($a['uname']) ?></td>
<td><?= $a['segment'] ?>/<?= htmlspecialchars($a['category']) ?></td><td>₹<?= number_format((float)$a['price']) ?></td>
<td><form method="post" style="display:flex;gap:6px;"><input type="hidden" name="lid" value="<?= $a['id'] ?>">
<button name="action" value="approve" class="btn btn-primary">✔</button><button name="action" value="reject" class="btn btn-outline-dark">✖</button></form></td></tr>
<?php endforeach; if(!$pendingAds) echo '<tr><td colspan="5">Nothing pending 🎉</td></tr>'; ?></table></div>
<div class="card"><h2>🧑‍🔧 Pending Workers (<?= count($pendingW) ?>)</h2><table>
<tr><th>Worker</th><th>Profession</th><th>Rate</th><th>ID</th><th>Action</th></tr>
<?php foreach($pendingW as $w): ?>
<tr><td><?= htmlspecialchars($w['uname']) ?><br><span class="muted">+91 <?= htmlspecialchars($w['mobile']) ?></span></td>
<td><?= htmlspecialchars($w['category']) ?></td><td><?= rateLabel($w['rate_type'],$w['rate']) ?></td>
<td><?php if($w['id_proof']): ?><a href="<?= $w['id_proof'] ?>" target="_blank">🪪 View</a><?php else: ?>—<?php endif; ?></td>
<td><form method="post" style="display:flex;gap:6px;"><input type="hidden" name="wid" value="<?= $w['id'] ?>">
<button name="action" value="approve" class="btn btn-primary">✔ Verify</button><button name="action" value="reject" class="btn btn-outline-dark">✖</button></form></td></tr>
<?php endforeach; if(!$pendingW) echo '<tr><td colspan="5">No workers pending 🎉</td></tr>'; ?></table></div>
<?php require 'includes/footer.php'; ?>