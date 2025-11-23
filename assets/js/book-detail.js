let currentRating = 0;
let isEditing = false;
let bookshelfDropdownOpen = false;
let formRating = 0;

document.addEventListener("DOMContentLoaded", function () {
  initializeBookshelfDropdown();
  initializeQuickRating();
  initializeReviewForm();
  initializeEditReview();
  initializeSearch();
  initializeUserDropdown();
});

// Bookshelf Dropdown Functionality - CLICK VERSION
function initializeBookshelfDropdown() {
  const bookshelfDropdown = document.getElementById("bookshelfDropdown");

  // Close dropdown when clicking outside
  document.addEventListener("click", function (e) {
    if (!e.target.closest(".status-dropdown")) {
      bookshelfDropdown.classList.remove("show");
      bookshelfDropdownOpen = false;
    }
  });
}

function toggleBookshelfDropdown() {
  const bookshelfDropdown = document.getElementById("bookshelfDropdown");
  bookshelfDropdownOpen = !bookshelfDropdownOpen;

  if (bookshelfDropdownOpen) {
    bookshelfDropdown.classList.add("show");
  } else {
    bookshelfDropdown.classList.remove("show");
  }
}

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

// Quick Rating Functionality
function initializeQuickRating() {
  const quickRating = document.getElementById("quickRating");
  if (!quickRating) return;

  const stars = quickRating.querySelectorAll(".star-icon-large");
  const ratingText = document.querySelector(".rating-text");

  // Set initial rating from PHP
  currentRating = parseInt(
    quickRating.getAttribute("data-current-rating") || "0"
  );
  updateQuickRatingUI(stars, ratingText, currentRating);

  // Add click events
  stars.forEach((star) => {
    star.addEventListener("click", function () {
      const rating = parseInt(this.getAttribute("data-rating"));
      submitQuickRating(rating);
    });

    // Hover effects
    star.addEventListener("mouseenter", function () {
      const hoverRating = parseInt(this.getAttribute("data-rating"));
      updateQuickRatingUI(stars, ratingText, hoverRating, false);
    });

    star.addEventListener("mouseleave", function () {
      updateQuickRatingUI(stars, ratingText, currentRating, false);
    });
  });
}

function updateQuickRatingUI(stars, ratingText, rating, permanent = true) {
  stars.forEach((star, index) => {
    if (index < rating) {
      star.innerHTML = '<i class="fas fa-star"></i>';
      if (permanent) star.classList.add("filled");
    } else {
      star.innerHTML = '<i class="far fa-star"></i>';
      if (permanent) star.classList.remove("filled");
    }
  });

  if (ratingText) {
    if (rating > 0) {
      ratingText.textContent = `Anda memberi rating ${rating} bintang`;
    } else {
      ratingText.textContent = "Klik bintang untuk memberi rating";
    }
  }
}

