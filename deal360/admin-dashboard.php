<?php
require_once 'config.php';
requireLogin();
if ($_SESSION['user']['role'] !== 'admin') { flash('Admins only.', 'error'); header('Location: dashboard.php'); exit; }

/* ---------- Local helpers ---------- */
function scalar(PDO $pdo, string $sql, array $p = []) {
    $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn();
}
function moneyShort($n) {
    $n = (float)$n;
    if ($n >= 100000) return round($n/100000, 1) . 'L';
    if ($n >= 1000)   return round($n/1000, 1) . 'k';
    return (string)round($n);
}

/* ================= MODERATION ACTIONS (inline approve/reject) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['lid'])) {
        $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
        $pdo->prepare("UPDATE listings SET status=? WHERE id=?")->execute([$action, (int)$_POST['lid']]);
        $s = $pdo->prepare("SELECT l.*, u.id owner_id FROM listings l JOIN users u ON u.id=l.user_id WHERE l.id=?");
        $s->execute([(int)$_POST['lid']]);
        if ($adRow = $s->fetch()) notifyAdStatus(getUserById((int)$adRow['owner_id']), $adRow, $action);
        flash('Ad ' . $action . ' — seller notified.');
    }
    if (isset($_POST['wid'])) {
        $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
        $pdo->prepare("UPDATE workers SET status=? WHERE id=?")->execute([$action, (int)$_POST['wid']]);
        $s = $pdo->prepare("SELECT w.id, u.id owner_id FROM workers w JOIN users u ON u.id=w.user_id WHERE w.id=?");
        $s->execute([(int)$_POST['wid']]);
        if ($wRow = $s->fetch()) notifyWorkerStatus(getUserById((int)$wRow['owner_id']), $action, (int)$wRow['id']);
        flash('Worker ' . $action . ' — notified.');
    }
    header('Location: admin-dashboard.php'); exit;
}

/* ================= CORE STAT CARDS ================= */
 $st = [
    'users'         => (int)scalar($pdo, "SELECT COUNT(*) FROM users"),
    'users_today'   => (int)scalar($pdo, "SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()"),
    'ads_live'      => (int)scalar($pdo, "SELECT COUNT(*) FROM listings WHERE status='approved'"),
    'ads_pending'   => (int)scalar($pdo, "SELECT COUNT(*) FROM listings WHERE status='pending'"),
    'views_today'   => (int)scalar($pdo, "SELECT COUNT(*) FROM view_logs WHERE DATE(viewed_at)=CURDATE()"),
    'enq_total'     => (int)scalar($pdo, "SELECT COUNT(*) FROM enquiries"),
    'enq_today'     => (int)scalar($pdo, "SELECT COUNT(*) FROM enquiries WHERE DATE(created_at)=CURDATE()"),
    'msg_today'     => (int)scalar($pdo, "SELECT COUNT(*) FROM messages WHERE DATE(created_at)=CURDATE()"),
    'revenue'       => (float)scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'"),
    'revenue_today' => (float)scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND DATE(created_at)=CURDATE()"),
    'rev_subs'      => (float)scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND purpose='subscription'"),
    'rev_boost'     => (float)scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND purpose='boost'"),
    'boosts_active' => (int)scalar($pdo, "SELECT COUNT(*) FROM featured_orders WHERE expires_at > NOW()"),
    'workers_ok'    => (int)scalar($pdo, "SELECT COUNT(*) FROM workers WHERE status='approved'"),
    'workers_pend'  => (int)scalar($pdo, "SELECT COUNT(*) FROM workers WHERE status='pending'"),
];
 $roles = $pdo->query("SELECT role, COUNT(*) c FROM users GROUP BY role")->fetchAll();
 $roleMap = [];
foreach ($roles as $r) $roleMap[$r['role']] = (int)$r['c'];

