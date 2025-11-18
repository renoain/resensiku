// main.js - General JavaScript for all pages

document.addEventListener("DOMContentLoaded", function () {
  initPasswordToggles();
  initImageUploadPreviews();
  initConfirmations();
  initNotifications();
});

// Password visibility toggle
function initPasswordToggles() {
  const toggleButtons = document.querySelectorAll(".toggle-password");

  toggleButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const input = this.parentNode.querySelector("input");
      if (input) {
        togglePasswordVisibility(input);
      }
    });

    // Keyboard accessibility
    button.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        const input = this.parentNode.querySelector("input");
        if (input) {
          togglePasswordVisibility(input);
        }
      }
    });
  });
}

function togglePasswordVisibility(input) {
  const icon = input.parentNode.querySelector("i");

  if (input.type === "password") {
    input.type = "text";
    icon.classList.replace("fa-eye-slash", "fa-eye");
  } else {
    input.type = "password";
    icon.classList.replace("fa-eye", "fa-eye-slash");
  }
  input.focus();
}

// Image upload preview
function initImageUploadPreviews() {
  const fileInputs = document.querySelectorAll(
    'input[type="file"][accept*="image"]'
  );

  fileInputs.forEach((input) => {
    input.addEventListener("change", function (e) {
      const file = e.target.files[0];
      if (file) {
        previewImage(this, file);
      }
    });
  });
}

function previewImage(input, file) {
  const reader = new FileReader();

  reader.onload = function (e) {
    // Remove existing preview
    const existingPreview = input.parentNode.querySelector(".image-preview");
    if (existingPreview) {
      existingPreview.remove();
    }

    // Remove current cover if exists
    const currentCover = input
      .closest(".form-group")
      ?.querySelector(".current-cover");
    if (currentCover) {
      currentCover.style.display = "none";
    }

    // Create new preview
    const preview = document.createElement("div");
    preview.className = "image-preview";
    preview.innerHTML = `
            <p style="color: var(--text-medium); margin-bottom: 10px;">Preview:</p>
            <img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 300px; border-radius: 8px; margin-top: 10px;">
            <button type="button" 
                    onclick="removeImagePreview(this)" 
                    style="margin-top: 10px; padding: 5px 10px; background: var(--error); color: white; border: none; border-radius: 4px; cursor: pointer;">
                <i class="fas fa-times"></i> Hapus Preview
            </button>
        `;

    input.parentNode.appendChild(preview);
  };

  reader.readAsDataURL(file);
}

// Remove image preview
function removeImagePreview(button) {
  const preview = button.closest(".image-preview");
  const fileInput = preview.parentNode.querySelector('input[type="file"]');
  const currentCover = preview
    .closest(".form-group")
    ?.querySelector(".current-cover");

  if (preview) {
    preview.remove();
  }

  if (fileInput) {
    fileInput.value = "";
  }

  if (currentCover) {
    currentCover.style.display = "block";
  }
}

// Confirmation dialogs
function initConfirmations() {
  const confirmLinks = document.querySelectorAll('a[onclick*="confirm"]');

  confirmLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      const confirmMessage =
        this.getAttribute("onclick")?.match(/confirm\('([^']+)'/)?.[1];
      if (confirmMessage && !window.confirm(confirmMessage)) {
        e.preventDefault();
      }
    });
  });
}

// Notification system
function initNotifications() {
  // Auto-hide alerts after 5 seconds
  const alerts = document.querySelectorAll(".alert");
  alerts.forEach((alert) => {
    setTimeout(() => {
      if (alert.parentNode) {
        alert.style.opacity = "0";
        setTimeout(() => alert.remove(), 300);
      }
    }, 5000);
  });
}

// Utility functions
function showLoading(button) {
  if (button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    button.disabled = true;

    return () => {
      button.innerHTML = originalText;
      button.disabled = false;
    };
  }
}

// Export functions to global scope
window.removeImagePreview = removeImagePreview;
window.showLoading = showLoading;
