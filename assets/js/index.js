// File: assets/js/index.js

// Fungsi untuk menu mobile navigation
const hamburger = document.getElementById("hamburger");
const mobileNav = document.getElementById("mobileNav");
const overlay = document.getElementById("overlay");

function toggleMenu() {
  if (hamburger) hamburger.classList.toggle("active");
  if (mobileNav) mobileNav.classList.toggle("active");
  if (overlay) overlay.classList.toggle("active");
  document.body.style.overflow = mobileNav?.classList.contains("active")
    ? "hidden"
    : "";
}

if (hamburger) hamburger.addEventListener("click", toggleMenu);
if (overlay) overlay.addEventListener("click", toggleMenu);

const mobileLinks = mobileNav ? mobileNav.querySelectorAll("a") : [];
mobileLinks.forEach((link) => {
  link.addEventListener("click", toggleMenu);
});

// Fungsi untuk input teks (enter untuk submit)
const textInput = document.getElementById("textInput");
if (textInput) {
  textInput.addEventListener("keydown", function (event) {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      document.getElementById("translateForm").submit();
    }
  });

  if (!textInput.value) {
    setTimeout(() => {
      textInput.focus();
    }, 300);
  }
}

// Deteksi apakah aplikasi berjalan di localhost atau hosting
function detectLocalhost() {
  const hostname = window.location.hostname;
  const href = window.location.href;

  if (
    hostname === "localhost" ||
    hostname === "127.0.0.1" ||
    hostname === "::1" ||
    hostname === "" ||
    hostname === "null" ||
    hostname === "undefined"
  ) {
    return true;
  }

  if (
    href.includes("localhost") ||
    href.includes("127.0.0.1") ||
    href.includes("localhost/bisindo")
  ) {
    return true;
  }

  if (window.location.protocol === "file:") {
    return true;
  }

  return false;
}

const IS_LOCALHOST = detectLocalhost();

