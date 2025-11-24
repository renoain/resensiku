document.addEventListener("DOMContentLoaded", function () {
  initializeCommunitiesPage();
});

function initializeCommunitiesPage() {
  // Real-time search
  initializeSearch();

  // Form handling
  initializeCreateCommunityForm();

  // Modal handling
  initializeModals();

  // Dropdown functionality
  initializeDropdowns();
}

function initializeSearch() {
  const searchInput = document.querySelector(".search-input");
  if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener("input", function () {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        if (this.value.trim().length >= 2 || this.value.trim().length === 0) {
          this.form.submit();
        }
      }, 500);
    });
  }
}

function initializeCreateCommunityForm() {
  const createForm = document.getElementById("createCommunityForm");
  if (createForm) {
    createForm.addEventListener("submit", function (e) {
      e.preventDefault();
      handleCreateCommunity(this);
    });
  }
}

function initializeModals() {
  // Close modal when clicking outside
  window.addEventListener("click", function (e) {
    const modal = document.getElementById("createCommunityModal");
    if (e.target === modal) {
      closeCreateCommunityModal();
    }
  });

  // Close modal with Escape key
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeCreateCommunityModal();
    }
  });
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

async function handleCreateCommunity(form) {
  const name = document.getElementById("communityName").value.trim();
  const description = document
    .getElementById("communityDescription")
    .value.trim();

  // Validation
  if (!name || !description) {
    showAlert("error", "Nama dan deskripsi community harus diisi");
    return;
  }

  if (name.length > 255) {
    showAlert(
      "error",
      "Nama community terlalu panjang (maksimal 255 karakter)"
    );
    return;
  }

  // Show loading state
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  setButtonLoading(submitBtn, "Membuat...");

  try {
    const formData = new FormData(form);

    const response = await fetch(form.action, {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.success) {
      showAlert("success", "Community berhasil dibuat!");
      setTimeout(() => {
        window.location.href = `community-detail.php?id=${result.community_id}`;
      }, 1500);
    } else {
      showAlert("error", "Gagal membuat community: " + result.message);
      resetButton(submitBtn, originalText);
    }
  } catch (error) {
    console.error("Error:", error);
    showAlert("error", "Terjadi kesalahan saat membuat community");
    resetButton(submitBtn, originalText);
  }
}

// Community Actions
async function joinCommunity(communityId, communityName = "") {
  if (!confirm("Apakah Anda yakin ingin bergabung dengan community ini?")) {
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
        location.reload();
      }, 1000);
    } else {
      showAlert("error", "Gagal keluar: " + result.message);
    }
  } catch (error) {
    console.error("Error:", error);
    showAlert("error", "Terjadi kesalahan saat keluar");
  }
}

// Modal Functions
function openCreateCommunityModal() {
  const modal = document.getElementById("createCommunityModal");
  if (modal) {
    modal.style.display = "block";
    document.body.style.overflow = "hidden";

    // Focus on first input
    const firstInput = modal.querySelector('input[type="text"]');
    if (firstInput) {
      setTimeout(() => firstInput.focus(), 100);
    }
  }
}

function closeCreateCommunityModal() {
  const modal = document.getElementById("createCommunityModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";

    // Reset form
    const form = document.getElementById("createCommunityForm");
    if (form) {
      form.reset();
    }
  }
}

// Utility Functions
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
  // Ctrl + N untuk buat community baru
  if ((e.ctrlKey || e.metaKey) && e.key === "n") {
    e.preventDefault();
    openCreateCommunityModal();
  }
});
