// BOOKSHELF SYSTEM JAVASCRIPT - UPDATED FOR NEW API
class BookshelfSystem {
  constructor() {
    this.currentStatus = "all";
    this.init();
  }

  init() {
    this.initTabs();
    this.initStatusDropdowns();
    this.initProgressTracking();
    this.loadBookshelfStats();
  }

  // TAB SYSTEM (tetap sama)
  initTabs() {
    const tabs = document.querySelectorAll(".bookshelf-tab");

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        const status = tab.dataset.status;
        this.switchTab(status);
      });
    });

    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get("tab") || "all";
    this.switchTab(initialTab);
  }

  switchTab(status) {
    document.querySelectorAll(".bookshelf-tab").forEach((tab) => {
      tab.classList.remove("active");
    });

    document.querySelector(`[data-status="${status}"]`).classList.add("active");

    document.querySelectorAll(".bookshelf-card").forEach((card) => {
      if (status === "all" || card.dataset.status === status) {
        card.style.display = "block";
      } else {
        card.style.display = "none";
      }
    });

    const url = new URL(window.location);
    url.searchParams.set("tab", status);
    window.history.pushState({}, "", url);

    this.currentStatus = status;
  }

  // STATUS DROPDOWN SYSTEM - UPDATED API ENDPOINT
  initStatusDropdowns() {
    document.addEventListener("click", (e) => {
      if (
        e.target.classList.contains("status-toggle") ||
        e.target.closest(".status-toggle")
      ) {
        const toggle = e.target.classList.contains("status-toggle")
          ? e.target
          : e.target.closest(".status-toggle");
        this.toggleStatusMenu(toggle);
      }

      if (!e.target.closest(".status-dropdown")) {
        this.closeAllStatusMenus();
      }

      if (e.target.classList.contains("status-option")) {
        const option = e.target;
        this.updateBookStatus(option);
      }
    });
  }

  toggleStatusMenu(toggle) {
    const dropdown = toggle.closest(".status-dropdown");
    const menu = dropdown.querySelector(".status-menu");

    this.closeAllStatusMenus();
    menu.classList.toggle("active");
  }

  closeAllStatusMenus() {
    document.querySelectorAll(".status-menu").forEach((menu) => {
      menu.classList.remove("active");
    });
  }

  async updateBookStatus(option) {
    const dropdown = option.closest(".status-dropdown");
    const bookId = dropdown.dataset.bookId;
    const newStatus = option.dataset.status;
    const card = dropdown.closest(".bookshelf-card");

    // Show loading state
    const toggle = dropdown.querySelector(".status-toggle");
    const originalIcon = toggle.innerHTML;
    toggle.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
      const formData = new FormData();
      formData.append("book_id", bookId);
      formData.append("status", newStatus);

      // Updated API endpoint
      const response = await fetch("api/bookshelf.php?action=update_status", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        this.showStatusUpdateSuccess(newStatus);
        this.updateCardStatus(card, newStatus);
        this.updateBookshelfStats(result.stats);

        if (this.currentStatus !== "all" && this.currentStatus !== newStatus) {
          card.style.display = "none";
        }
      } else {
        this.showStatusUpdateError();
      }
    } catch (error) {
      this.showStatusUpdateError();
      console.error("Status update error:", error);
    } finally {
      toggle.innerHTML = originalIcon;
      this.closeAllStatusMenus();
    }
  }

  updateCardStatus(card, newStatus) {
    const badge = card.querySelector(".bookshelf-status");
    badge.className = `bookshelf-status status-${newStatus}`;

    const statusLabels = {
      want_to_read: "Ingin Dibaca",
      reading: "Sedang Dibaca",
      read: "Selesai Dibaca",
    };

    badge.textContent = statusLabels[newStatus];
    card.dataset.status = newStatus;

    if (newStatus === "reading") {
      this.showProgressInput(card);
    } else {
      this.hideProgressInput(card);
    }
  }

  // PROGRESS TRACKING - UPDATED API ENDPOINT
  initProgressTracking() {
    document.addEventListener("input", (e) => {
      if (e.target.classList.contains("progress-input")) {
        this.updateProgressBar(e.target);
      }
    });

    document.addEventListener("change", (e) => {
      if (e.target.classList.contains("progress-input")) {
        this.saveProgress(e.target);
      }
    });
  }

  updateProgressBar(input) {
    const progressBar = input
      .closest(".bookshelf-progress")
      .querySelector(".progress-fill");
    const value = input.value;
    const max = input.max;
    const percentage = (value / max) * 100;

    progressBar.style.width = `${percentage}%`;
  }

  async saveProgress(input) {
    const card = input.closest(".bookshelf-card");
    const bookId = card.dataset.bookId;
    const currentPage = input.value;
    const totalPages = input.max;

    try {
      const formData = new FormData();
      formData.append("book_id", bookId);
      formData.append("current_page", currentPage);
      formData.append("total_pages", totalPages);

      // Updated API endpoint
      const response = await fetch("api/bookshelf.php?action=update_progress", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        this.showProgressUpdateSuccess();
      } else {
        this.showProgressUpdateError();
      }
    } catch (error) {
      this.showProgressUpdateError();
      console.error("Progress update error:", error);
    }
  }

  showProgressInput(card) {
    let progressSection = card.querySelector(".bookshelf-progress");

    if (!progressSection) {
      progressSection = document.createElement("div");
      progressSection.className = "bookshelf-progress";
      progressSection.innerHTML = `
                <div class="progress-label">Progress Membaca</div>
                <input type="range" class="progress-input" min="0" max="100" value="0">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
            `;
      card.querySelector(".bookshelf-card-info").appendChild(progressSection);
    }
  }

  hideProgressInput(card) {
    const progressSection = card.querySelector(".bookshelf-progress");
    if (progressSection) {
      progressSection.remove();
    }
  }

  // STATISTICS - UPDATED API ENDPOINT
  async loadBookshelfStats() {
    try {
      // Updated API endpoint
      const response = await fetch("api/bookshelf.php?action=get_stats");
      const result = await response.json();

      if (result.success) {
        this.updateStatsDisplay(result.data);
      }
    } catch (error) {
      console.error("Failed to load bookshelf stats:", error);
    }
  }

  updateStatsDisplay(stats) {
    document.querySelector(
      ".stat-want_to_read .stat-number-bookshelf"
    ).textContent = stats.want_to_read;
    document.querySelector(".stat-reading .stat-number-bookshelf").textContent =
      stats.reading;
    document.querySelector(".stat-read .stat-number-bookshelf").textContent =
      stats.read;

    document.querySelector(
      '[data-status="want_to_read"] .tab-badge'
    ).textContent = stats.want_to_read;
    document.querySelector('[data-status="reading"] .tab-badge').textContent =
      stats.reading;
    document.querySelector('[data-status="read"] .tab-badge').textContent =
      stats.read;
  }

  async updateBookshelfStats() {
    await this.loadBookshelfStats();
  }

  // STATUS QUICK ACTIONS - UPDATED API ENDPOINT
  initQuickActions() {
    document.addEventListener("click", (e) => {
      if (e.target.classList.contains("quick-status")) {
        const button = e.target;
        const bookId = button.dataset.bookId;
        const status = button.dataset.status;

        this.quickUpdateStatus(bookId, status);
      }
    });
  }

  async quickUpdateStatus(bookId, status) {
    try {
      const formData = new FormData();
      formData.append("book_id", bookId);
      formData.append("status", status);

      // Updated API endpoint
      const response = await fetch("api/bookshelf.php?action=update_status", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        this.showStatusUpdateSuccess(status);
        setTimeout(() => window.location.reload(), 1000);
      }
    } catch (error) {
      this.showStatusUpdateError();
      console.error("Quick status update error:", error);
    }
  }

  // NOTIFICATION METHODS (tetap sama)
  showStatusUpdateSuccess(status) {
    const statusMessages = {
      want_to_read: 'Ditambahkan ke "Ingin Dibaca"',
      reading: 'Ditandai sebagai "Sedang Dibaca"',
      read: 'Ditandai sebagai "Selesai Dibaca"',
    };

    this.showAlert(statusMessages[status], "success");
  }

  showStatusUpdateError() {
    this.showAlert("Gagal memperbarui status buku", "error");
  }

  showProgressUpdateSuccess() {
    this.showAlert("Progress berhasil disimpan", "success");
  }

  showProgressUpdateError() {
    this.showAlert("Gagal menyimpan progress", "error");
  }

  showAlert(message, type) {
    const alert = document.createElement("div");
    alert.className = `alert-toast alert-${type}`;
    alert.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: var(--space-md);
            border-radius: var(--radius-md);
            color: var(--white);
            background: ${
              type === "success" ? "var(--success)" : "var(--error)"
            };
            z-index: 1000;
            max-width: 300px;
            box-shadow: var(--shadow-lg);
        `;

    alert.innerHTML = `
            <div style="display: flex; align-items: center; gap: var(--space-sm);">
                <i class="fas fa-${
                  type === "success" ? "check" : "exclamation"
                }"></i>
                <span>${message}</span>
            </div>
        `;

    document.body.appendChild(alert);

    setTimeout(() => {
      alert.remove();
    }, 3000);
  }
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  new BookshelfSystem();
});