// Sistem Video Utama - Mengelola buffer dan playback video
let videoSystem = {
  MAX_PLAYING: 12, // Maksimal video yang diputar bersamaan
  IS_LOCALHOST: IS_LOCALHOST, // Status localhost

  allVideos: [], // Daftar semua video
  currentBufferIndex: 0, // Index video yang sedang di-buffer
  isBuffering: false, // Status buffering
  readyVideos: 0, // Jumlah video yang siap diputar
  playingVideos: new Set(), // Set video yang sedang diputar
  observer: null, // Intersection Observer
  checkInterval: null, // Interval untuk update playback

  recentlyPausedVideos: new Map(), // Video yang baru saja dipause (protection)
  PAUSE_PROTECTION_TIME: 2000, // Waktu protection setelah pause

  viewportVideos: new Set(), // Video yang ada di viewport
  viewportUpdateTimeout: null, // Timeout untuk update viewport

  // Fungsi inisialisasi sistem video
  init: function () {
    const videoElements = document.querySelectorAll(
      "video.translator-result-media"
    );
    if (videoElements.length === 0) return;

    // Setup semua video dengan background hitam
    this.allVideos = Array.from(videoElements).map((video, index) => {
      // Reset atribut video
      video.removeAttribute("poster");
      video.removeAttribute("autoplay");
      video.pause();
      video.muted = true;
      video.loop = true;
      video.playsInline = true;
      video.preload = "auto";

      // Styling video hitam
      video.style.backgroundColor = "#000000";
      video.style.width = "100%";
      video.style.height = "100%";
      video.style.objectFit = "cover";

      return {
        element: video,
        index: index,
        src: video.getAttribute("data-src") || video.src,
        isReady: false, // Status ready untuk diputar
        isLoading: false, // Status loading
        hasError: false, // Status error
        attempts: 0, // Jumlah percobaan buffer
        bufferStartTime: 0, // Waktu mulai buffer
        bufferProgress: 0, // Persentase buffer
        bufferInterval: null, // Interval untuk check buffer
        hasBeenPlayed: false, // Status pernah diputar
        thumbnailAdded: false, // Status thumbnail
        isVisible: false, // Status terlihat di viewport
        lastPlayTime: 0, // Waktu terakhir diputar
      };
    });

    // Setup controller playback
    this.setupPlaybackController();

    // Mulai buffer sequential
    setTimeout(() => {
      this.startSequentialBuffer();
    }, 500);
  },

  // Menampilkan loading indicator pada video
  showLoadingIndicator: function (video) {
    const container = video.parentElement;
    this.hideLoadingIndicator(video);

    video.style.backgroundColor = "#000000";
    video.style.opacity = "1";
    container.style.position = "relative";
    container.style.backgroundColor = "#000000";

    const overlay = document.createElement("div");
    overlay.className = "video-loading-overlay";
    overlay.style.position = "absolute";
    overlay.style.top = "0";
    overlay.style.left = "0";
    overlay.style.width = "100%";
    overlay.style.height = "100%";
    overlay.style.backgroundColor = "#000000";
    overlay.style.zIndex = "5";

    const loadingDiv = document.createElement("div");
    loadingDiv.className = "video-loading";
    loadingDiv.style.position = "absolute";
    loadingDiv.style.top = "50%";
    loadingDiv.style.left = "50%";
    loadingDiv.style.transform = "translate(-50%, -50%)";
    loadingDiv.style.zIndex = "10";
    loadingDiv.style.textAlign = "center";

    const spinner = document.createElement("div");
    spinner.className = "loading-spinner";
    spinner.style.width = "40px";
    spinner.style.height = "40px";
    spinner.style.border = "3px solid rgba(255, 255, 255, 0.3)";
    spinner.style.borderTop = "3px solid #2196f3";
    spinner.style.borderRadius = "50%";
    spinner.style.margin = "0 auto";
    spinner.style.animation = "spin 1s linear infinite";

    loadingDiv.appendChild(spinner);

    // Inject style untuk animasi spinner
    if (!document.querySelector("#spin-animation")) {
      const style = document.createElement("style");
      style.id = "spin-animation";
      style.textContent =
        "@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }";
      document.head.appendChild(style);
    }

    container.appendChild(overlay);
    container.appendChild(loadingDiv);
  },

  // Menyembunyikan loading indicator
  hideLoadingIndicator: function (video) {
    const container = video.parentElement;

    const overlay = container.querySelector(".video-loading-overlay");
    if (overlay) overlay.remove();

    const loading = container.querySelector(".video-loading");
    if (loading) loading.remove();

    video.style.opacity = "1";
  },

  // Menambahkan thumbnail ke video dari frame pertama
  addThumbnailToVideo: function (videoItem) {
    if (videoItem.thumbnailAdded || videoItem.isLoading) return;

    const video = videoItem.element;

    setTimeout(() => {
      try {
        if (video.readyState >= 2) {
          const canvas = document.createElement("canvas");
          canvas.width = video.videoWidth || 400;
          canvas.height = video.videoHeight || 300;
          const ctx = canvas.getContext("2d");

          const originalTime = video.currentTime;
          video.currentTime = 0.1;

          setTimeout(() => {
            try {
              ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
              const thumbnailUrl = canvas.toDataURL("image/jpeg", 0.8);
              video.setAttribute("poster", thumbnailUrl);
              videoItem.thumbnailAdded = true;
              video.currentTime = originalTime;
            } catch (e) {}
          }, 100);
        }
      } catch (e) {}
    }, 500);
  },

  // Setup Intersection Observer dan interval untuk playback
  setupPlaybackController: function () {
    this.observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          const video = entry.target;
          const videoItem = this.allVideos.find((v) => v.element === video);

          if (!videoItem) return;

          videoItem.isVisible = entry.isIntersecting;

          if (this.viewportUpdateTimeout) {
            clearTimeout(this.viewportUpdateTimeout);
          }

          this.viewportUpdateTimeout = setTimeout(() => {
            this.updatePlayback();
          }, 100);
        });
      },
      {
        threshold: 0.3,
        rootMargin: "150px 0px 150px 0px",
      }
    );

    this.allVideos.forEach((videoItem) => {
      this.observer.observe(videoItem.element);
    });

    this.checkInterval = setInterval(() => {
      this.updatePlayback();
    }, 1000);
  },

  // Fungsi utama untuk mengatur playback video
  updatePlayback: function () {
    const now = Date.now();
    const visibleVideos = [];

    // Kumpulkan video yang siap dan terlihat di viewport
    for (const videoItem of this.allVideos) {
      if (!videoItem.isReady) continue;

      const rect = videoItem.element.getBoundingClientRect();
      const isVisible =
        rect.top < window.innerHeight * 0.9 &&
        rect.bottom > window.innerHeight * 0.1;

      videoItem.isVisible = isVisible;

      if (isVisible) {
        visibleVideos.push({
          videoItem: videoItem,
          top: rect.top,
          isPlaying: !videoItem.element.paused,
          lastPlayTime: videoItem.lastPlayTime || 0,
        });
      }
    }

    if (visibleVideos.length === 0) {
      // Pause semua video jika tidak ada yang terlihat
      for (const videoItem of this.playingVideos) {
        this.gentlePause(videoItem);
      }
      return;
    }

    // Urutkan video dari atas ke bawah
    visibleVideos.sort((a, b) => a.top - b.top);

    // Tentukan video yang harus diputar (maksimal MAX_PLAYING)
    const shouldPlayVideos = visibleVideos.slice(0, this.MAX_PLAYING);
    const shouldPlaySet = new Set(shouldPlayVideos.map((v) => v.videoItem));

    // Play video yang harus diputar tapi belum playing
    for (const item of shouldPlayVideos) {
      const videoItem = item.videoItem;
      const video = videoItem.element;

      if (video.paused) {
        this.gentlePlay(videoItem);
      }
    }

    // Pause video yang playing tapi tidak harus playing
    for (const videoItem of this.playingVideos) {
      if (!shouldPlaySet.has(videoItem)) {
        this.gentlePause(videoItem);
      }
    }
  },

  // Memulai buffer sequential video
  startSequentialBuffer: function () {
    if (this.isBuffering || this.currentBufferIndex >= this.allVideos.length) {
      return;
    }

    this.isBuffering = true;
    const videoItem = this.allVideos[this.currentBufferIndex];
    this.bufferVideo(videoItem);
  },

  // Buffer video individual
  bufferVideo: function (videoItem) {
    const video = videoItem.element;
    const src = videoItem.src;

    if (!src) {
      this.moveToNextVideo();
      return;
    }

    videoItem.isLoading = true;
    videoItem.attempts++;
    videoItem.bufferStartTime = Date.now();

    this.showLoadingIndicator(video);

    if (!video.querySelector(`source[src="${src}"]`)) {
      video.innerHTML = `<source src="${src}" type="video/mp4">`;
    }

    video.load();

    const bufferCheckInterval = setInterval(() => {
      this.checkBufferProgress(videoItem, bufferCheckInterval);
    }, 800);

    videoItem.bufferInterval = bufferCheckInterval;
  },

  // Memeriksa progress buffer video
  checkBufferProgress: function (videoItem, intervalId) {
    const video = videoItem.element;
    const now = Date.now();

    if (video.buffered.length > 0 && video.duration > 0) {
      const bufferedEnd = video.buffered.end(video.buffered.length - 1);
      const progress = Math.round((bufferedEnd / video.duration) * 100);

      videoItem.bufferProgress = progress;

      // Localhost: buffer 30% sudah cukup
      if (this.IS_LOCALHOST && progress >= 30) {
        clearInterval(intervalId);
        this.handleBufferComplete(videoItem, `buffer-${progress}`);
        return;
      }

      // Hosting: harus 100% buffer
      if (!this.IS_LOCALHOST && progress >= 100) {
        clearInterval(intervalId);
        this.handleBufferComplete(videoItem, "buffer-100");
        return;
      }
    }

    // Fallback untuk localhost jika buffer tidak terdeteksi
    if (this.IS_LOCALHOST && video.readyState >= 2) {
      clearInterval(intervalId);
      videoItem.bufferProgress = 30;
      this.handleBufferComplete(videoItem, "readyState-2-fallback");
      return;
    }

    const timeout = this.IS_LOCALHOST ? 10000 : 45000;
    if (now - videoItem.bufferStartTime > timeout) {
      clearInterval(intervalId);
      this.handleBufferError(videoItem, "timeout");
    }
  },

  // Handle ketika buffer selesai
  handleBufferComplete: function (videoItem, reason) {
    videoItem.isLoading = false;
    videoItem.isReady = true;
    this.readyVideos++;

    this.hideLoadingIndicator(videoItem.element);

    // Buat thumbnail untuk video sebelumnya jika ada
    if (videoItem.index > 0) {
      const prevVideo = this.allVideos[videoItem.index - 1];
      if (prevVideo && !prevVideo.thumbnailAdded) {
        this.addThumbnailToVideo(prevVideo);
      }
    }

    this.moveToNextVideo();
  },

  // Handle error buffer
  handleBufferError: function (videoItem, reason) {
    this.hideLoadingIndicator(videoItem.element);

    if (videoItem.attempts < 3) {
      setTimeout(
        () => {
          this.bufferVideo(videoItem);
        },
        this.IS_LOCALHOST ? 1000 : 3000
      );
    } else {
      videoItem.isLoading = false;
      videoItem.hasError = true;
      this.moveToNextVideo();
    }
  },

  // Pindah ke video berikutnya untuk buffer
  moveToNextVideo: function () {
    this.currentBufferIndex++;
    this.isBuffering = false;

    if (this.currentBufferIndex < this.allVideos.length) {
      setTimeout(
        () => {
          this.startSequentialBuffer();
        },
        this.IS_LOCALHOST ? 50 : 150
      );
    } else {
      const lastVideo = this.allVideos[this.allVideos.length - 1];
      if (lastVideo && !lastVideo.thumbnailAdded) {
        this.addThumbnailToVideo(lastVideo);
      }

      // Trigger playback update setelah semua buffer selesai
      setTimeout(() => {
        this.updatePlayback();
      }, 300);
    }
  },

  // Memutar video dengan gentle (tidak reset waktu)
  gentlePlay: function (videoItem) {
    const video = videoItem.element;
    const now = Date.now();

    if (!video.paused) {
      return;
    }

    // Cek protection time setelah pause
    const lastPausedTime = this.recentlyPausedVideos.get(videoItem.index);
    if (lastPausedTime && now - lastPausedTime < this.PAUSE_PROTECTION_TIME) {
      return;
    }

    const playPromise = video.play();

    if (playPromise !== undefined) {
      playPromise
        .then(() => {
          videoItem.hasBeenPlayed = true;
          videoItem.lastPlayTime = now;
          this.playingVideos.add(videoItem);
          this.recentlyPausedVideos.delete(videoItem.index);
        })
        .catch((err) => {});
    }
  },

  // Pause video dengan gentle (tidak reset waktu ke 0)
  gentlePause: function (videoItem) {
    const video = videoItem.element;

    if (!video.paused) {
      video.pause();
      // Tidak reset currentTime ke 0 untuk mencegah video restart
      this.playingVideos.delete(videoItem);
      this.recentlyPausedVideos.set(videoItem.index, Date.now());
    }
  },

  // Cleanup resources
  cleanup: function () {
    if (this.observer) {
      this.observer.disconnect();
    }

    if (this.checkInterval) {
      clearInterval(this.checkInterval);
    }

    if (this.viewportUpdateTimeout) {
      clearTimeout(this.viewportUpdateTimeout);
    }

    this.allVideos.forEach((videoItem) => {
      if (videoItem.bufferInterval) {
        clearInterval(videoItem.bufferInterval);
      }

      this.hideLoadingIndicator(videoItem.element);

      if (!videoItem.element.paused) {
        videoItem.element.pause();
      }
    });

    this.playingVideos.clear();
    this.recentlyPausedVideos.clear();
    this.viewportVideos.clear();
  },
};

