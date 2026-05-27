// Mengatur fungsionalitas menu hamburger untuk perangkat mobile
const hamburger = document.getElementById("hamburger");
const mobileNav = document.getElementById("mobileNav");
const overlay = document.getElementById("overlay");

// Fungsi untuk membuka dan menutup menu mobile
function toggleMenu() {
  hamburger.classList.toggle("active");
  mobileNav.classList.toggle("active");
  overlay.classList.toggle("active");
  document.body.style.overflow = mobileNav.classList.contains("active")
    ? "hidden"
    : "";
}

// Event listener untuk membuka/menutup menu saat tombol hamburger diklik
hamburger.addEventListener("click", toggleMenu);

// Event listener untuk menutup menu saat overlay diklik
overlay.addEventListener("click", toggleMenu);

// Menutup menu saat link di dalam menu mobile diklik
const mobileLinks = mobileNav.querySelectorAll("a");
mobileLinks.forEach((link) => {
  link.addEventListener("click", toggleMenu);
});
