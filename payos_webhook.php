<?php
// Public endpoint; no session, no trust in browser redirects. Only authenticated payOS signals.
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
require_once __DIR__.'/upgrade_helpers.php';
function webhook_reply(int $status): void { http_response_code($status); echo json_encode(['ok'=>$status===200]); exit; }
if(($_SERVER['REQUEST_METHOD']??'')!=='POST') webhook_reply(405);
if(!payos_ready()) webhook_reply(503);
try {
    $raw=file_get_contents('php://input',false,null,0,65537);if(strlen($raw)>65536) webhook_reply(413);
    $p=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$d=$p['data']??null;$sig=$p['signature']??null;
    if(!is_array($d)||!is_string($sig)||!hash_equals(payos_signature($d,payos_config()['checksum_key']),$sig)) webhook_reply(401);
    if(!isset($d['orderCode'])||!is_int($d['orderCode'])) webhook_reply(400);
    if(($p['code']??'')!=='00'||($p['success']??false)!==true||($d['code']??'')!=='00') webhook_reply(200);
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);require __DIR__.'/connect.php';
    $code=$d['orderCode'];$s=$conn->prepare("SELECT * FROM donhang WHERE payos_order_code=? AND phuong_thuc='PAYOS'");$s->bind_param('i',$code);$s->execute();$o=$s->get_result()->fetch_assoc();$s->close();
    if(!$o) {
        if($code>=1000000000 && $code<=9999999999){
            $s=$conn->prepare('SELECT * FROM membership_upgrades WHERE order_code=?');$s->bind_param('i',$code);$s->execute();$up=$s->get_result()->fetch_assoc();$s->close();
            if($up){
                if(($d['currency']??'')!=='VND'||(!empty($up['link_id'])&&!hash_equals($up['link_id'],(string)($d['paymentLinkId']??'')))) webhook_reply(400);
                upgrade_sync($conn,$up);
            }
        }
        webhook_reply(200);
    }
    if(($d['currency']??'')!=='VND'||(!empty($o['payos_link_id'])&&!hash_equals($o['payos_link_id'],(string)($d['paymentLinkId']??'')))) webhook_reply(400);
    payos_sync($conn,$o,true); // authoritative GET handles partial payments and duplicated webhooks.
    webhook_reply(200);
} catch(JsonException $e){webhook_reply(400);}catch(Throwable $e){error_log('TT SHOP payOS webhook: '.$e->getMessage());webhook_reply(503);}
