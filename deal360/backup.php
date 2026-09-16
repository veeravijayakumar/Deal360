<?php
/* Daily: 0 2 * * * php /path/backup.php  (or ?key=CRON_SECRET_KEY) */
require_once __DIR__.'/config.php';
if (php_sapi_name()!=='cli'&&($_GET['key']??'')!==CRON_SECRET_KEY) { http_response_code(403); exit('Forbidden'); }
if (!is_dir(__DIR__.'/backups')) mkdir(__DIR__.'/backups',0770,true);
 $sql="-- Deal360 backup ".date('c')."\nSET FOREIGN_KEY_CHECKS=0;\n";
foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $sql.="\nDROP TABLE IF EXISTS `$t`;\n".$pdo->query("SHOW CREATE TABLE `$t`")->fetch()['Create Table'].";\n";
    $rows=$pdo->query("SELECT * FROM `$t`");
    while ($r=$rows->fetch(PDO::FETCH_ASSOC)) {
        $vals=array_map(fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),array_values($r));
        $sql.="INSERT INTO `$t` (`".implode('`,`',array_keys($r))."`) VALUES (".implode(',',$vals).");\n"; } }
file_put_contents(__DIR__.'/backups/deal360-'.date('Y-m-d-His').'.sql.gz',gzencode($sql."SET FOREIGN_KEY_CHECKS=1;\n",9));
foreach (glob(__DIR__.'/backups/deal360-*.sql.gz') as $f) if (filemtime($f)<time()-7*86400) unlink($f);
echo "Backup complete: ".date('Y-m-d');