<?php
// TT SHOP - payOS REST API. No credentials in source control.
function payos_config(): array {
    static $config = null;
    if ($config === null) {
        $file = __DIR__.'/config_payos.php';
        $config = is_file($file) ? require $file : [];
        if (!is_array($config)) $config = [];
    }
    return $config;
}
function payos_ready(): bool {
    $c = payos_config();
    foreach (['client_id','api_key','checksum_key','base_url'] as $k) {
        if (!is_string($c[$k] ?? null) || trim($c[$k]) === '') return false;
    }
    $u = parse_url($c['base_url']);
    return extension_loaded('curl') && PHP_INT_SIZE >= 8 && is_array($u) && isset($u['host'], $u['scheme'])
        && ($u['scheme'] === 'https' || ($u['scheme'] === 'http' && in_array($u['host'], ['localhost','127.0.0.1'], true)))
        && !isset($u['user']) && !isset($u['pass'])
        && !isset($u['query']) && !isset($u['fragment']);
}
function payos_signature(array $data, string $key): string {
    ksort($data, SORT_STRING);
    $parts = [];
    foreach ($data as $k => $v) {
        // Payment requests/webhooks use unescaped flat key=value fields.
        if (is_array($v) || is_object($v)) throw new RuntimeException('Unsupported payment signature field');
        if ($v === null || $v === 'null' || $v === 'undefined') $v = '';
        if (is_bool($v)) $v = $v ? 'true' : 'false';
        $parts[] = $k.'='.$v;
    }
    return hash_hmac('sha256', implode('&', $parts), $key);
}
function payos_api(string $method, string $path, ?array $body = null): array {
    if (!payos_ready()) throw new RuntimeException('payOS not configured or cURL unavailable');
    $c = payos_config();
    $ch = curl_init('https://api-merchant.payos.vn'.$path);
    $opts = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>12, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-client-id: '.$c['client_id'],'x-api-key: '.$c['api_key']]];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_errno($ch); curl_close($ch);
    if ($raw === false || $http < 200 || $http >= 300) throw new RuntimeException('payOS HTTP '.$http.' curl '.$err);
    $res = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (($res['code'] ?? '') !== '00' || !is_array($res['data'] ?? null)) throw new RuntimeException('payOS rejected request (code '.substr((string)($res['code'] ?? ''),0,10).')');
    return $res['data'];
}
function payos_label(array $o): string {
    if (($o['phuong_thuc'] ?? '') !== 'PAYOS') return ($o['phuong_thuc'] ?? 'COD') === 'COD' ? 'COD' : (string)$o['phuong_thuc'];
    return ['PENDING'=>'QR – Chờ thanh toán','PAID'=>'QR – Đã thanh toán','CANCELLED'=>'QR – Đã hủy','EXPIRED'=>'QR – Hết hạn','REVIEW'=>'QR – Cần đối soát'][$o['payment_status'] ?? 'PENDING'] ?? 'QR – Cần đối soát';
}
function payos_escape($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function payos_order(mysqli $db, int $id, ?int $owner = null): ?array {
    $sql = 'SELECT * FROM donhang WHERE id = ?'.($owner !== null ? ' AND user_id = ?' : '');
    $s = $db->prepare($sql);
    if ($owner !== null) $s->bind_param('ii', $id, $owner); else $s->bind_param('i', $id);
    $s->execute(); $r = $s->get_result()->fetch_assoc(); $s->close(); return $r ?: null;
}
function payos_validate_data(array $order, array $d, string $idKey = 'id'): string {
    $linkId = (string)($d[$idKey] ?? '');
    if ((string)($d['orderCode'] ?? '') !== (string)$order['payos_order_code']
        || (string)($d['amount'] ?? '') !== (string)$order['tong_cong']
        || !preg_match('/^[a-zA-Z0-9_-]{10,100}$/D', $linkId)
        || (!empty($order['payos_link_id']) && !hash_equals($order['payos_link_id'], $linkId))) {
        throw new RuntimeException('payOS order/amount/link mismatch');
    }
    return $linkId;
}
function payos_url_valid(string $url): bool {
    $u = parse_url($url);
    return is_array($u) && ($u['scheme'] ?? '') === 'https' && ($u['host'] ?? '') === 'pay.payos.vn'
        && !isset($u['user']) && !isset($u['pass']) && !isset($u['port']);
}
// Only a server-to-server GET determines final payment state; returnUrl is never trusted.
function payos_apply(mysqli $db, int $id, array $d): array {
    $db->begin_transaction();
    try {
        $s = $db->prepare('SELECT * FROM donhang WHERE id=? FOR UPDATE');
        $s->bind_param('i',$id); $s->execute(); $o=$s->get_result()->fetch_assoc(); $s->close();
        if (!$o || $o['phuong_thuc'] !== 'PAYOS') throw new RuntimeException('Invalid QR order');
        $linkId=payos_validate_data($o,$d);
        foreach (['amountPaid','amountRemaining'] as $k) if (!isset($d[$k]) || !is_int($d[$k]) || $d[$k] < 0) throw new RuntimeException('Invalid amount');
        $provider=(string)($d['status'] ?? '');
        if (!in_array($provider,['PENDING','PROCESSING','PAID','CANCELLED','EXPIRED','FAILED','UNDERPAID'],true)) throw new RuntimeException('Unknown payment status');
        $received=$d['amountPaid']; $total=(int)$o['tong_cong'];
        $state=$o['payment_status']; $shipping=$o['trang_thai']; $ref=$o['payment_reference'];
        if (is_array($d['transactions'] ?? null)) foreach ($d['transactions'] as $tx) {
            if (is_array($tx) && is_string($tx['reference'] ?? null)) $ref=substr($tx['reference'],0,255);
        }
        // A stale response may never overwrite a later paid/cancelled response.
        if ($received < (int)$o['payment_amount_received']) { $db->commit(); return $o; }
        if ($state === 'PAID') {
            if ($received === $total) { $db->commit(); return $o; }
            $state='REVIEW';
        } elseif ($provider === 'PAID' && $received === $total && $d['amountRemaining'] === 0) {
            $state=in_array($shipping,['Đã huỷ','Đã hủy'],true) ? 'REVIEW' : 'PAID';
        } elseif ($provider === 'PAID' || $received > $total || ($received > 0 && in_array($state,['CANCELLED','EXPIRED'],true)) || ($received > 0 && in_array($provider,['CANCELLED','EXPIRED'],true))) {
            $state='REVIEW';
        } elseif (in_array($provider,['CANCELLED','EXPIRED'],true) && $received === 0 && $state === 'PENDING') {
            $state=$provider; $shipping='Đã huỷ';
        }
        $s=$db->prepare("UPDATE donhang SET payos_link_id=?, payment_status=?, payment_amount_received=?, payment_reference=?, trang_thai=?, payment_checked_at=NOW(), paid_at=CASE WHEN ?='PAID' THEN COALESCE(paid_at,NOW()) ELSE paid_at END WHERE id=?");
        $s->bind_param('ssisssi',$linkId,$state,$received,$ref,$shipping,$state,$id); $s->execute(); $s->close();
        $db->commit(); return payos_order($db,$id);
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}
function payos_sync(mysqli $db, array $o, bool $force = false): array {
    if ($o['phuong_thuc'] !== 'PAYOS' || !$o['payos_order_code']) return $o;
    if (!$force && $o['payment_checked_at'] && strtotime($o['payment_checked_at']) > time()-10) return $o;
    // Throttle even when provider is down, without holding database locks across network calls.
    $id=(int)$o['id']; $s=$db->prepare('UPDATE donhang SET payment_checked_at=NOW() WHERE id=?'); $s->bind_param('i',$id); $s->execute(); $s->close();
    $d=payos_api('GET','/v2/payment-requests/'.rawurlencode((string)$o['payos_order_code']));
    return payos_apply($db,$id,$d);
}
function payos_create(mysqli $db, array $o): string {
    if ($o['phuong_thuc'] !== 'PAYOS' || $o['payment_status'] !== 'PENDING' || !$o['payos_order_code']) throw new RuntimeException('Order not payable');
    if (payos_url_valid((string)$o['payos_checkout_url'])) return $o['payos_checkout_url'];
    $c=payos_config(); $id=(int)$o['id']; $code=(int)$o['payos_order_code']; $amount=(int)$o['tong_cong'];
    $return=rtrim($c['base_url'] ?? '', '/').'/thanhtoan_qr.php?order_id='.$id;
    $body=['amount'=>$amount,'cancelUrl'=>$return,'description'=>(string)$code,'orderCode'=>$code,'returnUrl'=>$return];
    $body['signature']=payos_signature($body,(string)($c['checksum_key'] ?? ''));
    // Same persistent orderCode for every retry. Never create a second payment request for this order.
    $body['expiredAt']=strtotime($o['created_at'])+1800;
    try {
        if ($body['expiredAt'] <= time()) throw new RuntimeException('Payment creation expired');
        $d=payos_api('POST','/v2/payment-requests',$body);
        if (($d['currency'] ?? '') !== 'VND') throw new RuntimeException('Invalid currency');
        $linkId=payos_validate_data($o,$d,'paymentLinkId'); $url=(string)($d['checkoutUrl'] ?? '');
        if (!payos_url_valid($url)) throw new RuntimeException('Invalid checkout URL');
    } catch (Throwable $first) {
        // Handles a lost POST response or repeated orderCode: retrieve the same existing link.
        $d=payos_api('GET','/v2/payment-requests/'.$code); $linkId=payos_validate_data($o,$d);
        $o=payos_apply($db,$id,$d);
        if ($o['payment_status'] !== 'PENDING') return '';
        $url='https://pay.payos.vn/web/'.$linkId;
    }
    $s=$db->prepare("UPDATE donhang SET payos_link_id=?,payos_checkout_url=? WHERE id=? AND payment_status='PENDING' AND (payos_link_id IS NULL OR payos_link_id=?)");
    $s->bind_param('ssis',$linkId,$url,$id,$linkId); $s->execute(); $s->close();
    $fresh=payos_order($db,$id);
    return $fresh && $fresh['payment_status']==='PENDING' && payos_url_valid((string)$fresh['payos_checkout_url']) ? $fresh['payos_checkout_url'] : '';
}
