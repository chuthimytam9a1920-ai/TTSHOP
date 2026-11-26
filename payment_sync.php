<?php
// Authenticated dashboard polling fallback when running on localhost.
require_once __DIR__.'/check_admin.php';
require_once __DIR__.'/../csrf.php';
require_once __DIR__.'/../payment_helpers.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!csrf_check($_POST['csrf_token']??null)){http_response_code(403);echo '{}';exit;}
session_write_close();
if(!payos_ready()){echo '{"changed":false}';exit;}
try {
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);require __DIR__.'/../connect.php';
    if((int)$conn->query("SELECT GET_LOCK('ttshop_payos_sync',0)")->fetch_row()[0]!==1){echo '{"changed":false}';exit;}
    $changed=false;
    try {
        $rows=$conn->query("SELECT * FROM donhang WHERE phuong_thuc='PAYOS' AND payment_status IN ('PENDING','REVIEW') AND (payment_checked_at IS NULL OR payment_checked_at < NOW()-INTERVAL 15 SECOND) ORDER BY COALESCE(payment_checked_at,'2000-01-01') LIMIT 2")->fetch_all(MYSQLI_ASSOC);
        foreach($rows as $o){try{$fresh=payos_sync($conn,$o);if($fresh['payment_status']!==$o['payment_status']||(int)$fresh['payment_amount_received']!==(int)$o['payment_amount_received'])$changed=true;}catch(Throwable $e){error_log('TT SHOP admin sync: '.$e->getMessage());}}
    }finally{$conn->query("SELECT RELEASE_LOCK('ttshop_payos_sync')");}
    echo json_encode(['changed'=>$changed]);
}catch(Throwable $e){error_log('TT SHOP admin poll: '.$e->getMessage());http_response_code(503);echo '{}';}