/* ================= 14-DAY REVENUE CHART (stacked) ================= */
 $revRows = $pdo->query("SELECT DATE(created_at) d,
        SUM(CASE WHEN purpose='subscription' THEN amount ELSE 0 END) subs,
        SUM(CASE WHEN purpose='boost' THEN amount ELSE 0 END) boosts
        FROM payments WHERE status='paid'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
        GROUP BY DATE(created_at)")->fetchAll();
 $revMap = [];
foreach ($revRows as $r) $revMap[$r['d']] = ['subs' => (float)$r['subs'], 'boosts' => (float)$r['boosts']];

 $revDays = []; $revMax = 0;
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $v = $revMap[$d] ?? ['subs' => 0, 'boosts' => 0];
    $tot = $v['subs'] + $v['boosts'];
    $revDays[] = ['label' => date('d/m', strtotime($d)), 'subs' => $v['subs'], 'boosts' => $v['boosts'], 'tot' => $tot];
    $revMax = max($revMax, $tot);
}

/* ================= 14-DAY SIGNUP CHART ================= */
 $uRows = $pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM users
                      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                      GROUP BY DATE(created_at)")->fetchAll();
 $uMap = []; foreach ($uRows as $r) $uMap[$r['d']] = (int)$r['c'];
 $uDays = []; $uMax = 0;
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = $uMap[$d] ?? 0;
    $uDays[] = ['label' => date('d/m', strtotime($d)), 'c' => $c];
    $uMax = max($uMax, $c);
}

