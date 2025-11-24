document.addEventListener("DOMContentLoaded", function () {
  initializeCommunityDetailPage();
});

function initializeCommunityDetailPage() {
  // Form handling
  initializeCreatePostForm();

  // Modal handling
  initializeModals();

  // Comments functionality
  initializeComments();

  // Dropdown functionality
  initializeDropdowns();
}

function initializeCreatePostForm() {
  const createPostForm = document.getElementById("createPostForm");
  if (createPostForm) {
    createPostForm.addEventListener("submit", function (e) {
      e.preventDefault();
      handleCreatePost(this);
    });
  }
}

function initializeModals() {
  // Close modals when clicking outside
  window.addEventListener("click", function (e) {
    const postModal = document.getElementById("createPostModal");
    const imageModal = document.getElementById("imageModal");

    if (e.target === postModal) {
      closeCreatePostModal();
    }
    if (e.target === imageModal) {
      closeImageModal();
    }
  });

  // Close modals with Escape key
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeCreatePostModal();
      closeImageModal();
    }
  });
}

function initializeComments() {
  // Initialize any comment-related functionality
}

function initializeDropdowns() {
  // User dropdown functionality
  const userDropdowns = document.querySelectorAll(".user-dropdown");

  userDropdowns.forEach((dropdown) => {
    const trigger = dropdown.querySelector(".user-trigger");

    if (trigger) {
      trigger.addEventListener("click", function (e) {
        e.stopPropagation();
        e.preventDefault();

        // Close all other dropdowns
        userDropdowns.forEach((otherDropdown) => {
          if (otherDropdown !== dropdown) {
            otherDropdown.classList.remove("active");
          }
        });

        // Toggle current dropdown
        dropdown.classList.toggle("active");
      });
    }
  });

  // Close dropdown when clicking outside
  document.addEventListener("click", function () {
    userDropdowns.forEach((dropdown) => {
      dropdown.classList.remove("active");
    });
  });

  // Close dropdown with Escape key
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      userDropdowns.forEach((dropdown) => {
        dropdown.classList.remove("active");
      });
    }
  });
}

async function handleCreatePost(form) {
  const content = document.getElementById("postContent").value.trim();

  // Validation
  if (!content) {
    showAlert("error", "Konten post harus diisi");
    return;
  }

  // Show loading state
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  setButtonLoading(submitBtn, "Posting...");

  try {
    const formData = new FormData(form);

    const response = await fetch(form.action, {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.success) {
      showAlert("success", "Post berhasil dibuat!");
      setTimeout(() => {
        location.reload();
      }, 1500);
    } else {
      showAlert("error", "Gagal membuat post: " + result.message);
      resetButton(submitBtn, originalText);
    }
  } catch (error) {
    console.error("Error:", error);
    showAlert("error", "Terjadi kesalahan saat membuat post");
    resetButton(submitBtn, originalText);
  }
}

// Community Actions
async function joinCommunity(communityId, communityName = "") {
  const communityText = communityName ? ` "${communityName}"` : "";

  if (
    !confirm(
      `Apakah Anda yakin ingin bergabung dengan community${communityText}?`
    )
  ) {
    return;
  }

  try {
    const response = await fetch("actions/join_community.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ community_id: communityId }),
    });

    const result = await response.json();

    if (result.success) {
      showAlert("success", "Berhasil bergabung dengan community!");
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      showAlert("error", "Gagal bergabung: " + result.message);
    }
  } catch (error) {
    console.error("Error:", error);
    showAlert("error", "Terjadi kesalahan saat bergabung");
  }
}

