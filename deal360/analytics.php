<?php
require_once 'config.php';
requireLogin();
 $uid=$_SESSION['user_id']; refreshFeaturedFlags($pdo);
 $filterAd=(int)($_GET['ad']??0);
 $sql="SELECT l.id,l.title,l.status,l.segment,l.price,l.created_at,l.is_featured,l.is_featured_until,DATEDIFF(NOW(),l.created_at) days_live,
(SELECT COUNT(*) FROM view_logs vl WHERE vl.listing_id=l.id) v_total,
(SELECT COUNT(*) FROM view_logs vl WHERE vl.listing_id=l.id AND vl.viewed_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)) v_7d,
(SELECT COUNT(DISTINCT vl.user_id) FROM view_logs vl WHERE vl.listing_id=l.id) v_unique,
(SELECT COUNT(*) FROM enquiries e WHERE e.listing_id=l.id) enq,
(SELECT COUNT(*) FROM conversations c WHERE c.listing_id=l.id) chats,
(SELECT COUNT(*) FROM featured_orders f WHERE f.listing_id=l.id) boost_count
FROM listings l WHERE l.user_id=?";
 $params=[$uid]; if ($filterAd) { $sql.=" AND l.id=?"; $params[]=$filterAd; }
 $sql.=" ORDER BY l.created_at DESC";
 $st=$pdo->prepare($sql); $st->execute($params); $ads=$st->fetchAll();
 $tViews=$tViews7=$tEnq=$tChats=$tUnique=0;
foreach ($ads as $a) { $tViews+=(int)$a['v_total']; $tViews7+=(int)$a['v_7d']; $tEnq+=(int)$a['enq']; $tChats+=(int)$a['chats']; $tUnique+=(int)$a['v_unique']; }
 $overallCTR=$tViews>0?round($tEnq/$tViews*100,1):0.0;
 $tSql="SELECT DATE(vl.viewed_at) d,COUNT(*) c FROM view_logs vl JOIN listings l ON l.id=vl.listing_id WHERE l.user_id=? AND vl.viewed_at>=DATE_SUB(CURDATE(),INTERVAL 13 DAY)";
 $tP=[$uid]; if ($filterAd) { $tSql.=" AND vl.listing_id=?"; $tP[]=$filterAd; }
 $tSql.=" GROUP BY DATE(vl.viewed_at)";
 $st=$pdo->prepare($tSql); $st->execute($tP);
 $trendMap=[]; foreach ($st->fetchAll() as $r) $trendMap[$r['d']]=(int)$r['c'];
 $days=[]; $maxDay=0;
for ($i=13;$i>=0;$i--) { $d=date('Y-m-d',strtotime("-$i days")); $c=$trendMap[$d]??0;
 $days[]=['label'=>date('d/m',strtotime($d)),'count'=>$c]; $maxDay=max($maxDay,$c); }
 $insights=[]; $best=null;
