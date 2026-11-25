<?php
session_start();
require __DIR__.'/connect.php';
require __DIR__.'/phananh_helpers.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try {
    $id=pa_id($_GET['id']??null);
    $admin=pa_admin($conn); $user=$admin?null:pa_customer($conn);
    $row=null;
    if ($id && $admin) $row=pa_one($conn,'SELECT anh_mime, anh FROM phan_anh WHERE id=?','i',[$id]);
    elseif ($id && $user) $row=pa_one($conn,'SELECT anh_mime, anh FROM phan_anh WHERE id=? AND user_id=?','ii',[$id,(int)$user['id']]);
    if ($row && $row['anh']!==null && in_array($row['anh_mime'],['image/jpeg','image/png','image/webp'],true)) {
        header('Content-Type: '.$row['anh_mime']); header('Content-Length: '.strlen($row['anh'])); header("Content-Security-Policy: default-src 'none'; sandbox"); echo $row['anh']; exit;
    }
} catch (Throwable $e) { error_log('TT SHOP feedback image: '.$e->getMessage()); }
http_response_code(404); exit;