async function leaveCommunity(communityId, communityName = "") {
  const communityText = communityName ? ` "${communityName}"` : "";

  if (
    !confirm(`Apakah Anda yakin ingin keluar dari community${communityText}?`)
  ) {
    return;
  }

  try {
    const response = await fetch("actions/leave_community.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ community_id: communityId }),
    });

    const result = await response.json();

    if (result.success) {
      showAlert("success", "Berhasil keluar dari community!");
      setTimeout(() => {
        window.location.href = "communities.php";
      }, 1000);
    } else {
      showAlert("error", "Gagal keluar: " + result.message);
    }
  } catch (error) {
    console.error("Error:", error);
    showAlert("error", "Terjadi kesalahan saat keluar");
  }
}

// Post and Comments Functions
async function toggleComments(postId) {
  const commentsSection = document.getElementById(`comments-${postId}`);
  const commentsList = document.getElementById(`comments-list-${postId}`);
  const toggleBtn = document.querySelector(
    `[onclick="toggleComments(${postId})"]`
  );

  if (!commentsSection || !commentsList) return;

  if (
    commentsSection.style.display === "none" ||
    !commentsSection.style.display
  ) {
    // Show loading in button
    const originalHTML = toggleBtn.innerHTML;
    toggleBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memuat...';

    await loadComments(postId, commentsList);

    commentsSection.style.display = "block";
    toggleBtn.innerHTML = originalHTML;

    // Add active class
    toggleBtn.classList.add("active");
  } else {
    commentsSection.style.display = "none";
    toggleBtn.classList.remove("active");
  }
}

async function loadComments(postId, commentsList) {
  try {
    const response = await fetch(`actions/get_comments.php?post_id=${postId}`);
    const result = await response.json();

    if (result.success) {
      commentsList.innerHTML = "";

      if (result.comments.length === 0) {
        commentsList.innerHTML =
          '<p class="no-comments">Belum ada komentar</p>';
        return;
      }

      result.comments.forEach((comment) => {
        const commentElement = createCommentElement(comment);
        commentsList.appendChild(commentElement);
      });
    } else {
      commentsList.innerHTML = '<p class="error">Gagal memuat komentar</p>';
    }
  } catch (error) {
    console.error("Error loading comments:", error);
    commentsList.innerHTML =
      '<p class="error">Terjadi kesalahan saat memuat komentar</p>';
  }
}

function createCommentElement(comment) {
  const commentDiv = document.createElement("div");
  commentDiv.className = "comment-item";

  const timeAgo = getTimeAgo(comment.created_at);

  commentDiv.innerHTML = `
        <div class="comment-header">
            <div class="comment-author">
                <div class="comment-avatar">${comment.author_initials}</div>
                <div class="comment-author-info">
                    <span class="comment-author-name">${
                      comment.author_name
                    }</span>
                    <span class="comment-time">${timeAgo}</span>
                </div>
            </div>
        </div>
        <div class="comment-content">
            ${escapeHtml(comment.content)}
        </div>
    `;

  return commentDiv;
}

async function addComment(postId) {
  const commentInput = document.getElementById(`comment-input-${postId}`);
  const content = commentInput.value.trim();

  if (!content) {
    showAlert("error", "Komentar tidak boleh kosong");
    return;
  }

  try {
    const response = await fetch("actions/create_comment.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        post_id: postId,
        content: content,
      }),
    });

    const result = await response.json();

    if (result.success) {
      commentInput.value = "";

      // Reload comments
      const commentsList = document.getElementById(`comments-list-${postId}`);
      await loadComments(postId, commentsList);

      // Update comment count
      updateCommentCount(postId, 1);

      showAlert("success", "Komentar berhasil ditambahkan!");
    } else {
      showAlert("error", "Gagal menambahkan komentar: " + result.message);
    }
  } catch (error) {
    console.error("Error:", error);
    showAlert("error", "Terjadi kesalahan saat menambahkan komentar");
  }
}

function updateCommentCount(postId, increment = 1) {
  const commentBtn = document.querySelector(
    `[onclick="toggleComments(${postId})"]`
  );
  if (commentBtn) {
    const countSpan = commentBtn.querySelector("span");
    if (countSpan) {
      const currentText = countSpan.textContent;
      const currentCount = parseInt(currentText) || 0;
      const newCount = currentCount + increment;
      countSpan.textContent = `${newCount} Komentar`;
    }
  }
}

