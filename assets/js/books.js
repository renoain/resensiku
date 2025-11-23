document.addEventListener("DOMContentLoaded", function () {
  initializeUserDropdown();
  initializeSearch();
  initializeGenreFilters();
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
          searchForm.submit();
        }
      }, 500);
    });

    // Also allow Enter key for immediate search
    searchInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        searchForm.submit();
      }
    });
  }
}

// Genre Filters Functionality
function initializeGenreFilters() {
  const genreFilters = document.querySelectorAll(".genre-filter");

  genreFilters.forEach((filter) => {
    filter.addEventListener("click", function (e) {
      // Add loading state
      this.classList.add("loading");
    });
  });
}

function showLoading(message = "Memuat...") {
  console.log(message);
}

window.initializeGenreFilters = initializeGenreFilters;
