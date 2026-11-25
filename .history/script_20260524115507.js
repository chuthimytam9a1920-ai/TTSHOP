// Khai báo mảng chứa danh bạ đường dẫn các hình ảnh banner quảng cáo
const banners = [
    "images/1.png",
    "images/2.png",
    "images/5.png",
    "images/4.png",
    "images/3.png"
];

// Khởi tạo chỉ số vị trí ảnh hiện tại (bắt đầu từ tấm ảnh đầu tiên ở vị trí 0)
let currentIndex = 0;
// Lấy phần tử hiển thị ảnh banner chính từ giao diện HTML
const mainBanner = document.getElementById("mainBanner");

// ✅ Kiểm tra bảo mật giao diện: CHỈ chạy tính năng slider nếu tìm thấy thẻ hình ảnh này trên trang
if (mainBanner) {
    // Hàm cập nhật đường dẫn ảnh mới lên thẻ HTML để thay đổi hình ảnh hiển thị
    function renderBanner() {
        mainBanner.src = banners[currentIndex];
    }

    // Hàm xử lý tính toán vị trí tiếp theo khi chuyển ảnh (tiến hoặc lùi)
    function moveSlide(direction) {
        // Thuật toán chia lấy dư (%) giúp vòng lặp lặp lại vô hạn (vượt quá ảnh cuối sẽ quay về ảnh đầu và ngược lại)
        currentIndex = (currentIndex + direction + banners.length) % banners.length;
        renderBanner();
    }

    // Chạy lần đầu tiên để hiển thị bức ảnh ở vị trí mặc định (index 0)
    renderBanner();

    // Thiết lập đồng hồ đếm giờ: Cứ sau mỗi 3000ms (3 giây) tự động gọi hàm chuyển sang ảnh kế tiếp (moveSlide(1))
    setInterval(() => {
        moveSlide(1);
    }, 3000);
}

// BỘ LẮNG NGHE SỰ KIỆN CLICK TOÀN TRANG: Phục vụ cho các nút tăng giảm số lượng mua hàng nhanh
document.addEventListener("click", function (e) {
  
  // TRƯỜNG HỢP 1: Nếu người dùng click vào nút tăng số lượng (có chứa class "qty-plus")
  if (e.target.classList.contains("qty-plus")) {
    const box = e.target.closest(".quantity-box"); // Tìm khung bọc chứa bộ số lượng này
    const input = box.querySelector(".qty-input");  // Tìm ô nhập số lượng bên trong khung đó
    input.value = parseInt(input.value) + 1;       // Chuyển giá trị chữ thành số rồi cộng thêm 1 đơn vị
  }

  // TRƯỜNG HỢP 2: Nếu người dùng click vào nút giảm số lượng (có chứa class "qty-minus")
  if (e.target.classList.contains("qty-minus")) {
    const box = e.target.closest(".quantity-box");
    const input = box.querySelector(".qty-input");
    let value = parseInt(input.value);

    // Chống phá vỡ dữ liệu: Chỉ cho phép giảm nếu số lượng hiện tại đang lớn hơn 1 (số lượng tối thiểu là 1)
    if (value > 1) {
      input.value = value - 1;
    }
  }
});