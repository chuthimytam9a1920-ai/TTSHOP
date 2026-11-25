<?php
require_once __DIR__ . '/chat_helpers.php';
$ttchatIdentity = ttchat_identity();
$ttchatToken = ttchat_token($ttchatIdentity['key']);
?>

<footer class="footer">
    <div class="footer-container">
        <div class="footer-col footer-brand">
            <h2>TT SHOP</h2>
            <p class="footer-title">TT SHOP - Website thương mại điện tử đồ dùng học tập</p>
            <p>Cửa hàng chuyên cung cấp văn phòng phẩm, dụng cụ học tập, balo, sổ tay và máy tính cầm tay.</p>

            <form action="dangky.php" method="GET" class="email-box" novalidate>
                <input type="email" name="email" placeholder="Nhập địa chỉ email" data-validate="personal-email" data-label="địa chỉ email" required>
                <small class="field-error" aria-live="polite"></small>
                <button type="submit">Đăng ký</button>
            </form>
        </div>

        <div class="footer-col">
            <h3>ĐỊA CHỈ CỬA HÀNG</h3>
            <p><b>Địa chỉ:</b> thôn Thanh Nga, xã Việt Yên, tỉnh Hưng Yên</p>
            <p><b>Hotline:</b> 1900 888 999 </p>
            <p><b>Email:</b> TTSHOP@gmail.com</p>

            <div class="socials">
                <a href="https://www.facebook.com/profile.php?id=61589045422725"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-youtube"></i></a>
            </div>
        </div>

        <div class="footer-col">
            <h3>HỖ TRỢ KHÁCH HÀNG</h3>
            <ul>
                <li><a href="huongdanmuahang.php">Hướng dẫn mua hàng</a></li>
                <li><a href="huongdanthanhtoan.php">Hướng dẫn thanh toán</a></li>
                <li><a href="chinhsachgiaohang.php">Chính sách giao hàng</a></li>
                <li><a href="doitrahoantien.php">Chính sách đổi trả & hoàn tiền</a></li>
                <li><a href="khachhangthanthiet.php">Khách hàng thân thiết</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h3>VỀ TT SHOP</h3>
            <ul>
                <li><a href="gioithieu.php">Giới thiệu</a></li>
                <li><a href="sanphamhocbang.php">Sản phẩm học tập</a></li>
                <li><a href="baomat.php">Chính sách bảo mật</a></li>
                <li><a href="lienhe.php">Thông tin liên hệ</a></li>
                <li><a href="phananh.php">Phản ánh chất lượng sản phẩm</a></li>
            </ul>
        </div>
    </div>

    <p class="footer-bottom">2026 © TT SHOP - Website bán đồ dùng học tập</p>
</footer>

<div class="chat-toggle" onclick="toggleChatBox()">
    <i class="fas fa-comments"></i>
</div>

<div class="chat-box" id="chatBox">
    <div class="chat-header">
        <span>Hỗ trợ khách hàng</span>
        <button type="button" onclick="toggleChatBox()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="chat-body" id="chatBody">
        <div class="bot-message">
            Xin chào 👋<br>
            TT SHOP có thể hỗ trợ gì cho bạn?
        </div>

        <div class="quick-questions">
            <button onclick="quickQuestion('shop có giao hàng không')">🚚 Giao hàng</button>
            <button onclick="quickQuestion('shop có đổi trả không')">🔄 Đổi trả</button>
            <button onclick="quickQuestion('kiểm tra đơn hàng')">📦 Kiểm tra đơn</button>
            <button onclick="quickQuestion('shop bán sản phẩm gì')">🛍️ Sản phẩm</button>
        </div>
    </div>

    <div id="ttchatStatus" role="status" aria-live="polite" style="font-size:12px;color:#9b2525;padding:0 12px;"></div>
    <div class="chat-input-box">
        <input type="text" id="chatInput" maxlength="2000" placeholder="Nhập tin nhắn..." onkeypress="handleChatEnter(event)">
        <button type="button" id="chatSendButton" onclick="sendMessage()">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<style>
.chat-toggle {
    position: fixed;
    right: 22px;
    bottom: 22px;
    width: 62px;
    height: 62px;
    border-radius: 50%;
    background: #12a36d;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    cursor: pointer;
    z-index: 9999;
    box-shadow: 0 8px 20px rgba(0,0,0,.25);
}

.chat-box {
    position: fixed;
    right: 22px;
    bottom: 95px;
    width: 340px;
    height: 500px;
    background: white;
    border-radius: 18px;
    overflow: hidden;
    display: none;
    flex-direction: column;
    z-index: 9999;
    box-shadow: 0 10px 28px rgba(0,0,0,.22);
}

.chat-header {
    background: #12a36d;
    color: white;
    padding: 16px;
    font-weight: bold;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-header button {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
}

.chat-body {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    background: #fff;
}

.bot-message,
.user-message {
    max-width: 80%;
    padding: 12px 14px;
    border-radius: 16px;
    margin-bottom: 12px;
    line-height: 1.5;
    font-size: 15px;
}

.bot-message {
    background: #f1f1f1;
    color: #333;
}

.user-message {
    background: #12a36d;
    color: white;
    margin-left: auto;
}

.quick-questions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 14px 0;
}