// Modal Functions
function openCreatePostModal() {
  const modal = document.getElementById("createPostModal");
  if (modal) {
    modal.style.display = "block";
    document.body.style.overflow = "hidden";

    // Focus on content textarea
    const contentTextarea = document.getElementById("postContent");
    if (contentTextarea) {
      setTimeout(() => contentTextarea.focus(), 100);
    }
  }
}

function closeCreatePostModal() {
  const modal = document.getElementById("createPostModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";

    // Reset form
    const form = document.getElementById("createPostForm");
    if (form) {
      form.reset();
    }
  }
}

function openImageModal(imageSrc) {
  const modal = document.getElementById("imageModal");
  const modalImage = document.getElementById("modalImage");

  if (modal && modalImage) {
    modalImage.src = imageSrc;
    modal.style.display = "block";
    document.body.style.overflow = "hidden";
  }
}

function closeImageModal() {
  const modal = document.getElementById("imageModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }
}

// Navigation
function goBackToCommunities() {
  window.location.href = "communities.php";
}

// Utility Functions
function getTimeAgo(timestamp) {
  const now = new Date();
  const time = new Date(timestamp);
  const diff = now - time;

  const seconds = Math.floor(diff / 1000);
  const minutes = Math.floor(seconds / 60);
  const hours = Math.floor(minutes / 60);
  const days = Math.floor(hours / 24);
  const weeks = Math.floor(days / 7);
  const months = Math.floor(days / 30);
  const years = Math.floor(days / 365);

  if (seconds < 60) return "baru saja";
  if (minutes < 60) return `${minutes} menit lalu`;
  if (hours < 24) return `${hours} jam lalu`;
  if (days < 7) return `${days} hari lalu`;
  if (weeks < 4) return `${weeks} minggu lalu`;
  if (months < 12) return `${months} bulan lalu`;
  return `${years} tahun lalu`;
}

function escapeHtml(unsafe) {
  return unsafe
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function setButtonLoading(button, text = "Loading...") {
  button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${text}`;
  button.disabled = true;
}

function resetButton(button, originalHTML) {
  button.innerHTML = originalHTML;
  button.disabled = false;
}

function showAlert(type, message) {
  // Remove existing alerts
  const existingAlerts = document.querySelectorAll(".custom-alert");
  existingAlerts.forEach((alert) => alert.remove());

  const alert = document.createElement("div");
  alert.className = `custom-alert custom-alert-${type}`;
  alert.innerHTML = `
        <div class="alert-content">
            <i class="fas fa-${getAlertIcon(type)}"></i>
            <span>${message}</span>
            <button class="alert-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

  document.body.appendChild(alert);

  // Auto remove after 5 seconds
  setTimeout(() => {
    if (alert.parentElement) {
      alert.remove();
    }
  }, 5000);
}

function getAlertIcon(type) {
  const icons = {
    success: "check-circle",
    error: "exclamation-circle",
    warning: "exclamation-triangle",
    info: "info-circle",
  };
  return icons[type] || "info-circle";
}

// Keyboard shortcuts
document.addEventListener("keydown", function (e) {
  // Ctrl + Enter untuk submit post
  if ((e.ctrlKey || e.metaKey) && e.key === "Enter") {
    const postModal = document.getElementById("createPostModal");
    if (postModal && postModal.style.display === "block") {
      const submitBtn = document.querySelector(
        '#createPostForm button[type="submit"]'
      );
      if (submitBtn) {
        submitBtn.click();
      }
    }
  }

  // Backspace untuk kembali ke communities (kecuali di input field)
  if (e.key === "Backspace" && !e.target.matches("input, textarea")) {
    if (!document.querySelector('.modal[style*="display: block"]')) {
      goBackToCommunities();
    }
  }
});
