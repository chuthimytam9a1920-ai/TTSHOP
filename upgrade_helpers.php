<?php
require_once __DIR__.'/membership.php';
require_once __DIR__.'/payment_helpers.php';
function upgrade_prices(): array { return [1=>50000,2=>80000,3=>120000,4=>160000]; }
function upgrade_find(mysqli $db, int $id, ?int $uid=null): ?array {
    $s=$db->prepare('SELECT * FROM membership_upgrades WHERE id=?'.($uid===null?'':' AND user_id=?'));
    if($uid===null) $s->bind_param('i',$id); else $s->bind_param('ii',$id,$uid);
    $s->execute();$r=$s->get_result()->fetch_assoc();$s->close();return $r?:null;
}
function upgrade_active(mysqli $db,int $uid): ?array {
    $s=$db->prepare("SELECT * FROM membership_upgrades WHERE user_id=? AND status IN ('PENDING','REVIEW') ORDER BY id DESC LIMIT 1");
    $s->bind_param('i',$uid);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();return $r?:null;
}
function upgrade_new(mysqli $db,int $uid,int $target,int $expectedRank): array {
    if(!payos_ready()) throw new RuntimeException('payOS unavailable');
    $db->begin_transaction();
    try {
        $s=$db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$s->bind_param('i',$uid);$s->execute();$user=$s->get_result()->fetch_assoc();$s->close();
        if(!$user) throw new RuntimeException('Invalid user');
        if(upgrade_active($db,$uid)) throw new DomainException('Bạn còn giao dịch nâng hạng chưa hoàn tất. Vui lòng kiểm tra giao dịch đó trước.');
        $rank=membership_get($db,$uid)['rank'];
        if($rank!==$expectedRank) throw new DomainException('Hạng hiện tại đã thay đổi. Vui lòng kiểm tra lại giá trước khi thanh toán.');
        $amount=upgrade_prices()[$target-$rank]??null;
        if($target>4||$target<1||$amount===null) throw new DomainException('Hạng muốn nâng không hợp lệ.');
        // Disjoint from the existing product order codes (9 digits).
        $code=random_int(1000000000,9999999999);
        $s=$db->prepare('INSERT INTO membership_upgrades(user_id,from_rank,target_rank,amount,order_code) VALUES(?,?,?,?,?)');
        $s->bind_param('iiiii',$uid,$rank,$target,$amount,$code);$s->execute();$id=(int)$db->insert_id;$s->close();
        $db->commit();return upgrade_find($db,$id,$uid);
    } catch(Throwable $e){$db->rollback();throw $e;}
}
function upgrade_validate(array $o,array $d,string $key='id'): string {
    $id=(string)($d[$key]??'');
    if((string)($d['orderCode']??'')!==(string)$o['order_code'] || (string)($d['amount']??'')!==(string)$o['amount'] || !preg_match('/^[a-zA-Z0-9_-]{10,100}$/D',$id) || (!empty($o['link_id'])&&!hash_equals($o['link_id'],$id))) throw new RuntimeException('Upgrade payment mismatch');
    return $id;
}
function upgrade_sync(mysqli $db,array $o): array {
    if($o['status']==='PAID') return $o;
    $d=payos_api('GET','/v2/payment-requests/'.rawurlencode((string)$o['order_code']));
    $db->begin_transaction();
    try {
        $id=(int)$o['id'];$s=$db->prepare('SELECT * FROM membership_upgrades WHERE id=? FOR UPDATE');$s->bind_param('i',$id);$s->execute();$o=$s->get_result()->fetch_assoc();$s->close();
        $link=upgrade_validate($o,$d);
        $received=$d['amountPaid']??null;$remaining=$d['amountRemaining']??null;$provider=$d['status']??'';
        if(!is_int($received)||!is_int($remaining)||$received<0||$remaining<0||!in_array($provider,['PENDING','PROCESSING','PAID','CANCELLED','EXPIRED','FAILED','UNDERPAID'],true)) throw new RuntimeException('Invalid payment data');
        if($o['status']==='PAID'||$received<(int)$o['amount_received']){$db->commit();return $o;}
        $state=$o['status'];
        if($provider==='PAID'&&$received===(int)$o['amount']&&$remaining===0) $state='PAID';
        elseif($provider==='PAID'||$received>(int)$o['amount']||($received>0&&in_array($provider,['CANCELLED','EXPIRED','FAILED'],true))) $state='REVIEW';
        elseif($received===0&&in_array($provider,['CANCELLED','EXPIRED'],true)&&$state==='PENDING') $state=$provider;
        $s=$db->prepare("UPDATE membership_upgrades SET link_id=?,status=?,amount_received=?,checked_at=NOW(),paid_at=CASE WHEN ?='PAID' THEN COALESCE(paid_at,NOW()) ELSE paid_at END WHERE id=?");
        $s->bind_param('ssisi',$link,$state,$received,$state,$id);$s->execute();$s->close();$db->commit();return upgrade_find($db,$id);
    }catch(Throwable $e){$db->rollback();throw $e;}
}
function upgrade_checkout(mysqli $db,array $o): string {
    if($o['status']!=='PENDING') return '';
    if(payos_url_valid((string)$o['checkout_url'])) return $o['checkout_url'];
    $c=payos_config();$id=(int)$o['id'];$code=(int)$o['order_code'];$amount=(int)$o['amount'];
    $return=rtrim($c['base_url'],'/').'/nang_hang.php?id='.$id;
    $body=['amount'=>$amount,'cancelUrl'=>$return,'description'=>'TT NH '.$code,'orderCode'=>$code,'returnUrl'=>$return];
    $body['signature']=payos_signature($body,$c['checksum_key']);$body['expiredAt']=time()+1800;
    try {
        $d=payos_api('POST','/v2/payment-requests',$body);
        if(($d['currency']??'')!=='VND') throw new RuntimeException('Invalid currency');
        $link=upgrade_validate($o,$d,'paymentLinkId');$url=(string)($d['checkoutUrl']??'');
        if(!payos_url_valid($url)) throw new RuntimeException('Invalid checkout URL');
    }catch(Throwable $e){
        $o=upgrade_sync($db,$o);if($o['status']!=='PENDING') return '';
        $link=$o['link_id'];$url='https://pay.payos.vn/web/'.$link;
    }
    $s=$db->prepare("UPDATE membership_upgrades SET link_id=?,checkout_url=? WHERE id=? AND status='PENDING' AND (link_id IS NULL OR link_id=?)");
    $s->bind_param('ssis',$link,$url,$id,$link);$s->execute();$s->close();$fresh=upgrade_find($db,$id);
    return $fresh['status']==='PENDING'&&payos_url_valid((string)$fresh['checkout_url'])?$fresh['checkout_url']:'';
}