foreach ($ads as $a) {
    $v=(int)$a['v_total']; $e=(int)$a['enq']; $ctr=$v>0?($e/$v*100):0;
    if ($a['status']==='approved'&&($best===null||$e>$best['enq'])) $best=$a;
    $cb=$a['is_featured']&&strtotime($a['is_featured_until'])>time();
    if ($v>=10&&$ctr>=8&&!$cb) $insights[]=['🔥',"“".htmlspecialchars($a['title'])."” converts at <b>".round($ctr,1)."%</b> — ⚡ <b>Boost it!</b>",$a['id']];
    if ($v>=20&&$e===0) $insights[]=['📸',"“".htmlspecialchars($a['title'])."” got <b>$v views, 0 enquiries</b>. Try better photos/price.",$a['id']];
    if ($a['status']==='approved'&&$v>0&&(int)$a['v_7d']===0) $insights[]=['😴',"“".htmlspecialchars($a['title'])."” had no views in 7 days — buried. ⚡ Boost!",$a['id']];
}
require 'includesheader.php';
?>
<style>
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:22px;}
.stat{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;text-align:center;}
.stat h3{font-size:1.7rem;color:var(--primary);margin-bottom:2px;}.stat p{font-size:.78rem;color:#64748b;margin:0;}
.bars{display:flex;align-items:flex-end;gap:6px;height:190px;padding:24px 4px 26px;border-bottom:2px solid #e2e8f0;}
.bar{flex:1;background:linear-gradient(180deg,#60a5fa,#1d4ed8);border-radius:6px 6px 0 0;position:relative;min-height:4px;}
.bar b{position:absolute;top:-18px;left:50%;transform:translateX(-50%);font-size:.68rem;color:#334155;}
.bar span{position:absolute;bottom:-22px;left:50%;transform:translateX(-50%);font-size:.62rem;color:#94a3b8;white-space:nowrap;}
.insight{display:flex;gap:12px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;margin-bottom:10px;font-size:.9rem;}
.rate-pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;}
.pill-hot{background:#dcfce7;color:#166534;}.pill-mid{background:#fef9c3;color:#854d0e;}.pill-low{background:#fee2e2;color:#991b1b;}.pill-zero{background:#f1f5f9;color:#64748b;}
</style>
<div class="topbar"><h2 style="margin:0;">📊 My Ad Analytics</h2>
<form method="get" style="display:flex;gap:8px;align-items:center;">
<select name="ad" onchange="this.form.submit()"><option value="0">All my ads</option>
<?php foreach ($ads as $a): ?><option value="<?= $a['id'] ?>" <?= $filterAd===(int)$a['id']?'selected':'' ?>><?= htmlspecialchars(mb_strimwidth($a['title'],0,40,'…')) ?></option><?php endforeach; ?></select>
<a href="analytics-export.php<?= $filterAd?'?ad='.$filterAd:'' ?>" class="btn btn-outline-dark">⬇ CSV</a></form></div>
<div class="stat-grid">
<div class="stat"><h3><?= number_format($tViews) ?></h3><p>Total Views</p></div>
<div class="stat"><h3><?= number_format($tViews7) ?></h3><p>Views (7d)</p></div>
<div class="stat"><h3><?= number_format($tUnique) ?></h3><p>Unique Viewers</p></div>
<div class="stat"><h3><?= number_format($tEnq) ?></h3><p>Enquiries</p></div>
<div class="stat"><h3><?= number_format($tChats) ?></h3><p>Chats</p></div>
<div class="stat"><h3><?= $overallCTR ?>%</h3><p>CTR</p></div></div>
<?php if ($insights): ?><div class="card"><h2>💡 Smart Suggestions</h2>
<?php foreach (array_slice($insights,0,6) as $ins): ?><div class="insight"><b><?= $ins[0] ?></b><div><?= $ins[1] ?> <a href="boost-ad.php?listing_id=<?= $ins[2] ?>">Boost →</a></div></div><?php endforeach; ?></div><?php endif; ?>
<div class="card"><h2>📅 Views — Last 14 Days</h2>
<?php if ($maxDay>0): ?><div class="bars"><?php foreach ($days as $d): $h=round($d['count']/$maxDay*100); ?>
<div class="bar" style="height:<?= max(3,$h) ?>%;"><?php if ($d['count']>0): ?><b><?= $d['count'] ?></b><?php endif; ?><span><?= $d['label'] ?></span></div>
<?php endforeach; ?></div><?php else: ?><p class="muted">No views yet.</p><?php endif; ?></div>
<div class="card"><h2>📋 Performance by Ad</h2><table>
<tr><th>Ad</th><th>Status</th><th>Views 7d/total</th><th>Unique</th><th>Enq.</th><th>Chats</th><th>CTR</th></tr>
<?php foreach ($ads as $a): $v=(int)$a['v_total']; $e=(int)$a['enq']; $ctr=$v>0?round($e/$v*100,1):null;
if ($ctr===null) { $pill='pill-zero'; $label='—'; } elseif ($ctr>=8) { $pill='pill-hot'; $label=$ctr.'% 🔥'; } elseif ($ctr>=3) { $pill='pill-mid'; $label=$ctr.'%'; } else { $pill='pill-low'; $label=$ctr.'%'; } ?>
<tr><td><b><a href="listing.php?id=<?= $a['id'] ?>"><?= htmlspecialchars(mb_strimwidth($a['title'],0,38,'…')) ?></a></b></td>
<td><span class="tag <?= $a['status']==='approved'?'ok':($a['status']==='pending'?'pending':'no') ?>"><?= $a['status'] ?></span></td>
<td><b><?= $a['v_7d'] ?></b>/<?= $v ?></td><td><?= $a['v_unique'] ?></td><td><?= $e ?></td><td><?= $a['chats'] ?></td>
<td><span class="rate-pill <?= $pill ?>"><?= $label ?></span></td></tr>
<?php endforeach; if (!$ads) echo '<tr><td colspan="7">No ads yet.</td></tr>'; ?></table></div>
<?php require 'includesfooter.php'; ?>