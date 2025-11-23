document.addEventListener("DOMContentLoaded", function () {
  initializeUserDropdown();
  initializeSearch();
  initializeMultipleGenreFilter();
  initializeGenreCheckboxes();
  syncCheckboxesWithURL();
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
  const searchForm = document.querySelector(".search-form");
  const searchInput = document.querySelector(".search-input");

  if (searchForm && searchInput) {
    // Real-time search with debounce
    let searchTimeout;
    searchInput.addEventListener("input", function () {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        if (this.value.trim().length >= 2 || this.value.trim().length === 0) {
          applySearchWithGenres(this.value.trim());
        }
      }, 500);
    });

    // Also allow Enter key for immediate search
    searchInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        applySearchWithGenres(this.value.trim());
      }
    });
  }
}

// Apply search with current genre filters
function applySearchWithGenres(searchTerm) {
  const selectedGenres = getSelectedGenresFromCheckboxes();
  const baseUrl = "books.php";
  const params = new URLSearchParams();

  // Add search parameter if exists
  if (searchTerm) {
    params.set("search", searchTerm);
  }

  // Add genres parameters
  selectedGenres.forEach((genre) => {
    params.append("genres[]", genre);
  });

  // Build final URL
  const finalUrl =
    selectedGenres.length > 0 || searchTerm
      ? `${baseUrl}?${params.toString()}`
      : baseUrl;

  window.location.href = finalUrl;
}

// Initialize Multiple Genre Filter
function initializeMultipleGenreFilter() {
  const applyButton = document.getElementById("applyGenreFilter");
  const clearAllButton = document.querySelector(".clear-all-genres-btn");

  // Apply genre filter
  if (applyButton) {
    applyButton.addEventListener("click", function (e) {
      e.preventDefault();
      applyGenreFilters();
    });
  }

  // Clear all genres
  if (clearAllButton) {
    clearAllButton.addEventListener("click", function (e) {
      e.preventDefault();
      clearAllGenres();
    });
  }

  // Initialize remove genre buttons
  initializeRemoveGenreButtons();
}

// Initialize Genre Checkboxes
function initializeGenreCheckboxes() {
  const genreCheckboxes = document.querySelectorAll(".genre-checkbox");

  genreCheckboxes.forEach((checkbox) => {
    // Update visual state when checkbox changes
    checkbox.addEventListener("change", function () {
      updateGenreFilterState(this);
    });

    // Allow keyboard navigation
    checkbox.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        this.checked = !this.checked;
        this.dispatchEvent(new Event("change"));
      }
    });

    // Initialize visual state
    updateGenreFilterState(checkbox);
  });

  // Add click handlers for genre filter labels
  const genreLabels = document.querySelectorAll(".genre-filter-checkbox");
  genreLabels.forEach((label) => {
    label.addEventListener("click", function (e) {
      if (e.target.type !== "checkbox") {
        const checkbox = this.querySelector(".genre-checkbox");
        if (checkbox) {
          checkbox.checked = !checkbox.checked;
          checkbox.dispatchEvent(new Event("change"));
        }
      }
    });
  });
}

// Initialize Remove Genre Buttons
function initializeRemoveGenreButtons() {
  const removeGenreButtons = document.querySelectorAll(".remove-genre-btn");

  removeGenreButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const genreToRemove = this.getAttribute("data-genre");
      removeGenre(genreToRemove);
    });

    // Keyboard support
    button.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        const genreToRemove = this.getAttribute("data-genre");
        removeGenre(genreToRemove);
      }
    });
  });
}

// Apply Genre Filters
function applyGenreFilters() {
  const selectedGenres = getSelectedGenresFromCheckboxes();
  const searchInput = document.querySelector('input[name="search"]');
  const searchValue = searchInput ? searchInput.value.trim() : "";

  // Build URL parameters
  const params = new URLSearchParams();

  // Add search parameter if exists
  if (searchValue) {
    params.set("search", searchValue);
  }

  // Add genres parameters
  selectedGenres.forEach((genre) => {
    params.append("genres[]", genre);
  });

  // Build final URL
  const baseUrl = "books.php";
  const finalUrl =
    selectedGenres.length > 0 || searchValue
      ? `${baseUrl}?${params.toString()}`
      : baseUrl;

  // Show loading state
  showLoadingState();

  // Navigate to new URL
  setTimeout(() => {
    window.location.href = finalUrl;
  }, 300);
}

// Get Selected Genres from Checkboxes
function getSelectedGenresFromCheckboxes() {
  const checkboxes = document.querySelectorAll(".genre-checkbox:checked");
  const selectedGenres = [];

  checkboxes.forEach((checkbox) => {
    if (checkbox.value && checkbox.value.trim() !== "") {
      selectedGenres.push(checkbox.value);
    }
  });

  return selectedGenres;
}

// Remove Single Genre
function removeGenre(genreToRemove) {
  // Get current URL parameters
  const currentUrl = new URL(window.location.href);
  const currentParams = new URLSearchParams(window.location.search);

  // Get current genres and search
  const currentGenres = currentParams.getAll("genres[]");
  const searchParam = currentParams.get("search");

  // Remove the genre from current genres
  const updatedGenres = currentGenres.filter(
    (genre) => genre !== genreToRemove
  );

  // Build new parameters
  const newParams = new URLSearchParams();

  // Add search parameter if exists
  if (searchParam) {
    newParams.set("search", searchParam);
  }

  // Add updated genres
  updatedGenres.forEach((genre) => {
    newParams.append("genres[]", genre);
  });

  // Build final URL
  const baseUrl = "books.php";
  const finalUrl = newParams.toString()
    ? `${baseUrl}?${newParams.toString()}`
    : baseUrl;

  // Show loading state
  showLoadingState();

  // Navigate to updated URL
  setTimeout(() => {
    window.location.href = finalUrl;
  }, 300);
}

