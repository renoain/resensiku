document.addEventListener("DOMContentLoaded", function () {
  initializeUserDropdown();
  initializeSearch();
  initializeSortDropdown();
});

// User Dropdown Functionality
function initializeUserDropdown() {
  const userDropdowns = document.querySelectorAll(".user-dropdown");

  userDropdowns.forEach((dropdown) => {
    const trigger = dropdown.querySelector(".user-trigger");

    trigger.addEventListener("click", function (e) {
      e.stopPropagation();
      dropdown.classList.toggle("active");
    });
  });

  // Close dropdown when clicking outside
  document.addEventListener("click", function () {
    userDropdowns.forEach((dropdown) => {
      dropdown.classList.remove("active");
    });
  });
}

// Search Functionality
function initializeSearch() {
  const searchInput = document.querySelector(".search-input");
  if (searchInput) {
    searchInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        const query = this.value.trim();
        if (query) {
          window.location.href = `books.php?search=${encodeURIComponent(
            query
          )}`;
        }
      }
    });
  }
}

// Sort Dropdown Functionality
function initializeSortDropdown() {
  const sortButton = document.querySelector(".sort-button");
  if (sortButton) {
    // You can implement sort functionality here
    sortButton.addEventListener("click", function () {
      showNotification("Fitur sorting akan segera tersedia!", "info");
    });
  }
}

// Update Book Status Functionality
async function updateBookStatus(bookId, status) {
  const formData = new FormData();
  formData.append("book_id", bookId);
  formData.append("status", status);

  try {
    showLoading("Mengupdate status...");

    const response = await fetch("api/bookshelf.php", {
      method: "POST",
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      showNotification("Status berhasil diupdate", "success");

      // If removing book, reload page to reflect changes
      if (status === "remove") {
        setTimeout(() => {
          location.reload();
        }, 1000);
      } else {
        // Update UI for status change
        updateBookStatusUI(bookId, status);
      }
    } else {
      showNotification("Error: " + data.message, "error");
    }
  } catch (error) {
    console.error("Error:", error);
    showNotification("Terjadi kesalahan saat mengupdate status", "error");
  } finally {
    hideLoading();
  }
}

function updateBookStatusUI(bookId, status) {
  const bookCard = document
    .querySelector(`[onclick*="${bookId}"]`)
    .closest(".bookshelf-card");
  if (!bookCard) return;

  // Update status badge
  const statusBadge = bookCard.querySelector(".status-badge");
  const statusIcons = {
    want_to_read: "bookmark",
    reading: "book-open",
    read: "check-circle",
  };
  const statusText = {
    want_to_read: "Ingin Dibaca",
    reading: "Sedang Dibaca",
    read: "Sudah Dibaca",
  };

  statusBadge.className = `status-badge status-${status}`;
  statusBadge.innerHTML = `<i class="fas fa-${statusIcons[status]}"></i> ${statusText[status]}`;

  // Update quick status buttons
  const statusButtons = bookCard.querySelectorAll(".status-btn");
  statusButtons.forEach((btn) => {
    btn.classList.remove("active");
  });

  const activeBtn = bookCard.querySelector(`.status-btn[onclick*="${status}"]`);
  if (activeBtn) {
    activeBtn.classList.add("active");
  }

  // Update stats if needed
  updateStatsCount();
}

function updateStatsCount() {
  showNotification("Status berhasil diupdate!", "success");
}

// Utility Functions
function showNotification(message, type = "info") {
  const existingNotifications = document.querySelectorAll(".notification");
  existingNotifications.forEach((notification) => notification.remove());

  const notification = document.createElement("div");
  notification.className = `notification notification-${type}`;

  const icons = {
    success: "check-circle",
    error: "exclamation-circle",
    warning: "exclamation-triangle",
    info: "info-circle",
  };

  notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${icons[type] || "info-circle"}"></i>
            <span>${message}</span>
        </div>
    `;

  document.body.appendChild(notification);

  // Show notification
  setTimeout(() => notification.classList.add("show"), 100);

  // Hide after 4 seconds
  setTimeout(() => {
    notification.classList.remove("show");
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 300);
  }, 4000);
}

function showLoading(message = "Memproses...") {
  // Remove existing loaders
  const existingLoaders = document.querySelectorAll(".loading-overlay");
  existingLoaders.forEach((loader) => loader.remove());

  const loader = document.createElement("div");
  loader.className = "loading-overlay";
  loader.innerHTML = `
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>${message}</p>
        </div>
    `;

  document.body.appendChild(loader);
  setTimeout(() => loader.classList.add("show"), 100);
}

function hideLoading() {
  const loader = document.querySelector(".loading-overlay");
  if (loader) {
    loader.classList.remove("show");
    setTimeout(() => {
      if (loader.parentNode) {
        loader.remove();
      }
    }, 300);
  }
}

// Export functions for global access
window.updateBookStatus = updateBookStatus;
