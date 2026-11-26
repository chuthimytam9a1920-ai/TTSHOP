<?php
if(session_status()===PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
require_once __DIR__.'/payment_helpers.php';
if (($_SESSION['is_login']??false)!==true || (int)($_SESSION['user_id']??0)<=0) { http_response_code(401); echo '{}'; exit; }
$uid=(int)$_SESSION['user_id']; session_write_close();
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
if(!$id){http_response_code(404);echo '{}';exit;}
try {
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);require __DIR__.'/connect.php';
    $o=payos_order($conn,$id,$uid);
    if(!$o||$o['phuong_thuc']!=='PAYOS'){http_response_code(404);echo '{}';exit;}
    if(in_array($o['payment_status'],['PENDING','REVIEW'],true)) $o=payos_sync($conn,$o);
    echo json_encode(['status'=>$o['payment_status'],'received'=>(int)$o['payment_amount_received']]);
} catch(Throwable $e){error_log('TT SHOP payOS poll: '.$e->getMessage());http_response_code(503);echo '{}';}