// Ekspos sistem video ke global scope
window.videoSystem = videoSystem;

// Inisialisasi saat DOM siap
document.addEventListener("DOMContentLoaded", function () {
  setTimeout(() => {
    if (window.videoSystem) {
      window.videoSystem.init();
    }

    if (textInput && !textInput.value) {
      textInput.focus();
    }
  }, 100);
});

// Cleanup sebelum unload
window.addEventListener("beforeunload", function () {
  if (window.videoSystem) {
    window.videoSystem.cleanup();
  }
});

// Handle user interaction
let userHasInteracted = false;
function handleUserInteraction() {
  if (!userHasInteracted) {
    userHasInteracted = true;
  }
}

document.addEventListener("click", handleUserInteraction);
document.addEventListener("touchstart", handleUserInteraction);

// Handle scroll dengan debounce
let scrollTimeout;
window.addEventListener("scroll", function () {
  clearTimeout(scrollTimeout);
  scrollTimeout = setTimeout(() => {
    if (window.videoSystem && window.videoSystem.updatePlayback) {
      window.videoSystem.updatePlayback();
    }
  }, 200);
});

// Handle resize dengan debounce
let resizeTimeout;
window.addEventListener("resize", function () {
  clearTimeout(resizeTimeout);
  resizeTimeout = setTimeout(() => {
    if (window.videoSystem && window.videoSystem.updatePlayback) {
      window.videoSystem.updatePlayback();
    }
  }, 300);
});

// Reset form input
function resetForm() {
  if (textInput) {
    textInput.value = "";
    textInput.focus();
  }
}

// Debug function untuk melihat status video
window.showVideoStatus = function () {
  if (window.videoSystem) {
    const playing = window.videoSystem.playingVideos.size;
    const ready = window.videoSystem.readyVideos;
    const total = window.videoSystem.allVideos.length;

    window.videoSystem.allVideos.forEach((videoItem) => {
      const isPlaying = window.videoSystem.playingVideos.has(videoItem);
      const currentTime = videoItem.element.currentTime.toFixed(2);
      const duration = videoItem.element.duration.toFixed(2);

      let status = "";
      if (videoItem.isLoading) {
        status = "LOADING";
      } else if (videoItem.isReady) {
        status = isPlaying
          ? `PLAYING (${currentTime}/${duration}s)`
          : `PAUSED (${currentTime}/${duration}s)`;
      } else {
        status = "WAITING";
      }
    });
  }
};