async function submitQuickRating(rating) {
  const formData = new FormData();
  formData.append(
    "book_id",
    document.querySelector('input[name="book_id"]').value
  );
  formData.append("rating", rating);
  formData.append("action", "quick_rating");

  try {
    showLoading("Menyimpan rating...");

    const response = await fetch("api/review.php", {
      method: "POST",
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      currentRating = rating;
      updateQuickRatingUI(
        document.querySelectorAll("#quickRating .star-icon-large"),
        document.querySelector(".rating-text"),
        rating
      );
      updateAverageRating();
      showNotification("Rating berhasil disimpan", "success");

      // Reload page if this is first rating to show review section
      const hasExistingReview = document.querySelector(".user-review-section");
      if (!hasExistingReview) {
        setTimeout(() => location.reload(), 1000);
      }
    } else {
      showNotification("Error: " + data.message, "error");
    }
  } catch (error) {
    console.error("Error:", error);
    showNotification("Terjadi kesalahan saat memberikan rating", "error");
  } finally {
    hideLoading();
  }
}

// Review Form Functionality
function initializeReviewForm() {
  const reviewForm = document.getElementById("reviewForm");
  if (!reviewForm) return;

  const reviewRating = document.getElementById("reviewRating");
  const selectedRating = document.getElementById("selectedRating");
  const reviewTextarea = document.getElementById("reviewTextarea");
  const charCounter = document.getElementById("charCounter");
  const submitReview = document.getElementById("submitReview");
  const cancelReview = document.getElementById("cancelReview");

  // Initialize form rating
  formRating = 0;

  // Star rating interaction
  const stars = reviewRating.querySelectorAll(".star");
  stars.forEach((star) => {
    star.addEventListener("click", function () {
      formRating = parseInt(this.getAttribute("data-rating"));
      selectedRating.value = formRating;
      updateReviewStars(stars, formRating);
      updateSubmitButton();
    });

    star.addEventListener("mouseenter", function () {
      const hoverRating = parseInt(this.getAttribute("data-rating"));
      updateReviewStars(stars, hoverRating, false);
    });

    star.addEventListener("mouseleave", function () {
      updateReviewStars(stars, formRating, false);
    });
  });

  // Character counter
  reviewTextarea.addEventListener("input", function () {
    const length = this.value.length;
    charCounter.textContent = length + "/1000 karakter";

    if (length > 900) {
      charCounter.classList.add("warning");
    } else {
      charCounter.classList.remove("warning");
    }

    if (length > 1000) {
      charCounter.classList.add("error");
      this.value = this.value.substring(0, 1000);
    } else {
      charCounter.classList.remove("error");
    }

    updateSubmitButton();
  });

  // Cancel review
  cancelReview.addEventListener("click", function () {
    reviewTextarea.value = "";
    formRating = 0;
    selectedRating.value = 0;
    updateReviewStars(stars, 0, false);
    updateSubmitButton();
    showNotification("Review dibatalkan", "info");
  });

  // Form submission
  reviewForm.addEventListener("submit", async function (e) {
    e.preventDefault();

    if (formRating === 0) {
      showNotification("Silakan berikan rating terlebih dahulu", "error");
      return;
    }

    if (reviewTextarea.value.trim().length === 0) {
      showNotification("Silakan tulis review terlebih dahulu", "error");
      return;
    }

    await submitReviewForm();
  });

  function updateSubmitButton() {
    const hasRating = formRating > 0;
    const hasText = reviewTextarea.value.trim().length > 0;
    submitReview.disabled = !(hasRating && hasText);
  }

  async function submitReviewForm() {
    const formData = new FormData(reviewForm);
    const submitBtn = document.getElementById("submitReview");

    submitBtn.disabled = true;
    submitBtn.textContent = "Mengirim...";

    try {
      showLoading("Mengirim review...");

      const response = await fetch("api/review.php", {
        method: "POST",
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        showNotification("Review berhasil disimpan", "success");
        setTimeout(() => location.reload(), 1000);
      } else {
        showNotification("Error: " + data.message, "error");
        submitBtn.disabled = false;
        submitBtn.textContent = "Kirim Review";
      }
    } catch (error) {
      console.error("Error:", error);
      showNotification("Terjadi kesalahan saat mengirim review", "error");
      submitBtn.disabled = false;
      submitBtn.textContent = "Kirim Review";
    } finally {
      hideLoading();
    }
  }
}

function updateReviewStars(stars, rating, permanent = true) {
  stars.forEach((star, index) => {
    if (index < rating) {
      star.innerHTML = '<i class="fas fa-star"></i>';
      if (permanent) star.classList.add("active");
    } else {
      star.innerHTML = '<i class="far fa-star"></i>';
      if (permanent) star.classList.remove("active");
    }
  });
}

// Edit Review Functionality
function initializeEditReview() {
  const editReviewBtn = document.getElementById("editReviewBtn");
  if (editReviewBtn) {
    editReviewBtn.addEventListener("click", enableEditReview);
  }

  const deleteReviewBtn = document.getElementById("deleteReviewBtn");
  if (deleteReviewBtn) {
    deleteReviewBtn.addEventListener("click", deleteReview);
  }
}

function enableEditReview() {
  if (isEditing) return;

  isEditing = true;
  const reviewSection = document.querySelector(".user-review-section");
  const reviewContent = reviewSection.querySelector(".user-review-content");
  const reviewText = reviewContent.textContent.trim();

  // Replace with textarea
  const textarea = document.createElement("textarea");
  textarea.className = "review-textarea edit-textarea";
  textarea.value = reviewText;
  textarea.maxLength = 1000;
  textarea.placeholder = "Edit review Anda...";
  reviewContent.replaceWith(textarea);

  // Add character counter
  const charCounter = document.createElement("div");
  charCounter.className = "char-counter";
  charCounter.id = "editCharCounter";
  charCounter.textContent = textarea.value.length + "/1000 karakter";
  textarea.parentNode.insertBefore(charCounter, textarea.nextSibling);

  // Update character counter
  textarea.addEventListener("input", function () {
    const length = this.value.length;
    charCounter.textContent = length + "/1000 karakter";

    if (length > 900) {
      charCounter.classList.add("warning");
    } else {
      charCounter.classList.remove("warning");
    }

    if (length > 1000) {
      charCounter.classList.add("error");
      this.value = this.value.substring(0, 1000);
    } else {
      charCounter.classList.remove("error");
    }
  });

  // Add edit actions
  const editActions = document.createElement("div");
  editActions.className = "edit-actions";
  editActions.innerHTML = `
        <button type="button" class="btn-cancel" onclick="cancelEdit()">
            <i class="fas fa-times"></i> Batal
        </button>
        <button type="button" class="btn-submit" onclick="saveEdit()">
            <i class="fas fa-save"></i> Simpan Perubahan
        </button>
    `;

  document.querySelector(".user-review-actions").style.display = "none";
  reviewSection.appendChild(editActions);

  // Focus on textarea
  textarea.focus();
}

function cancelEdit() {
  isEditing = false;
  location.reload();
}

async function saveEdit() {
  const textarea = document.querySelector(".edit-textarea");
  const newText = textarea.value.trim();

  if (!newText) {
    showNotification("Review text tidak boleh kosong", "error");
    return;
  }

  const formData = new FormData();
  formData.append(
    "book_id",
    document.querySelector('input[name="book_id"]').value
  );
  formData.append("rating", currentRating);
  formData.append("review_text", newText);

  try {
    showLoading("Menyimpan perubahan...");

    const response = await fetch("api/review.php", {
      method: "POST",
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      showNotification("Review berhasil diupdate", "success");
      setTimeout(() => location.reload(), 1000);
    } else {
      showNotification("Error: " + data.message, "error");
    }
  } catch (error) {
    console.error("Error:", error);
    showNotification("Terjadi kesalahan saat mengupdate review", "error");
  } finally {
    hideLoading();
  }
}

async function deleteReview() {
  if (
    !confirm(
      "Apakah Anda yakin ingin menghapus review ini? Tindakan ini tidak dapat dibatalkan."
    )
  ) {
    return;
  }

  const formData = new FormData();
  formData.append(
    "book_id",
    document.querySelector('input[name="book_id"]').value
  );
  formData.append("action", "delete_review");

  try {
    showLoading("Menghapus review...");

    const response = await fetch("api/review.php", {
      method: "POST",
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      showNotification("Review berhasil dihapus", "success");
      setTimeout(() => location.reload(), 1000);
    } else {
      showNotification("Error: " + data.message, "error");
    }
  } catch (error) {
    console.error("Error:", error);
    showNotification("Terjadi kesalahan saat menghapus review", "error");
  } finally {
    hideLoading();
  }
}

// Bookshelf Functionality
async function updateBookshelf(status) {
  // Close dropdown after selection
  const bookshelfDropdown = document.getElementById("bookshelfDropdown");
  bookshelfDropdown.classList.remove("show");
  bookshelfDropdownOpen = false;

  const formData = new FormData();
  formData.append(
    "book_id",
    document.querySelector('input[name="book_id"]').value
  );
  formData.append("status", status);

  try {
    showLoading("Mengupdate bookshelf...");

    const response = await fetch("api/bookshelf.php", {
      method: "POST",
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      updateBookshelfUI(status);
      showNotification("Bookshelf berhasil diupdate", "success");
    } else {
      showNotification("Error: " + data.message, "error");
    }
  } catch (error) {
    console.error("Error:", error);
    showNotification("Terjadi kesalahan saat mengupdate bookshelf", "error");
  } finally {
    hideLoading();
  }
}

function updateBookshelfUI(status) {
  const statusButton = document.querySelector(".status-button");
  const statusText = {
    want_to_read: "Ingin Dibaca",
    reading: "Sedang Dibaca",
    read: "Sudah Dibaca",
    remove: "Tambahkan ke Bookshelf",
  };

  if (status === "remove") {
    statusButton.innerHTML =
      '<i class="fas fa-bookmark"></i> Tambahkan ke Bookshelf <i class="fas fa-chevron-down"></i>';
    // Remove remove button from dropdown
    const removeOption = document.querySelector(".option-remove");
    if (removeOption) {
      removeOption.remove();
    }
    // Remove divider if no options left
    const divider = document.querySelector(".status-divider");
    if (divider && !document.querySelector(".option-remove")) {
      divider.remove();
    }
  } else {
    statusButton.innerHTML = `<i class="fas fa-bookmark"></i> ${statusText[status]} <i class="fas fa-chevron-down"></i>`;
    // Ensure remove button exists
    if (!document.querySelector(".option-remove")) {
      const dropdownContent = document.querySelector(
        ".status-dropdown-content form"
      );
      const divider = document.createElement("div");
      divider.className = "status-divider";
      const removeBtn = document.createElement("button");
      removeBtn.type = "button";
      removeBtn.className = "status-option option-remove";
      removeBtn.innerHTML =
        '<span>Hapus dari Bookshelf</span><i class="fas fa-times"></i>';
      removeBtn.onclick = () => updateBookshelf("remove");
      dropdownContent.appendChild(divider);
      dropdownContent.appendChild(removeBtn);
    }
  }

  // Update radio buttons
  document.querySelectorAll('input[name="status_radio"]').forEach((radio) => {
    radio.checked = radio.value === status;
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

// Utility Functions
function updateAverageRating() {
  // This would typically be updated from server response
  // For now, we'll just increment the count
  const totalReviews = document.getElementById("totalReviews");
  if (totalReviews) {
    const currentCount = parseInt(totalReviews.textContent);
    totalReviews.textContent = currentCount + 1;
  }
}

function showNotification(message, type = "info") {
  // Remove existing notifications
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
window.updateBookshelf = updateBookshelf;
window.submitQuickRating = submitQuickRating;
window.enableEditReview = enableEditReview;
window.cancelEdit = cancelEdit;
window.saveEdit = saveEdit;
window.deleteReview = deleteReview;
window.toggleBookshelfDropdown = toggleBookshelfDropdown;
