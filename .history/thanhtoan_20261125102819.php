.coupon-box button{padding:12px 18px;font-size:16px}
.loyal-title{font-size:24px;font-weight:800;padding-top:28px;margin-top:25px;border-top:1px solid #ddd}
.member-box{background:#f1fbf6;border:1px solid #b9dfca;border-radius:8px;padding:14px;color:#087e49;margin-top:12px}
.voucher-list{display:grid;gap:10px;margin:14px 0}
.voucher-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;border:1px dashed #a4cbb5;border-radius:8px}
.voucher-item small{display:block;color:#666;margin-top:5px}.voucher-item button{flex-shrink:0;border:1px solid #15985d;color:#15985d;background:white;padding:8px 12px;border-radius:6px;cursor:pointer}
.notice{background:#fff0f0;color:#a12020;padding:12px;border-radius:6px}.voucher-message{color:#a12020;min-height:20px}.voucher-message.success,.saving{color:#15985d}
.total-row,.grand-total{display:flex;justify-content:space-between;gap:12px;align-items:center;margin:18px 0;font-size:17px}
.grand-total{border-top:1px solid #ddd;padding-top:25px;margin-top:25px;font-size:20px;flex-wrap:wrap}.grand-total strong{font-size:30px}.vnd{color:#999;font-size:15px;margin-right:8px}
@media(max-width:900px){.checkout-page{grid-template-columns:1fr}.checkout-left,.checkout-right{padding:30px 20px}.next-btn{width:100%}}
</style>
</head>
<body>
<div class="checkout-page">
<section class="checkout-left">
    <a href="index.php" class="logo">TT SHOP</a>
    <nav class="breadcrumb"><a href="giohang.php">Giỏ hàng</a><span>›</span><span class="active-step">Thông tin giao hàng</span><span>›</span><span>Phương thức thanh toán</span></nav>
    <h2>Thông tin giao hàng</h2>
    <div class="user-box"><div class="user-icon"><i class="fas fa-user"></i></div><div>
        <p><?= tt_e($fullName) ?> (<?= tt_e($user['email'] ?? '') ?>)</p><p><a href="dangxuat.php">Đăng xuất</a></p>
    </div></div>
    <?php if ($error !== ''): ?><p class="notice" role="alert"><?= tt_e($error) ?></p><?php endif; ?>
    <form id="delivery-form" class="checkout-form" action="thanhtoan.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= tt_e($token) ?>">
        <input type="hidden" name="review_code" id="review-code" value="<?= tt_e($state['code']) ?>">
        <input type="hidden" name="review_total" id="review-total" value="<?= tt_e($state['total']) ?>">
        <input type="text" name="ho_ten" aria-label="Họ và tên" placeholder="Họ và tên" autocomplete="name" value="<?= tt_e($delivery['ho_ten'] ?? '') ?>" required>
        <input type="tel" name="sdt" aria-label="Số điện thoại" placeholder="Số điện thoại" autocomplete="tel" value="<?= tt_e($delivery['sdt'] ?? '') ?>" required>
        <input type="text" name="dia_chi" aria-label="Địa chỉ" placeholder="Địa chỉ cụ thể, số nhà, tên đường..." autocomplete="street-address" value="<?= tt_e($delivery['dia_chi'] ?? '') ?>" required>
        <div class="checkout-action"><a href="giohang.php" class="back-cart">Giỏ hàng</a><button id="next-btn" class="next-btn" type="submit" disabled>Tiếp tục đến phương thức thanh toán</button></div>
    </form>
</section>
<section class="checkout-right">
    <div id="product-list"></div>
    <div class="coupon-box">
        <input id="voucher-code" aria-label="Mã giảm giá" placeholder="Mã giảm giá" maxlength="50" disabled>
        <button id="voucher-apply" type="button" disabled>Sử dụng</button>
        <button id="voucher-remove" type="button" hidden disabled>Gỡ mã</button>
    </div>
    <p id="voucher-message" class="voucher-message" role="status" aria-live="polite"></p>
    <h2 class="loyal-title">Chương trình khách hàng thân thiết</h2>
    <div id="member-info" class="member-box"></div>
    <div id="voucher-list" class="voucher-list"></div>
    <div class="total-row"><span>Tạm tính</span><strong id="subtotal">—</strong></div>
    <div class="total-row saving"><span>Giảm giá thành viên</span><strong id="discount">—</strong></div>
    <div class="total-row"><span>Phí vận chuyển</span><strong id="shipping">—</strong></div>
    <div class="total-row saving"><span>Tiết kiệm</span><strong id="saving">—</strong></div>
    <div class="grand-total"><span>Tổng cộng</span><div><span class="vnd">VND</span><strong id="total">—</strong></div></div>
</section>
</div>
<noscript><p class="notice">Vui lòng bật JavaScript để xem ưu đãi và tiếp tục thanh toán.</p></noscript>
<script>
const $ = id => document.getElementById(id);
const csrf = <?= json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let state = <?= json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
let busy = false;
const money = n => Number(n).toLocaleString('vi-VN') + 'đ';
function element(tag, cls, text) {
    const el = document.createElement(tag); if (cls) el.className = cls;
    if (text !== undefined) el.textContent = text; return el;
}
function message(text, ok = false) {
    $('voucher-message').textContent = text || '';
    $('voucher-message').classList.toggle('success', ok);
}
function controls() {
    const disabled = busy || !state.ok || !state.items?.length;
    ['voucher-code','voucher-apply','voucher-remove','next-btn'].forEach(id => $(id).disabled = disabled);
    $('voucher-list').querySelectorAll('button').forEach(b => b.disabled = disabled || b.dataset.allowed !== '1');
}
function render(s) {
    state = s;
    if (!s.ok) {
        ['subtotal','discount','shipping','saving','total'].forEach(id => $(id).textContent = '—');
        $('voucher-remove').hidden = true;
        message(s.message || 'Không thể tải thông tin đơn hàng.'); controls(); return;
    }
    const products = $('product-list'); products.replaceChildren();
    for (const i of s.items) {
        const row = element('div','product-item');
        const imageBox = element('div','product-img');
        const img = element('img'); img.src = i.img; img.alt = i.name;
        imageBox.append(img, element('span','qty-badge',i.qty));
        row.append(imageBox, element('div','product-name',i.name), element('div','product-price',money(i.price*i.qty)));
        products.append(row);
    }
    $('member-info').textContent = s.member ? 'Hạng '+s.member.tier+' · '+s.member.orders+' đơn đã giao · Chi tiêu '+money(s.member.spend) : '';
    $('voucher-code').value = s.code || ''; $('voucher-remove').hidden = !s.code;
    const list = $('voucher-list'); list.replaceChildren();
    if (!s.vouchers.length) list.textContent = 'Hiện chưa có mã giảm giá.';
    for (const v of s.vouchers) {
        const row = element('div','voucher-item'); const info = element('div');
        info.append(element('strong','',v.code+' — Giảm '+v.rate+'%'+(v.free?' + Freeship':'')),
            element('small','','Hạng '+v.tier+' · Đơn từ '+money(v.min)+' · Tối đa '+money(v.cap)));
        if (!v.ok) info.append(element('small','',v.message));
        const b = element('button','',s.code===v.code?'Đã áp dụng':'Áp dụng'); b.type='button';
        b.dataset.allowed = v.ok && s.code!==v.code ? '1':'0';
        b.addEventListener('click',()=>voucher('apply',v.code)); row.append(info,b); list.append(row);
    }
    $('subtotal').textContent = money(s.subtotal); $('discount').textContent = '−'+money(s.discount);
    $('shipping').textContent = s.shipping===0 ? 'Miễn phí' : money(s.shipping);
    $('saving').textContent = money(s.discount+s.shipping_discount); $('total').textContent = money(s.total);
    $('review-code').value = s.code || ''; $('review-total').value = String(s.total);
    controls();
}
async function post(url,action,extra={}) {
    const res = await fetch(url,{method:'POST',credentials:'same-origin',cache:'no-store',
        body:new URLSearchParams({csrf_token:csrf,action,...extra})});
    let data; try {data=await res.json();} catch(e){throw new Error('Phản hồi máy chủ không hợp lệ.');}
    if (!res.ok && data.ok!==false) throw new Error('Không thể xử lý yêu cầu.');
    return data;
}
async function voucher(action,code='') {
    if (busy || !state.ok) return;
    busy=true; controls(); message('Đang kiểm tra mã giảm giá...');
    try {
        const result=await post('ap_dung_magiamgia.php',action,{code});
        // Đọc lại giỏ hàng và voucher từ máy chủ, không tự tính giá trong JavaScript.
        const s=await post('giohang.php','snapshot'); render(s);
        if(s.ok) message(s.message || result.message, result.ok===true && !s.message);
    } catch(e) {render({...state,ok:false,message:e.message+' Vui lòng tải lại trang.'});}
    finally {busy=false; controls();}
}
$('voucher-apply').addEventListener('click',()=>voucher('apply',$('voucher-code').value));
$('voucher-remove').addEventListener('click',()=>voucher('remove'));
$('voucher-code').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();voucher('apply',e.target.value);}});
$('delivery-form').addEventListener('submit',e=>{if(busy||!state.ok||!state.items?.length)e.preventDefault();else{busy=true;controls();}});
window.addEventListener('pageshow',e=>{if(e.persisted)location.reload();});