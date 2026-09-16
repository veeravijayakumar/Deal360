<?php
require_once 'config.php';
requireLogin();
if ($_SESSION['user']['role']!=='admin') { flash('Admins only.','error'); header('Location: dashboard.php'); exit; }
 $rows=$pdo->query("SELECT * FROM email_log ORDER BY id DESC LIMIT 50")->fetchAll();
 $stats=$pdo->query("SELECT status,COUNT(*) c FROM email_log GROUP BY status")->fetchAll();
require 'includesheader.php';
?>
<div class="card"><h2>📧 Email Log (last 50)</h2>
<p><?php foreach ($stats as $s): ?><span class="tag <?= $s['status']==='sent'?'ok':($s['status']==='dev_only'?'pending':'no') ?>"><?= $s['status'] ?>: <?= $s['c'] ?></span> <?php endforeach; ?>
<?= NOTIFY_DEV_MODE?'<span class="tag pending">NOTIFY_DEV_MODE = ON</span>':'' ?></p>
<table><tr><th>When</th><th>To</th><th>Subject</th><th>Type</th><th>Status</th><th>Error</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td class="muted"><?= $r['created_at'] ?></td><td><?= htmlspecialchars($r['email']) ?></td>
<td><?= htmlspecialchars($r['subject']) ?></td><td><span class="tag pending"><?= $r['type'] ?></span></td>
<td><span class="tag <?= $r['status']==='sent'?'ok':($r['status']==='dev_only'?'pending':'no') ?>"><?= $r['status'] ?></span></td>
<td class="muted"><?= htmlspecialchars($r['error_text']??'') ?></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="6">No emails yet.</td></tr>'; ?></table></div>
<?php require 'includesfooter.php'; ?>