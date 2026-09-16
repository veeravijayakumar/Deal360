<?php
require_once 'config.php';
requireLogin();
 $uid=$_SESSION['user_id']; $filterAd=(int)($_GET['ad']??0);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=deal360-analytics-'.date('Y-m-d').'.csv');
 $out=fopen('php://output','w'); fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($out,['Ad ID','Title','Segment','Status','Posted','Views 7d','Views Total','Unique','Enquiries','Chats','CTR %']);
 $sql="SELECT l.*,DATEDIFF(NOW(),l.created_at) days_live,
(SELECT COUNT(*) FROM view_logs vl WHERE vl.listing_id=l.id) v_total,
(SELECT COUNT(*) FROM view_logs vl WHERE vl.listing_id=l.id AND vl.viewed_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)) v_7d,
(SELECT COUNT(DISTINCT vl.user_id) FROM view_logs vl WHERE vl.listing_id=l.id) v_unique,
(SELECT COUNT(*) FROM enquiries e WHERE e.listing_id=l.id) enq,
(SELECT COUNT(*) FROM conversations c WHERE c.listing_id=l.id) chats
FROM listings l WHERE l.user_id=?";
 $p=[$uid]; if ($filterAd) { $sql.=" AND l.id=?"; $p[]=$filterAd; }
 $st=$pdo->prepare($sql); $st->execute($p);
foreach ($st->fetchAll() as $a) fputcsv($out,[$a['id'],$a['title'],$a['segment'],$a['status'],$a['created_at'],$a['v_7d'],$a['v_total'],$a['v_unique'],$a['enq'],$a['chats'],$a['v_total']>0?round($a['enq']/$a['v_total']*100,1):'']);
fclose($out);