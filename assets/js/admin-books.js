// admin-books.js - JavaScript for admin books management

document.addEventListener("DOMContentLoaded", function () {
  initBookForm();
  initImageUpload();
  initCharacterCounter();
});

// Initialize book form functionality
function initBookForm() {
  const bookForm = document.getElementById("bookForm");
  if (bookForm) {
    bookForm.addEventListener("submit", handleFormSubmit);
  }
}

// Handle form submission with loading state
function handleFormSubmit(e) {
  const submitBtn = document.getElementById("submitBtn");
  const originalText = submitBtn.innerHTML;

  // Show loading state
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
  submitBtn.classList.add("btn-loading");
  submitBtn.disabled = true;

  // Basic validation
  if (!validateForm()) {
    e.preventDefault();
    resetButtonState(submitBtn, originalText);
    return;
  }
}

// Reset button to original state
function resetButtonState(button, originalHTML) {
  button.innerHTML = originalHTML;
  button.classList.remove("btn-loading");
  button.disabled = false;
}

// Form validation
function validateForm() {
  const title = document.getElementById("title");
  const author = document.getElementById("author");
  let isValid = true;

  // Clear previous errors
  clearFieldError(title);
  clearFieldError(author);

  // Title validation
  if (!title.value.trim()) {
    showFieldError(title, "Judul buku harus diisi");
    isValid = false;
  } else if (title.value.trim().length < 2) {
    showFieldError(title, "Judul buku terlalu pendek");
    isValid = false;
  }

  // Author validation
  if (!author.value.trim()) {
    showFieldError(author, "Penulis harus diisi");
    isValid = false;
  }

  // Publication year validation
  const year = document.getElementById("publication_year");
  if (
    year.value &&
    (year.value < 1900 || year.value > new Date().getFullYear())
  ) {
    showFieldError(year, "Tahun terbit tidak valid");
    isValid = false;
  }

  // Page count validation
  const pageCount = document.getElementById("page_count");
  if (pageCount.value && pageCount.value < 1) {
    showFieldError(pageCount, "Jumlah halaman tidak valid");
    isValid = false;
  }

  return isValid;
}

// Show field error
function showFieldError(field, message) {
  field.style.borderColor = "var(--error)";

  let errorElement = field.parentNode.querySelector(".field-error");
  if (!errorElement) {
    errorElement = document.createElement("div");
    errorElement.className = "field-error";
    field.parentNode.appendChild(errorElement);
  }

  errorElement.textContent = message;
  errorElement.style.color = "var(--error)";
  errorElement.style.fontSize = "0.8rem";
  errorElement.style.marginTop = "5px";
}

// Clear field error
function clearFieldError(field) {
  field.style.borderColor = "";
  const errorElement = field.parentNode.querySelector(".field-error");
  if (errorElement) {
    errorElement.remove();
  }
}

// Image upload functionality
function initImageUpload() {
  const fileInput = document.getElementById("cover_image");
  if (!fileInput) return;

  fileInput.addEventListener("change", function (e) {
    const file = e.target.files[0];
    if (file) {
      validateImageFile(file);
    }
  });
}

// Validate image file before upload
function validateImageFile(file) {
  const maxSize = 2 * 1024 * 1024; // 2MB
  const allowedTypes = ["image/jpeg", "image/jpg", "image/png", "image/gif"];

  // Check file type
  if (!allowedTypes.includes(file.type)) {
    alert("Format file tidak didukung. Harus JPG, PNG, atau GIF.");
    return false;
  }

  // Check file size
  if (file.size > maxSize) {
    alert("Ukuran file terlalu besar. Maksimal 2MB.");
    return false;
  }

  return true;
}

// Character counter for synopsis
function initCharacterCounter() {
  const synopsisTextarea = document.getElementById("synopsis");
  if (!synopsisTextarea) return;

  const counter = document.querySelector(".char-counter");

  function updateCounter() {
    const length = synopsisTextarea.value.length;
    counter.textContent = `${length} karakter`;

    // Add warning/error classes based on length
    counter.classList.remove("warning", "error");
    if (length > 1000) {
      counter.classList.add("warning");
    }
    if (length > 2000) {
      counter.classList.add("error");
    }
  }

  synopsisTextarea.addEventListener("input", updateCounter);
  updateCounter(); // Initial count
}
