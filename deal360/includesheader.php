<?php
require_once __DIR__ . '/../config.php';
 $u = isLoggedIn();
 $pageTitle = $pageTitle ?? 'Deal360.shop | Buy, Sell Houses, Cars, Electronics & Find Workers';
 $pageDesc  = $pageDesc  ?? 'India\'s trusted marketplace for property, vehicles, electronics & local workers. OTP-verified users.';
 $canonical = $canonical ?? null;
 $ogImage   = $ogImage   ?? SITE_URL . '/assets/og-cover.jpg';
 $noindex   = $noindex   ?? false;
 $unreadMsgs = 0;
if ($u) {
    $q = $pdo->prepare("SELECT COUNT(*) c FROM messages m JOIN conversations c2 ON c2.id=m.conversation_id WHERE (c2.buyer_id=? OR c2.seller_id=?) AND m.sender_id<>? AND m.is_read=0");
    $q->execute([$_SESSION['user_id'],$_SESSION['user_id'],$_SESSION['user_id']]);
    $unreadMsgs = (int)$q->fetch()['c'];
}
 $isAdmin = $u && ($_SESSION['user']['role'] ?? '')==='admin';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if ($canonical): ?><link rel="canonical" href="<?= $canonical ?>"><?php endif; ?>
<meta property="og:type" content="website"><meta property="og:site_name" content="Deal360.shop">
<meta property="og:title" content="<?= $pageTitle ?>"><meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
<meta property="og:url" content="<?= $canonical ?: SITE_URL ?>"><meta property="og:image" content="<?= $ogImage ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="stylesheet" href="style.css">
<style>
.app-wrap{max-width:1100px;margin:30px auto;padding:0 16px;}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:26px;box-shadow:0 4px 20px rgba(15,23,42,.06);margin-bottom:22px;}
.card h2{margin-bottom:16px;font-size:1.3rem;}
.f{display:flex;flex-direction:column;gap:12px;max-width:460px;}
.f input,.f select,.f textarea{padding:12px;border:1px solid #cbd5e1;border-radius:8px;font-size:.95rem;}
.f label{font-weight:600;font-size:.88rem;}
.alert{padding:13px 16px;border-radius:8px;margin-bottom:18px;font-size:.92rem;}
.alert.success{background:#dcfce7;color:#166534;}.alert.error{background:#fee2e2;color:#991b1b;}.alert.info{background:#dbeafe;color:#1e40af;}
table{width:100%;border-collapse:collapse;font-size:.9rem;}th,td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;}th{background:#f1f5f9;}
.tag{padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
.tag.ok{background:#dcfce7;color:#166534;}.tag.pending{background:#fef9c3;color:#854d0e;}.tag.no{background:#fee2e2;color:#991b1b;}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;}
fieldset{border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-bottom:12px;}
legend{font-weight:700;font-size:.85rem;padding:0 8px;}
.imggrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;}
.imggrid img{width:100%;height:110px;object-fit:cover;border-radius:8px;}
.adgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;}
.aditem{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;transition:.2s;display:block;color:inherit;}
.aditem:hover{box-shadow:0 8px 24px rgba(0,0,0,.09);transform:translateY(-3px);}
.aditem .thumb{height:150px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:3rem;}
.aditem .thumb img{width:100%;height:100%;object-fit:cover;}
.aditem .body{padding:14px;}
.price{color:#ea580c;font-weight:800;font-size:1.05rem;}
.muted{color:#64748b;font-size:.83rem;}
.topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:22px;}
</style></head><body>
<header class="header"><div class="container nav-wrap">
  <a href="index.php" class="logo">deal<span>360</span><em>.shop</em></a>
  <nav class="nav">
    <a href="listings.php">Browse Ads</a><a href="find-workers.php">Find Workers</a><a href="index.php#plans">Plans</a>
    <?php if($u): ?>
      <a href="post-ad.php">Post Ad</a><a href="dashboard.php">Dashboard</a>
      <a href="analytics.php">📊 Analytics</a>
      <a href="messages.php">💬 Messages<?= $unreadMsgs?' <span class="tag no">'.$unreadMsgs.'</span>':'' ?></a>
      <a href="become-worker.php">Worker Profile</a>
    <?php endif; ?>
    <?php if($isAdmin): ?><a href="admin-dashboard.php">🛡️ Admin</a><?php endif; ?>
  </nav>
  <div class="nav-actions">
    <?php if($u): ?><span class="muted">Hi, <?= htmlspecialchars($_SESSION['user']['name']) ?></span><a href="logout.php" class="btn btn-outline">Logout</a>
    <?php else: ?><a href="login.php" class="btn btn-outline">Login</a><a href="register.php" class="btn btn-primary">Register</a><?php endif; ?>
  </div>
</div></header>
<main class="app-wrap">
<?php if($f=getFlash()): ?><div class="alert <?= $f['type'] ?>"><?= $f['msg'] ?></div><?php endif; ?>