// Clear All Genres
function clearAllGenres() {
  // Get current URL parameters
  const currentParams = new URLSearchParams(window.location.search);
  const searchParam = currentParams.get("search");

  // Build new parameters - only keep search if exists
  const newParams = new URLSearchParams();
  if (searchParam) {
    newParams.set("search", searchParam);
  }

  // Build final URL
  const baseUrl = "books.php";
  const finalUrl = newParams.toString()
    ? `${baseUrl}?${newParams.toString()}`
    : baseUrl;

  // Show loading state
  showLoadingState();

  // Navigate to cleaned URL
  setTimeout(() => {
    window.location.href = finalUrl;
  }, 300);
}

// Update Genre Filter Visual State
function updateGenreFilterState(checkbox) {
  const label = checkbox.closest(".genre-filter-checkbox");
  if (!label) return;

  if (checkbox.checked) {
    label.classList.add("active");
  } else {
    label.classList.remove("active");
  }
}

// Sync Checkboxes with URL Parameters
function syncCheckboxesWithURL() {
  const urlParams = new URLSearchParams(window.location.search);
  const urlGenres = urlParams.getAll("genres[]");

  const genreCheckboxes = document.querySelectorAll(".genre-checkbox");
  genreCheckboxes.forEach((checkbox) => {
    if (urlGenres.includes(checkbox.value)) {
      checkbox.checked = true;
      updateGenreFilterState(checkbox);
    } else {
      checkbox.checked = false;
      updateGenreFilterState(checkbox);
    }
  });
}

// Show Loading State
function showLoadingState() {
  const applyButton = document.getElementById("applyGenreFilter");
  if (applyButton) {
    const originalHTML = applyButton.innerHTML;
    applyButton.classList.add("loading");
    applyButton.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Memproses...';
    applyButton.disabled = true;

    // Restore button after 5 seconds (safety net)
    setTimeout(() => {
      if (applyButton.classList.contains("loading")) {
        applyButton.classList.remove("loading");
        applyButton.innerHTML = originalHTML;
        applyButton.disabled = false;
      }
    }, 5000);
  }

  // Add loading state to genre checkboxes
  const genreCheckboxes = document.querySelectorAll(".genre-filter-checkbox");
  genreCheckboxes.forEach((checkbox) => {
    checkbox.classList.add("loading");
  });

  // Show overlay or loading indicator
  const existingOverlay = document.querySelector(".loading-overlay");
  if (existingOverlay) {
    existingOverlay.remove();
  }

  const loadingOverlay = document.createElement("div");
  loadingOverlay.className = "loading-overlay";
  loadingOverlay.innerHTML = `
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Memuat buku...</p>
        </div>
    `;
  document.body.appendChild(loadingOverlay);

  // Add styles for loading overlay if not exists
  if (!document.querySelector("#loading-styles")) {
    const styles = document.createElement("style");
    styles.id = "loading-styles";
    styles.textContent = `
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                backdrop-filter: blur(5px);
            }
            .loading-spinner {
                text-align: center;
                background: var(--white);
                padding: var(--space-lg);
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-lg);
                border: 1px solid var(--text-light);
            }
            .spinner {
                width: 40px;
                height: 40px;
                border: 4px solid var(--bg-primary);
                border-top: 4px solid var(--text-dark);
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto var(--space-md);
            }
            .loading-spinner p {
                color: var(--text-dark);
                font-weight: 500;
                margin: 0;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .genre-filter-checkbox.loading {
                opacity: 0.7;
                pointer-events: none;
            }
            .genre-filter-checkbox.loading::after {
                content: "";
                position: absolute;
                top: 50%;
                right: 12px;
                width: 16px;
                height: 16px;
                border: 2px solid transparent;
                border-top: 2px solid var(--text-medium);
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
        `;
    document.head.appendChild(styles);
  }

  // Auto-remove overlay after 10 seconds (safety net)
  setTimeout(() => {
    const overlay = document.querySelector(".loading-overlay");
    if (overlay) {
      overlay.remove();
    }
    if (applyButton && applyButton.classList.contains("loading")) {
      applyButton.classList.remove("loading");
      applyButton.disabled = false;
    }
    genreCheckboxes.forEach((checkbox) => {
      checkbox.classList.remove("loading");
    });
  }, 10000);
}

// Error handling for failed operations
window.addEventListener("error", function (e) {
  console.error("Error in books.js:", e.error);

  // Remove loading states on error
  removeLoadingStates();
});

// Handle page unload to clean up
window.addEventListener("beforeunload", function () {
  removeLoadingStates();
});

// Remove all loading states
function removeLoadingStates() {
  const applyButton = document.getElementById("applyGenreFilter");
  if (applyButton && applyButton.classList.contains("loading")) {
    applyButton.classList.remove("loading");
    applyButton.disabled = false;
  }

  const genreCheckboxes = document.querySelectorAll(".genre-filter-checkbox");
  genreCheckboxes.forEach((checkbox) => {
    checkbox.classList.remove("loading");
  });

  const loadingOverlay = document.querySelector(".loading-overlay");
  if (loadingOverlay) {
    loadingOverlay.remove();
  }
}

// Export functions for global access
window.applyGenreFilters = applyGenreFilters;
window.removeGenre = removeGenre;
window.clearAllGenres = clearAllGenres;
window.getSelectedGenresFromCheckboxes = getSelectedGenresFromCheckboxes;

// Debug helper
console.log("books.js loaded successfully");
