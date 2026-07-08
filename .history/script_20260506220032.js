const banners = [
    "images/1.png",
    "images/2.png",
    "images/5.png",
    "images/4.png",
    "images/3.png"
];

let currentIndex = 0;
const mainBanner = document.getElementById("mainBanner");

// ✅ CHỈ chạy khi có banner
if (mainBanner) {
    function renderBanner() {
        mainBanner.src = banners[currentIndex];
    }

    function moveSlide(direction) {
        currentIndex = (currentIndex + direction + banners.length) % banners.length;
        renderBanner();
    }

    renderBanner();

    setInterval(() => {
        moveSlide(1);
    }, 3000);
}
document.addEventListener("click", function (e) {
  if (e.target.classList.contains("qty-plus")) {
    const box = e.target.closest(".quantity-box");
    const input = box.querySelector(".qty-input");
    input.value = parseInt(input.value) + 1;
  }

  if (e.target.classList.contains("qty-minus")) {
    const box = e.target.closest(".quantity-box");
    const input = box.querySelector(".qty-input");
    let value = parseInt(input.value);

    if (value > 1) {
      input.value = value - 1;
    }
  }
});
