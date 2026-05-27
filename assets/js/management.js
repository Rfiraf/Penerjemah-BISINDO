// management.js - JavaScript untuk halaman management admin

// Tunggu sampai halaman selesai dimuat sebelum menjalankan script
document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM Loaded - Initializing management page");

  // 1. MENGATUR MENU HAMBURGER UNTUK PERANGKAT MOBILE
  const hamburger = document.getElementById("hamburger");
  const mobileNav = document.getElementById("mobileNav");
  const overlay = document.getElementById("overlay");

  if (hamburger && mobileNav && overlay) {
    function toggleMenu() {
      hamburger.classList.toggle("active");
      mobileNav.classList.toggle("active");
      overlay.classList.toggle("active");
      document.body.style.overflow = mobileNav.classList.contains("active")
        ? "hidden"
        : "auto";
    }

    hamburger.addEventListener("click", toggleMenu);
    overlay.addEventListener("click", toggleMenu);

    const mobileLinks = mobileNav.querySelectorAll("a");
    mobileLinks.forEach((link) => {
      link.addEventListener("click", toggleMenu);
    });
  }

  // 2. DROPDOWN UNTUK FORM BUAT ADMIN BARU
  const createAdminToggle = document.getElementById("createAdminToggle");
  const createAdminContent = document.getElementById("createAdminContent");

  if (createAdminToggle && createAdminContent) {
    createAdminToggle.addEventListener("click", function (e) {
      e.stopPropagation();
      this.classList.toggle("active");
      createAdminContent.classList.toggle("active");
    });
  }

  // Tutup dropdown saat klik di luar area dropdown
  document.addEventListener("click", function (e) {
    if (
      !e.target.closest(".management-form-dropdown-toggle") &&
      !e.target.closest(".management-form-dropdown-content")
    ) {
      if (
        createAdminToggle &&
        createAdminContent.classList.contains("active")
      ) {
        createAdminToggle.classList.remove("active");
        createAdminContent.classList.remove("active");
      }
    }
  });

  // 3. INISIALISASI DROPDOWN KUSTOM UNTUK PILIHAN ROLE
  function setupCustomDropdowns() {
    const dropdowns = document.querySelectorAll(".custom-select");

    dropdowns.forEach((dropdown) => {
      const trigger = dropdown.querySelector(".custom-select-trigger");
      const options = dropdown.querySelector(".custom-options");
      const optionItems = dropdown.querySelectorAll(".custom-option");

      if (!trigger || !options) return;

      // Cari select tersembunyi dalam form yang sama
      const hiddenSelect = dropdown
        .closest(".form-group")
        ?.querySelector("select");

      // Toggle dropdown saat diklik
      trigger.addEventListener("click", function (e) {
        e.stopPropagation();
        e.preventDefault();

        const isActive = options.classList.contains("active");

        // Tutup semua dropdown lain yang terbuka
        document.querySelectorAll(".custom-options").forEach((opt) => {
          if (opt !== options) {
            opt.classList.remove("active");
            opt.previousElementSibling?.classList.remove("active");
          }
        });

        // Buka/tutup dropdown saat ini
        if (!isActive) {
          options.classList.add("active");
          trigger.classList.add("active");
        } else {
          options.classList.remove("active");
          trigger.classList.remove("active");
        }
      });

      // Pilihan opsi dalam dropdown
      optionItems.forEach((option) => {
        option.addEventListener("click", function (e) {
          e.stopPropagation();
          e.preventDefault();

          const value = this.getAttribute("data-value");
          const text = this.textContent;

          // Update teks yang ditampilkan di dropdown
          const textSpan = trigger.querySelector("span:first-child");
          if (textSpan) {
            textSpan.textContent = text;
          }

          // Update nilai select tersembunyi
          if (hiddenSelect) {
            hiddenSelect.value = value;
          }

          // Update status terpilih
          optionItems.forEach((opt) => opt.classList.remove("selected"));
          this.classList.add("selected");

          // Tutup dropdown setelah memilih
          options.classList.remove("active");
          trigger.classList.remove("active");
        });
      });
    });

    // Tutup dropdown saat klik di luar
    document.addEventListener("click", function (e) {
      if (!e.target.closest(".custom-select")) {
        document.querySelectorAll(".custom-options").forEach((options) => {
          options.classList.remove("active");
        });
        document
          .querySelectorAll(".custom-select-trigger")
          .forEach((trigger) => {
            trigger.classList.remove("active");
          });
      }
    });
  }

  // 4. FUNGSI MODAL UNTUK MENGEDIT DATA ADMIN

  // Menampilkan modal edit admin
  window.showEditModal = function (
    adminId,
    username,
    role,
    isSelfEdit = false
  ) {
    const modal = document.getElementById("editModal");
    const modalTitle = document.getElementById("editModalTitle");
    const adminIdInput = document.getElementById("editAdminId");
    const usernameInput = document.getElementById("editUsername");
    const roleFieldContainer = document.getElementById("roleFieldContainer");

    if (!modal || !adminIdInput || !usernameInput || !roleFieldContainer) {
      console.error("Modal elements missing!");
      return;
    }

    // Set nilai form dari data admin
    adminIdInput.value = adminId;
    usernameInput.value = username;

    // Set judul modal sesuai konteks
    modalTitle.textContent = isSelfEdit ? "Edit Profil Anda" : "Edit Admin";

    // Tampilkan/sembunyikan field role berdasarkan edit akun sendiri
    if (isSelfEdit) {
      roleFieldContainer.style.display = "none";
    } else {
      roleFieldContainer.style.display = "block";

      // Update dropdown role
      const customRoleText = document.getElementById("customRoleText");
      if (customRoleText) {
        customRoleText.textContent =
          role === "superadmin" ? "Superadmin" : "Admin";
      }

      // Update nilai select tersembunyi
      const editRoleSelect = document.getElementById("editRole");
      if (editRoleSelect) {
        editRoleSelect.value = role;
      }

      // Update opsi terpilih di dropdown kustom
      const customRoleSelect = document.getElementById("customRoleSelect");
      if (customRoleSelect) {
        const options = customRoleSelect.querySelectorAll(".custom-option");
        options.forEach((opt) => {
          opt.classList.remove("selected");
          if (opt.getAttribute("data-value") === role) {
            opt.classList.add("selected");
          }
        });
      }
    }

    // Reset field password
    document.getElementById("editPassword").value = "";
    document.getElementById("editConfirmPassword").value = "";

    // Tutup dropdown yang mungkin terbuka
    document.querySelectorAll(".custom-options").forEach((opt) => {
      opt.classList.remove("active");
    });

    document.querySelectorAll(".custom-select-trigger").forEach((trigger) => {
      trigger.classList.remove("active");
    });

    // Tampilkan modal
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";

    // Fokus ke field username
    setTimeout(() => {
      usernameInput.focus();
    }, 100);
  };

  // Menutup modal edit admin
  window.closeEditModal = function () {
    const modal = document.getElementById("editModal");
    if (modal) {
      modal.style.display = "none";
      document.body.style.overflow = "auto";

      // Tutup semua dropdown
      document.querySelectorAll(".custom-options").forEach((options) => {
        options.classList.remove("active");
      });

      document.querySelectorAll(".custom-select-trigger").forEach((trigger) => {
        trigger.classList.remove("active");
      });
    }
  };

  // Event listener untuk modal
  const editModal = document.getElementById("editModal");
  if (editModal) {
    editModal.addEventListener("click", function (e) {
      if (e.target === this) closeEditModal();
    });
  }

  const modalCloseBtn = document.querySelector(".management-modal-close");
  if (modalCloseBtn) {
    modalCloseBtn.addEventListener("click", closeEditModal);
  }

  // Tombol Escape untuk menutup modal
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && editModal && editModal.style.display === "flex") {
      closeEditModal();
    }
  });

  // 5. VALIDASI FORM EDIT ADMIN
  const editAdminForm = document.getElementById("editAdminForm");
  if (editAdminForm) {
    editAdminForm.addEventListener("submit", function (e) {
      const adminId = parseInt(document.getElementById("editAdminId").value);

      // Ambil ID admin saat ini dari atribut data
      const editModalEl = document.getElementById("editModal");
      let currentAdminId = 0;

      if (editModalEl && editModalEl.dataset.currentAdminId) {
        currentAdminId = parseInt(editModalEl.dataset.currentAdminId);
      }

      const isSelfEdit = adminId === currentAdminId;
      const username = document.getElementById("editUsername").value.trim();
      const password = document.getElementById("editPassword").value;
      const confirmPassword = document.getElementById(
        "editConfirmPassword"
      ).value;

      // Validasi username wajib diisi
      if (!username) {
        e.preventDefault();
        alert("Username harus diisi");
        return;
      }

      // Validasi password (jika diisi)
      if (password || confirmPassword) {
        if (password.length < 6 && password !== "") {
          e.preventDefault();
          alert("Password minimal 6 karakter");
          return;
        }

        if (password !== confirmPassword) {
          e.preventDefault();
          alert("Password baru dan konfirmasi tidak cocok");
          return;
        }
      }

      // Konfirmasi sebelum submit
      const message = isSelfEdit
        ? "Yakin ingin mengubah data Anda?"
        : "Yakin ingin mengubah data admin ini?";

      if (!confirm(message)) {
        e.preventDefault();
        return;
      }
    });
  }

  // 6. ACCORDION UNTUK TAMPILAN MOBILE
  const accordionItems = document.querySelectorAll(
    ".management-accordion-item"
  );
  accordionItems.forEach((item) => {
    const header = item.querySelector(".management-accordion-header");
    const content = item.querySelector(".management-accordion-content");

    if (header && content) {
      header.addEventListener("click", () => {
        accordionItems.forEach((otherItem) => {
          if (otherItem !== item && otherItem.classList.contains("active")) {
            otherItem.classList.remove("active");
            const otherContent = otherItem.querySelector(
              ".management-accordion-content"
            );
            if (otherContent) otherContent.style.maxHeight = "0";
          }
        });

        item.classList.toggle("active");
        if (item.classList.contains("active")) {
          content.style.maxHeight = content.scrollHeight + "px";
        } else {
          content.style.maxHeight = "0";
        }
      });
    }
  });

  // 7. INISIALISASI AWAL SETELAH HALAMAN DIMUAT
  // Setup dropdown kustom setelah halaman selesai dimuat
  setTimeout(() => {
    setupCustomDropdowns();
    console.log("Management page initialized");
  }, 100);
});
