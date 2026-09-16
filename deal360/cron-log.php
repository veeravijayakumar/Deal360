<?php
require_once 'config.php';
requireLogin();
if ($_SESSION['user']['role']!=='admin') { flash('Admins only.','error'); header('Location: dashboard.php'); exit; }
 $runs=$pdo->query("SELECT * FROM cron_runs ORDER BY id DESC LIMIT 30")->fetchAll();
 $last=$runs[0]??null;
require 'includesheader.php';
?>
<div class="topbar"><h2 style="margin:0;">🧹 Cron / Maintenance</h2>
<a href="cron.php?manual=1" class="btn btn-accent">▶ Run Now</a></div>
<div class="card"><p><b>Last run:</b> <?= $last?date('d M Y, h:i A',strtotime($last['ran_at'])).' ('.$last['trigger_type'].', '.$last['duration_ms'].'ms)':'never' ?></p>
<p class="muted">Schedule hourly in cPanel. Safe to run often — all tasks dedupe.</p></div>
<div class="card"><h2>📜 Run History</h2><table>
<tr><th>When</th><th>Trigger</th><th>OK</th><th>Duration</th><th>Details</th></tr>
<?php foreach ($runs as $r): $res=json_decode($r['results'],true)?:[]; ?>
<tr><td><?= date('d M, h:i A',strtotime($r['ran_at'])) ?></td><td><span class="tag pending"><?= $r['trigger_type'] ?></span></td>
<td><?= (int)$r['tasks_done'] ?></td><td><?= (int)$r['duration_ms'] ?> ms</td>
<td><details><summary class="muted" style="cursor:pointer;">view</summary><ul style="font-size:.8rem;">
<?php foreach ($res as $n=>$l): ?><li><?= $l['ok']?'✅':'❌' ?> <b><?= htmlspecialchars($n) ?></b>: <?= htmlspecialchars($l['ok']?$l['result']:$l['error']) ?></li><?php endforeach; ?>
</ul></details></td></tr>
<?php endforeach; if (!$runs) echo '<tr><td colspan="5">No runs yet.</td></tr>'; ?></table></div>
<?php require 'includesfooter.php'; ?>