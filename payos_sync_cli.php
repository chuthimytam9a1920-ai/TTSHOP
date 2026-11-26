<?php
// Run every minute via Windows Task Scheduler or cron; cannot be invoked over HTTP.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/upgrade_helpers.php';
if(!payos_ready()){fwrite(STDERR,"payOS not configured\n");exit(1);}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);require __DIR__.'/connect.php';
// Advisory lock prevents overlapping scheduled runs.
if((int)$conn->query("SELECT GET_LOCK('ttshop_payos_sync',0)")->fetch_row()[0]!==1) exit;
$errors=0;
try {
    $rows=$conn->query("SELECT * FROM donhang WHERE phuong_thuc='PAYOS' AND payment_status IN ('PENDING','REVIEW') AND (payment_checked_at IS NULL OR payment_checked_at < NOW()-INTERVAL 45 SECOND) ORDER BY COALESCE(payment_checked_at,'2000-01-01') LIMIT 20")->fetch_all(MYSQLI_ASSOC);
    foreach($rows as $o){try{payos_sync($conn,$o,true);}catch(Throwable $e){$errors++;error_log('TT SHOP sync order '.(int)$o['id'].': '.$e->getMessage());}usleep(200000);}
    $upgrades=$conn->query("SELECT * FROM membership_upgrades WHERE status IN ('PENDING','REVIEW') AND link_id IS NOT NULL AND (checked_at IS NULL OR checked_at < NOW()-INTERVAL 45 SECOND) ORDER BY COALESCE(checked_at,'2000-01-01') LIMIT 20")->fetch_all(MYSQLI_ASSOC);
    foreach($upgrades as $up){try{upgrade_sync($conn,$up);}catch(Throwable $e){$errors++;error_log('TT SHOP upgrade sync '.(int)$up['id'].': '.$e->getMessage());}}
} finally {$conn->query("SELECT RELEASE_LOCK('ttshop_payos_sync')");}
exit($errors?1:0);
