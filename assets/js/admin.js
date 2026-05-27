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

// Inisialisasi menu hamburger (dengan pengecekan elemen ada)
if (hamburger) {
  hamburger.addEventListener("click", toggleMenu);
}
if (overlay) {
  overlay.addEventListener("click", toggleMenu);
}

// Menutup menu saat link di dalam menu mobile diklik
const mobileLinks = mobileNav ? mobileNav.querySelectorAll("a") : [];
mobileLinks.forEach((link) => {
  link.addEventListener("click", toggleMenu);
});

// Fungsi untuk dropdown filter pada dashboard admin
document.addEventListener("DOMContentLoaded", function () {
  const searchForm = document.getElementById("searchForm");
  const searchInput = document.getElementById("searchInput");
  const clearButton = document.getElementById("clearSearch");
  const filterToggle = document.getElementById("filterToggle");
  const filterMenu = document.getElementById("filterMenu");
  const filterCheckboxes = document.querySelectorAll(".filter-checkbox");
  const applyFilterBtn = document.getElementById("applyFilter");
  const clearFilterBtn = document.getElementById("clearFilter");

  // Variabel untuk debounce (menunda eksekusi)
  let searchTimeout = null;
  let isFilterOpen = false;

  // State filter yang aktif (diambil dari PHP atau default)
  let selectedFilters = window.filterState || {
    image: false,
    video: false,
    multi_word: false,
    single_word: false,
    character: false,
  };

  // Fungsi untuk menampilkan/menyembunyikan tombol clear search
  function updateClearButton() {
    if (clearButton && searchInput) {
      clearButton.style.display =
        searchInput.value.trim() !== "" ? "flex" : "none";
    }
  }

  // Fungsi untuk memperbarui badge jumlah filter aktif
  function updateFilterBadge() {
    if (!filterToggle) return;

    // Hitung jumlah filter yang aktif
    const activeCount = Object.values(selectedFilters).filter(Boolean).length;

    let badgeContainer = filterToggle.querySelector(".filter-badge-container");
    let badge = filterToggle.querySelector(".filter-badge");

    if (activeCount > 0) {
      // Buat badge jika belum ada
      if (!badgeContainer) {
        badgeContainer = document.createElement("span");
        badgeContainer.className = "filter-badge-container";
        const filterArrow = filterToggle.querySelector(".filter-arrow");
        if (filterArrow) {
          filterToggle.insertBefore(badgeContainer, filterArrow);
        }
      }

      if (!badge) {
        badge = document.createElement("span");
        badge.className = "filter-badge";
        badge.id = "filterBadge";
        badgeContainer.appendChild(badge);
      }

      badge.textContent = activeCount;
    } else {
      // Hapus badge jika tidak ada filter aktif
      if (badgeContainer) {
        badgeContainer.remove();
      }
    }
  }

  // Sync checkbox dengan input hidden
  function syncCheckboxesToHiddenInputs() {
    if (!searchForm) return;

    // Ambil semua checkbox filter
    const checkboxes = document.querySelectorAll(".filter-checkbox");

    // Update setiap input hidden
    checkboxes.forEach((checkbox) => {
      const param = checkbox.getAttribute("data-param");
      if (!param) return;

      // Cari input hidden yang sesuai
      let hiddenInput = searchForm.querySelector(`input[name="${param}"]`);

      // Jika tidak ada, buat baru
      if (!hiddenInput) {
        hiddenInput = document.createElement("input");
        hiddenInput.type = "hidden";
        hiddenInput.name = param;
        searchForm.appendChild(hiddenInput);
      }

      // Update nilainya berdasarkan status checkbox
      hiddenInput.value = checkbox.checked ? "1" : "";
    });
  }

  // Inisialisasi awal
  updateClearButton();
  updateFilterBadge();

  // Event listener untuk tombol filter dropdown
  if (filterToggle) {
    filterToggle.addEventListener("click", function (e) {
      e.stopPropagation();
      isFilterOpen = !isFilterOpen;

      if (isFilterOpen) {
        filterToggle.classList.add("active");
        if (filterMenu) filterMenu.classList.add("active");
      } else {
        filterToggle.classList.remove("active");
        if (filterMenu) filterMenu.classList.remove("active");
      }
    });
  }

  // Tutup dropdown filter saat klik di luar area filter
  document.addEventListener("click", function (e) {
    if (isFilterOpen && filterToggle && filterMenu) {
      if (!filterToggle.contains(e.target) && !filterMenu.contains(e.target)) {
        isFilterOpen = false;
        filterToggle.classList.remove("active");
        filterMenu.classList.remove("active");
      }
    }
  });

  // Event listener untuk checkbox filter
  if (filterCheckboxes.length > 0) {
    filterCheckboxes.forEach((checkbox) => {
      checkbox.addEventListener("change", function () {
        const param = this.getAttribute("data-param");
        if (param) {
          selectedFilters[param] = this.checked;
          updateFilterBadge();
        }
      });
    });
  }

  //Event listener untuk tombol terapkan filter
  if (applyFilterBtn) {
    applyFilterBtn.addEventListener("click", function () {
      isFilterOpen = false;
      if (filterToggle) filterToggle.classList.remove("active");
      if (filterMenu) filterMenu.classList.remove("active");

      // Sync checkbox dengan input hidden sebelum submit
      syncCheckboxesToHiddenInputs();
      if (searchForm) searchForm.submit();
    });
  }

  //Event listener untuk tombol reset filter
  if (clearFilterBtn) {
    clearFilterBtn.addEventListener("click", function () {
      // Reset semua checkbox
      filterCheckboxes.forEach((checkbox) => {
        checkbox.checked = false;
        const param = checkbox.getAttribute("data-param");
        if (param) {
          selectedFilters[param] = false;
        }
      });

      updateFilterBadge();

      // Sync checkbox dengan input hidden
      syncCheckboxesToHiddenInputs();

      isFilterOpen = false;
      if (filterToggle) filterToggle.classList.remove("active");
      if (filterMenu) filterMenu.classList.remove("active");

      if (searchForm) searchForm.submit();
    });
  }

  // Event listener untuk tombol clear search
  if (clearButton) {
    clearButton.addEventListener("click", function () {
      if (searchInput) {
        searchInput.value = "";
        updateClearButton();
        if (searchForm) searchForm.submit();
      }
    });
  }

  // Fokus ke input search jika sudah ada nilai
  if (searchInput && searchInput.value) {
    searchInput.focus();
    // Set kursor ke akhir teks
    requestAnimationFrame(() => {
      const length = searchInput.value.length;
      searchInput.setSelectionRange(length, length);
    });
  }

  // Event listener untuk input search
  if (searchInput) {
    // Submit form saat tekan Enter
    searchInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        if (searchForm) searchForm.submit();
      }
    });

    // Auto-submit dengan debounce saat mengetik
    searchInput.addEventListener("input", function () {
      updateClearButton();

      // Clear timeout sebelumnya
      if (searchTimeout) {
        clearTimeout(searchTimeout);
      }

      // Set timeout baru (submit setelah 500ms berhenti mengetik)
      searchTimeout = setTimeout(() => {
        if (searchForm) searchForm.submit();
      }, 500);
    });
  }

  // Fungsi accordion untuk tampilan mobile
  const accordionItems = document.querySelectorAll(".accordion-item");
  if (accordionItems.length > 0) {
    accordionItems.forEach((item) => {
      const header = item.querySelector(".accordion-header");
      if (header) {
        header.addEventListener("click", function () {
          // Tutup accordion lain yang terbuka
          accordionItems.forEach((otherItem) => {
            if (otherItem !== item && otherItem.classList.contains("active")) {
              otherItem.classList.remove("active");
            }
          });
          // Toggle accordion saat ini
          item.classList.toggle("active");
        });
      }
    });
  }
});