/* ================= SEGMENT BREAKDOWN ================= */
 $segs = $pdo->query("SELECT segment, COUNT(*) total,
        SUM(status='approved') live, SUM(status='pending') pend, SUM(status='rejected') rej
        FROM listings GROUP BY segment")->fetchAll();
 $segMax = 1; foreach ($segs as $s) $segMax = max($segMax, (int)$s['total']);
 $segMeta = [
    'property'    => ['🏠', 'linear-gradient(90deg,#60a5fa,#1d4ed8)'],
    'vehicles'    => ['🚗', 'linear-gradient(90deg,#fdba74,#ea580c)'],
    'electronics' => ['📱', 'linear-gradient(90deg,#c4b5fd,#6d28d9)'],
];
 $wCats = $pdo->query("SELECT category, COUNT(*) c FROM workers WHERE status='approved'
                      GROUP BY category ORDER BY c DESC LIMIT 6")->fetchAll();

/* ================= TABLES ================= */
 $pendingAds = $pdo->query("SELECT l.*, u.name uname FROM listings l JOIN users u ON u.id=l.user_id
                           WHERE l.status='pending' ORDER BY l.created_at LIMIT 6")->fetchAll();
 $pendingWk = $pdo->query("SELECT w.*, u.name uname FROM workers w JOIN users u ON u.id=w.user_id
                          WHERE w.status='pending' ORDER BY w.id LIMIT 6")->fetchAll();
 $recentUsers = $pdo->query("SELECT id, name, email, mobile, role, email_verified, mobile_verified, created_at
                            FROM users ORDER BY id DESC LIMIT 8")->fetchAll();
 $recentAds = $pdo->query("SELECT l.*, u.name uname FROM listings l JOIN users u ON u.id=l.user_id
                          ORDER BY l.created_at DESC LIMIT 8")->fetchAll();
 $topAds = $pdo->query("SELECT l.id, l.title, l.segment,
        (SELECT COUNT(*) FROM view_logs vl WHERE vl.listing_id=l.id) v
        FROM listings l WHERE l.status='approved' ORDER BY v DESC LIMIT 5")->fetchAll();

/* ================= USER SEARCH (support tool) ================= */
 $search = trim($_GET['q'] ?? '');
 $found = [];
if ($search !== '') {
    $s = $pdo->prepare("SELECT id, name, email, mobile, role, email_verified, mobile_verified, created_at
                        FROM users WHERE name LIKE ? OR email LIKE ? OR mobile LIKE ? LIMIT 10");
    $s->execute(["%$search%", "%$search%", "%$search%"]);
    $found = $s->fetchAll();
}

/* ================= PLATFORM HEALTH / GO-LIVE CHECKS ================= */
 $cronLast = $pdo->query("SELECT * FROM cron_runs ORDER BY id DESC LIMIT 1")->fetch();
 $health = [];
 $health[] = [!(defined('OTP_DEV_MODE') && OTP_DEV_MODE), 'OTP delivery is LIVE (real SMS/email)', 'OTP_DEV_MODE is ON — OTPs are shown on screen, not sent'];
 $health[] = [!(defined('NOTIFY_DEV_MODE') && NOTIFY_DEV_MODE), 'Notification emails are LIVE', 'NOTIFY_DEV_MODE is ON — emails are only logged'];
 $health[] = [defined('RZP_LIVE_MODE') && RZP_LIVE_MODE, 'Razorpay is LIVE (real payments)', 'Razorpay is in TEST mode — no real money charged'];
 $health[] = [(int)$st['ads_pending'] === 0 && (int)$st['workers_pend'] === 0, 'Moderation queue is clear 🎉', ($st['ads_pending'] + $st['workers_pend']) . ' item(s) awaiting approval'];
 $cronOk = $cronLast && (time() - strtotime($cronLast['ran_at'])) < 26 * 3600;
 $health[] = [$cronOk, 'Cron ran within the last 26 hours', $cronLast ? 'Cron last ran ' . date('d M, h:i A', strtotime($cronLast['ran_at'])) : 'Cron has NEVER run — set it up!'];

require 'includesheader.php';
?>
<style>
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:22px;}
.stat{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;text-align:center;}
.stat h3{font-size:1.6rem;color:var(--primary);margin-bottom:2px;}
.stat p{font-size:.76rem;color:#64748b;margin:0;}
.stat .sub{font-size:.72rem;color:#059669;font-weight:700;}
.bars{display:flex;align-items:flex-end;gap:6px;height:200px;padding:26px 4px 28px;border-bottom:2px solid #e2e8f0;}
.bar{flex:1;display:flex;flex-direction:column;justify-content:flex-end;position:relative;min-height:4px;}
.bar .seg{width:100%;}
.bar .seg.s1{background:linear-gradient(180deg,#60a5fa,#1d4ed8);}
.bar .seg.s2{background:linear-gradient(180deg,#fb923c,#ea580c);}
.bar.solo .seg{background:linear-gradient(180deg,#34d399,#059669);border-radius:6px 6px 0 0;}
.bar b{position:absolute;top:-18px;left:50%;transform:translateX(-50%);font-size:.66rem;color:#334155;white-space:nowrap;}
.bar span{position:absolute;bottom:-22px;left:50%;transform:translateX(-50%);font-size:.62rem;color:#94a3b8;white-space:nowrap;}
.legend{display:flex;gap:18px;font-size:.78rem;color:#475569;margin-top:12px;}
.legend i{display:inline-block;width:12px;height:12px;border-radius:3px;margin-right:6px;vertical-align:-1px;}
.segrow{display:flex;align-items:center;gap:12px;margin-bottom:12px;font-size:.9rem;}
.segrow .nm{width:130px;font-weight:600;flex-shrink:0;}
.segbar{flex:1;height:14px;background:#f1f5f9;border-radius:10px;overflow:hidden;}
.segbar>div{height:100%;border-radius:10px;}
.health li{padding:7px 0;border-bottom:1px dashed #e2e8f0;font-size:.9rem;list-style:none;}
.chip{display:inline-block;background:#eff6ff;color:#1d4ed8;border-radius:20px;padding:3px 12px;font-size:.78rem;font-weight:700;margin:2px;}
</style>

<div class="topbar">
  <h2 style="margin:0;">🛡️ Admin Dashboard</h2>
  <div>
    <a href="admin.php" class="btn btn-primary">📋 Full Moderation Queue<?= $st['ads_pending'] + $st['workers_pend'] ? ' (' . ($st['ads_pending'] + $st['workers_pend']) . ')' : '' ?></a>
    <a href="admin-dashboard.php" class="btn btn-outline-dark">🔄 Refresh</a>
  </div>
</div>

<!-- ============ STAT CARDS ============ -->
<div class="stat-grid">
  <div class="stat"><h3><?= number_format($st['users']) ?></h3><p>Total Users</p><span class="sub">+<?= $st['users_today'] ?> today</span></div>
  <div class="stat"><h3><?= number_format($st['ads_live']) ?></h3><p>Live Ads</p><span class="sub"><?= $st['ads_pending'] ?> pending</span></div>
  <div class="stat"><h3>₹<?= number_format($st['revenue']) ?></h3><p>Total Revenue</p><span class="sub">+₹<?= number_format($st['revenue_today']) ?> today</span></div>
  <div class="stat"><h3><?= number_format($st['views_today']) ?></h3><p>Ad Views Today</p></div>
  <div class="stat"><h3><?= number_format($st['enq_total']) ?></h3><p>Enquiries</p><span class="sub">+<?= $st['enq_today'] ?> today</span></div>
  <div class="stat"><h3><?= number_format($st['msg_today']) ?></h3><p>Chats Today</p></div>
  <div class="stat"><h3><?= $st['boosts_active'] ?></h3><p>Active Boosts</p></div>
  <div class="stat"><h3><?= $st['workers_ok'] ?></h3><p>Verified Workers</p><span class="sub"><?= $st['workers_pend'] ?> pending</span></div>
</div>

<!-- ============ REVENUE CHART (stacked) ============ -->
<div class="card">
  <h2>💰 Revenue — Last 14 Days</h2>
  <?php if ($revMax > 0): ?>
  <div class="bars">
    <?php foreach ($revDays as $d): $h = $d['tot'] > 0 ? round($d['tot'] / $revMax * 100) : 0; ?>
    <div class="bar" style="height:<?= max(4, $h) ?>%;">
      <?php if ($d['tot'] > 0): ?>
        <div class="seg s2" style="height:<?= round($d['boosts'] / $d['tot'] * 100) ?>%;"></div>
        <div class="seg s1" style="height:<?= round($d['subs'] / $d['tot'] * 100) ?>%;"></div>
        <b>₹<?= moneyShort($d['tot']) ?></b>
      <?php endif; ?>
      <span><?= $d['label'] ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="legend">
    <span><i style="background:#1d4ed8;"></i>Subscriptions ₹<?= number_format($st['rev_subs']) ?></span>
    <span><i style="background:#ea580c;"></i>Boosts ₹<?= number_format($st['rev_boost']) ?></span>
  </div>
  <?php else: ?><p class="muted">No paid payments in the last 14 days yet.</p><?php endif; ?>
</div>

<!-- ============ SIGNUP CHART + SEGMENTS (side by side) ============ -->
<div class="grid2">
  <div class="card">
    <h2>👥 New Signups — Last 14 Days</h2>
    <?php if ($uMax > 0): ?>
    <div class="bars" style="height:160px;">
      <?php foreach ($uDays as $d): $h = $d['c'] > 0 ? round($d['c'] / $uMax * 100) : 0; ?>
      <div class="bar solo" style="height:<?= max(4, $h) ?>%;">
        <?php if ($d['c'] > 0): ?><b><?= $d['c'] ?></b><?php endif; ?>
        <span><?= $d['label'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p class="muted">No signups in the last 14 days.</p><?php endif; ?>
    <div style="margin-top:14px;">
      <?php foreach (['buyer'=>'👤','seller'=>'🏪','worker'=>'🔧','admin'=>'🛡️'] as $role => $ico): if(empty($roleMap[$role])) continue; ?>
        <span class="chip"><?= $ico ?> <?= ucfirst($role) ?>s: <?= number_format($roleMap[$role]) ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h2>📦 Ads by Segment</h2>
    <?php foreach ($segs as $s): [$ico, $grad] = $segMeta[$s['segment']] ?? ['📦', '#94a3b8']; ?>
    <div class="segrow">
      <div class="nm"><?= $ico ?> <?= ucfirst($s['segment']) ?></div>
      <div class="segbar"><div style="width:<?= round($s['total'] / $segMax * 100) ?>%;background:<?= $grad ?>;"></div></div>
      <div style="width:120px;text-align:right;flex-shrink:0;">
        <b><?= (int)$s['live'] ?></b>/<?= (int)$s['total'] ?>
         <?= $s['pend'] ? '<span class="tag pending">' . (int)$s['pend'] . ' ⏳</span>' : '' ?>
      </div>
    </div>
    <?php endforeach; if (!$segs) echo '<p class="muted">No ads yet.</p>'; ?>

    <?php if ($wCats): ?>
    <h2 style="margin-top:22px;">🔧 Top Worker Categories</h2>
    <?php foreach ($wCats as $w): ?>
      <span class="chip"><?= htmlspecialchars($w['category']) ?>: <?= (int)$w['c'] ?></span>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- ============ PENDING MODERATION (fast actions) ============ -->
<?php if ($pendingAds || $pendingWk): ?>
<div class="card">
  <h2>⚡ Quick Moderation</h2>
  <?php if ($pendingAds): ?>
  <table style="margin-bottom:18px;">
    <tr><th>Ad</th><th>By</th><th>Price</th><th>Action</th></tr>
    <?php foreach ($pendingAds as $a): ?>
    <tr>
      <td><a href="listing.php?id=<?= $a['id'] ?>"><?= htmlspecialchars(mb_strimwidth($a['title'], 0, 40, '…')) ?></a></td>
      <td><?= htmlspecialchars($a['uname']) ?></td>
      <td>₹<?= number_format((float)$a['price']) ?></td>
      <td><form method="post" style="display:flex;gap:6px;">
        <input type="hidden" name="lid" value="<?= $a['id'] ?>">
        <button name="action" value="approve" class="btn btn-primary" style="padding:6px 14px;">✔</button>
        <button name="action" value="reject" class="btn btn-outline-dark" style="padding:6px 14px;">✖</button>
      </form></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <?php if ($pendingWk): ?>
  <table>
    <tr><th>Worker</th><th>Category</th><th>Rate</th><th>ID</th><th>Action</th></tr>
    <?php foreach ($pendingWk as $w): ?>
    <tr>
      <td><a href="worker.php?id=<?= $w['id'] ?>"><?= htmlspecialchars($w['uname']) ?></a></td>
      <td><?= htmlspecialchars($w['category']) ?></td>
      <td><?= rateLabel($w['rate_type'], $w['rate']) ?></td>
      <td><?php if ($w['id_proof']): ?><a href="<?= $w['id_proof'] ?>" target="_blank">🪪 View</a><?php else: ?>—<?php endif; ?></td>
      <td><form method="post" style="display:flex;gap:6px;">
        <input type="hidden" name="wid" value="<?= $w['id'] ?>">
        <button name="action" value="approve" class="btn btn-primary" style="padding:6px 14px;">✔</button>
        <button name="action" value="reject" class="btn btn-outline-dark" style="padding:6px 14px;">✖</button>
      </form></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ============ USER SEARCH + RECENT SIGNUPS ============ -->
<div class="card">
  <h2>🔍 Find a User <span class="muted" style="font-size:.8rem;">(support tool)</span></h2>
  <form method="get" style="display:flex;gap:10px;margin-bottom:14px;">
    <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name / email / mobile…"
           style="flex:1;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
    <button class="btn btn-primary">Search</button>
  </form>
  <?php if ($search !== ''): ?>
  <table>
    <tr><th>User</th><th>Email</th><th>Mobile</th><th>Role</th><th>Verified</th><th>Joined</th></tr>
    <?php foreach ($found as $f): ?>
    <tr>
      <td><b><?= htmlspecialchars($f['name']) ?></b> (#<?= $f['id'] ?>)</td>
      <td><?= htmlspecialchars($f['email']) ?></td>
      <td>+91 <?= htmlspecialchars($f['mobile']) ?></td>
      <td><span class="tag pending"><?= $f['role'] ?></span></td>
      <td><?= $f['email_verified'] ? '📧✅' : '📧❌' ?> <?= $f['mobile_verified'] ? '📱✅' : '📱❌' ?></td>
      <td class="muted"><?= date('d M Y', strtotime($f['created_at'])) ?></td>
    </tr>
    <?php endforeach; if (!$found) echo '<tr><td colspan="6">No users matched "' . htmlspecialchars($search) . '".</td></tr>'; ?>
  </table>
  <?php endif; ?>

  <h2 style="margin-top:20px;">🆕 Recent Signups</h2>
  <table>
    <tr><th>User</th><th>Role</th><th>Email</th><th>Mobile</th><th>Verified</th><th>Joined</th></tr>
    <?php foreach ($recentUsers as $f): ?>
    <tr>
      <td><b><?= htmlspecialchars($f['name']) ?></b></td>
      <td><span class="tag pending"><?= $f['role'] ?></span></td>
      <td class="muted"><?= htmlspecialchars($f['email']) ?></td>
      <td class="muted"><?= htmlspecialchars($f['mobile']) ?></td>
      <td><?= $f['email_verified'] ? '📧✅' : '📧❌' ?> <?= $f['mobile_verified'] ? '📱✅' : '📱❌' ?></td>
      <td class="muted"><?= date('d M, h:i A', strtotime($f['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<!-- ============ RECENT ADS + TOP ADS ============ -->
<div class="grid2">
  <div class="card">
    <h2>🕒 Latest Ads</h2>
    <table>
      <tr><th>Ad</th><th>Seller</th><th>Status</th></tr>
      <?php foreach ($recentAds as $a): ?>
      <tr>
        <td><a href="listing.php?id=<?= $a['id'] ?>"><?= htmlspecialchars(mb_strimwidth($a['title'], 0, 32, '…')) ?></a><br>
            <span class="muted"><?= ucfirst($a['segment']) ?> · ₹<?= number_format((float)$a['price']) ?></span></td>
        <td><?= htmlspecialchars($a['uname']) ?></td>
        <td><span class="tag <?= $a['status']==='approved'?'ok':($a['status']==='pending'?'pending':'no') ?>"><?= $a['status'] ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card">
    <h2>🔥 Top 5 Ads (by views)</h2>
    <table>
      <tr><th>#</th><th>Ad</th><th>Views</th></tr>
      <?php foreach ($topAds as $i => $t): ?>
      <tr>
        <td><b><?= $i + 1 ?></b></td>
        <td><a href="listing.php?id=<?= $t['id'] ?>"><?= htmlspecialchars(mb_strimwidth($t['title'], 0, 38, '…')) ?></a><br>
            <span class="muted"><?= ucfirst($t['segment']) ?></span></td>
        <td><b><?= number_format((int)$t['v']) ?></b></td>
      </tr>
      <?php endforeach; if (!$topAds) echo '<tr><td colspan="3">No approved ads yet.</td></tr>'; ?>
    </table>
  </div>
</div>

<!-- ============ PLATFORM HEALTH / GO-LIVE ============ -->
<div class="card">
  <h2>🩺 Platform Health & Go-Live Checklist</h2>
  <ul class="health" style="padding:0;">
    <?php foreach ($health as [$ok, $good, $bad]): ?>
    <li><?= $ok ? '🟢' : '🔴' ?> <b><?= $ok ? $good : $bad ?></b></li>
    <?php endforeach; ?>
  </ul>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
    <a href="cron-log.php" class="btn btn-outline-dark">🧹 Cron</a>
    <a href="email-log.php" class="btn btn-outline-dark">📧 Email Log</a>
    <a href="admin.php" class="btn btn-outline-dark">📋 Moderation</a>
  </div>
</div>

<?php require 'includesfooter.php'; ?>