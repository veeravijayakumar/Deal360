<?php
require_once 'config.php';
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
function u(string $loc,?string $last=null,string $f='weekly',string $p='0.7'): void {
    echo "  <url><loc>".htmlspecialchars($loc)."</loc>".($last?"<lastmod>".date('Y-m-d',strtotime($last))."</lastmod>":"")."<changefreq>$f</changefreq><priority>$p</priority></url>\n"; }
u(SITE_URL.'/',null,'daily','1.0'); u(SITE_URL.'/listings.php',null,'hourly','0.9'); u(SITE_URL.'/find-workers.php',null,'daily','0.8');
foreach ($pdo->query("SELECT id,title,created_at FROM listings WHERE status='approved' ORDER BY created_at DESC LIMIT 5000")->fetchAll() as $a)
    u(SITE_URL.'/ad/'.$a['id'].'/'.slugify($a['title']),$a['created_at'],'daily','0.7');
foreach ($pdo->query("SELECT w.id FROM workers w WHERE w.status='approved' LIMIT 2000")->fetchAll() as $w)
    u(SITE_URL.'/worker/'.$w['id'],null,'weekly','0.6');
echo '</urlset>';