.quick-questions button {
    border: none;
    background: #f3f3f3;
    padding: 10px 14px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 13px;
}

.quick-questions button:hover {
    background: #12a36d;
    color: white;
}

.chat-input-box {
    display: flex;
    border-top: 1px solid #eee;
    padding: 12px;
    gap: 10px;
}

.chat-input-box input {
    flex: 1;
    border: 1px solid #ddd;
    border-radius: 999px;
    padding: 12px 16px;
    outline: none;
}

.chat-input-box button {
    width: 46px;
    height: 46px;
    border: none;
    border-radius: 50%;
    background: #12a36d;
    color: white;
    cursor: pointer;
    font-size: 18px;
}
</style>

<script src="validate.js"></script>
<script>
const ttchatToken = <?php echo json_encode($ttchatToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const ttchatBody = document.getElementById('chatBody');
const ttchatInitial = ttchatBody.innerHTML; // markup co dinh tu template
let ttchatSending = false, ttchatVersion = 0, ttchatLastMessages = '', ttchatExpired = false;
function ttchatStatus(message) { document.getElementById('ttchatStatus').textContent = message; }
function toggleChatBox() {
    const box = document.getElementById('chatBox');
    box.style.display = box.style.display === 'flex' ? 'none' : 'flex';
    if (box.style.display === 'flex') loadMessages();
}
async function ttchatResponse(response) {
    if (response.status === 403) {
        ttchatExpired = true;
        ttchatBody.innerHTML = ttchatInitial;
        document.getElementById('chatSendButton').disabled = true;
        throw new Error('Phiên chat đã thay đổi. Vui lòng tải lại trang.');
    }
    let data;
    try { data = await response.json(); } catch (_) { throw new Error('Hệ thống chat tạm thời không khả dụng.'); }
    if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể xử lý yêu cầu.');
    return data;
}
async function sendMessage() {
    const input = document.getElementById('chatInput');
    const text = input.value.trim();
    if (!text || ttchatSending || ttchatExpired) return;
    ttchatSending = true;
    document.getElementById('chatSendButton').disabled = true;
    try {
        await ttchatResponse(await fetch('chat_send.php', {
            method: 'POST', credentials: 'same-origin', cache: 'no-store',
            body: new URLSearchParams({message:text, csrf_token:ttchatToken})
        }));
        if (input.value.trim() === text) input.value = '';
        ttchatStatus('');
        await loadMessages();
    } catch (error) { ttchatStatus(error.message || 'Không thể gửi tin nhắn.'); }
    finally { ttchatSending = false; document.getElementById('chatSendButton').disabled = ttchatExpired; }
}
async function loadMessages() {
    if (ttchatExpired) return;
    const version = ++ttchatVersion;
    try {
        const data = await ttchatResponse(await fetch('chat_load.php', {
            credentials: 'same-origin', cache: 'no-store', headers: {'X-Chat-CSRF': ttchatToken}
        }));
        if (version !== ttchatVersion || ttchatExpired) return;
        const signature = JSON.stringify(data.messages);
        if (signature === ttchatLastMessages) return;
        const atBottom = ttchatBody.scrollHeight - ttchatBody.scrollTop - ttchatBody.clientHeight < 60;
        const first = ttchatLastMessages === '';
        ttchatBody.innerHTML = ttchatInitial;
        for (const msg of data.messages) {
            const bubble = document.createElement('div');
            bubble.className = msg.sender_type === 'user' ? 'user-message' : 'bot-message';
            bubble.style.whiteSpace = 'pre-wrap';
            bubble.style.overflowWrap = 'anywhere';
            if (msg.sender_type !== 'user') {
                const label = document.createElement('strong');
                label.textContent = msg.sender_type === 'bot' ? 'Trợ lý tự động' : 'Nhân viên TT SHOP';
                label.style.display = 'block'; label.style.fontSize = '12px';
                bubble.appendChild(label);
            }
            bubble.appendChild(document.createTextNode(msg.message));
            ttchatBody.appendChild(bubble);
        }
        ttchatLastMessages = signature;
        if (first || atBottom || ttchatSending) ttchatBody.scrollTop = ttchatBody.scrollHeight;
    } catch (error) { if (version === ttchatVersion) ttchatStatus(error.message || 'Không thể tải tin nhắn.'); }
}
function quickQuestion(text) {
    if (ttchatSending || ttchatExpired) return;
    document.getElementById('chatInput').value = text;
    sendMessage();
}
function handleChatEnter(event) {
    if (event.key === 'Enter') { event.preventDefault(); sendMessage(); }
}
setInterval(function() {
    if (!ttchatSending && !ttchatExpired && document.getElementById('chatBox').style.display === 'flex') loadMessages();
}, 3000);
</script>
