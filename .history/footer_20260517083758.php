<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$chatCustomerName = trim(($_SESSION['user_ho'] ?? '') . ' ' . ($_SESSION['user_name'] ?? ''));

if ($chatCustomerName == '') {
    $chatCustomerName = 'Khách';
}
?>

<footer class="footer">
    <div class="footer-container">
        <div class="footer-col footer-brand">
            <h2>TT SHOP</h2>
            <p class="footer-title">TT SHOP - Website thương mại điện tử đồ dùng học tập</p>
            <p>Cửa hàng chuyên cung cấp văn phòng phẩm, dụng cụ học tập, balo, sổ tay và máy tính cầm tay.</p>

            <form action="dangky.php" method="GET" class="email-box">
                <input type="email" name="email" placeholder="Nhập địa chỉ email" required>
                <button type="submit">Đăng ký</button>
            </form>
        </div>

        <div class="footer-col">
            <h3>ĐỊA CHỈ CỬA HÀNG</h3>
            <p><b>Địa chỉ:</b> thôn Châu Xá, xã Việt Yên, tỉnh Hưng Yên</p>
            <p><b>Hotline:</b> 1900 111 222</p>
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

    <div class="chat-input-box">
        <input type="text" id="chatInput" placeholder="Nhập tin nhắn..." onkeypress="handleChatEnter(event)">
        <button type="button" onclick="sendMessage()">
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

<script>
const chatCustomerName = "<?php echo htmlspecialchars($chatCustomerName, ENT_QUOTES); ?>";

function toggleChatBox() {
    const box = document.getElementById("chatBox");
    box.style.display = box.style.display === "flex" ? "none" : "flex";

    if (box.style.display === "flex") {
        loadMessages();
    }
}

function sendMessage() {
    const input = document.getElementById("chatInput");
    const text = input.value.trim();

    if (text === "") return;

    fetch("chat_send.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body:
            "message=" + encodeURIComponent(text) +
            "&sender_type=user" +
            "&customer_name=" + encodeURIComponent(chatCustomerName)
    })
    .then(response => response.text())
    .then(() => {

        let reply = "TT SHOP đã nhận tin nhắn của bạn ❤️. Nhân viên sẽ hỗ trợ bạn sớm nhất.";

        const lower = text.toLowerCase();

        if (lower.includes("giao hàng") || lower.includes("ship")) {
            reply = "Shop giao hàng toàn quốc từ 2 - 5 ngày tuỳ khu vực 🚚";
        } else if (lower.includes("đổi trả") || lower.includes("hoàn tiền")) {
            reply = "Shop hỗ trợ đổi trả trong 7 ngày nếu sản phẩm bị lỗi từ nhà sản xuất 🔄";
        } else if (lower.includes("kiểm tra đơn") || lower.includes("đơn hàng")) {
            reply = "Bạn vào Tài khoản → Danh sách đơn hàng để kiểm tra trạng thái đơn nhé 📦";
        } else if (lower.includes("sản phẩm") || lower.includes("bán gì")) {
            reply = "TT SHOP bán văn phòng phẩm, dụng cụ học tập, balo, sổ tay và máy tính cầm tay 🛍️";
        }

        fetch("chat_send.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body:
                "message=" + encodeURIComponent(reply) +
                "&sender_type=admin" +
                "&customer_name=" + encodeURIComponent(chatCustomerName)
        })
        .then(response => response.text())
        .then(() => {
            input.value = "";
            loadMessages();
        });
    });
}

function loadMessages() {
    fetch("chat_load.php?customer=" + encodeURIComponent(chatCustomerName))
        .then(response => response.text())
        .then(data => {
            const body = document.getElementById("chatBody");

            body.innerHTML = `
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
            ` + data;

            body.scrollTop = body.scrollHeight;
        });
}

function quickQuestion(text) {
    document.getElementById("chatInput").value = text;
    sendMessage();
}

function handleChatEnter(event) {
    if (event.key === "Enter") {
        sendMessage();
    }
}

setInterval(function() {
    const box = document.getElementById("chatBox");

    if (box && box.style.display === "flex") {
        loadMessages();
    }
}, 3000);
</script>