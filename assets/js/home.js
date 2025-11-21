// HOMEPAGE INTERACTIONS
class HomepageInteractions {
  constructor() {
    this.init();
  }

  init() {
    this.initSearch();
    this.initBookshelfHover();
    this.initBookCardInteractions();
    this.initGenreInteractions();
    this.initDropdownMenu();
  }

  // Search functionality
  initSearch() {
    const searchForm = document.querySelector(".search-form");
    const searchInput = document.querySelector(".search-bar");

    if (searchInput) {
      // Real-time search suggestions
      searchInput.addEventListener("input", (e) => {
        this.handleSearchInput(e.target.value);
      });

      // Form submission
      searchForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.performSearch(searchInput.value);
      });
    }
  }

  handleSearchInput(query) {
    if (query.length > 2) {
      // Show search suggestions
      console.log("Searching for:", query);
      // In a real implementation, this would fetch from API
    }
  }

  performSearch(query) {
    if (query.trim()) {
      window.location.href = `search.php?q=${encodeURIComponent(query)}`;
    }
  }

  // Bookshelf hover effects
  initBookshelfHover() {
    const shelfItems = document.querySelectorAll(".shelf-item");

    shelfItems.forEach((item) => {
      item.addEventListener("mouseenter", () => {
        item.style.transform = "translateY(-5px)";
      });

      item.addEventListener("mouseleave", () => {
        item.style.transform = "translateY(0)";
      });
    });
  }

  // Book card interactions
  initBookCardInteractions() {
    const bookCards = document.querySelectorAll(".book-card");

    bookCards.forEach((card) => {
      card.addEventListener("click", (e) => {
        // Navigate to book detail if not clicking on interactive elements
        if (!e.target.closest("a") && !e.target.closest("button")) {
          const link = card.querySelector("a");
          if (link) {
            window.location.href = link.href;
          }
        }
      });
    });
  }

  // Genre interactions
  initGenreInteractions() {
    const genreItems = document.querySelectorAll(".genre-item");

    genreItems.forEach((item) => {
      item.addEventListener("click", (e) => {
        e.preventDefault();
        const link = item.querySelector("a");
        if (link) {
          window.location.href = link.href;
        }
      });
    });
  }

  // Dropdown menu functionality
  initDropdownMenu() {
    const dropdowns = document.querySelectorAll(".dropdown-li");

    dropdowns.forEach((dropdown) => {
      dropdown.addEventListener("mouseenter", () => {
        const menu = dropdown.querySelector(".dropdown-menu");
        if (menu) {
          menu.style.display = "block";
        }
      });

      dropdown.addEventListener("mouseleave", () => {
        const menu = dropdown.querySelector(".dropdown-menu");
        if (menu) {
          menu.style.display = "none";
        }
      });
    });
  }

  // Quick add to bookshelf
  async quickAddToBookshelf(bookId, status) {
    try {
      const response = await fetch("api/bookshelf.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "add",
          book_id: bookId,
          status: status,
        }),
      });

      const result = await response.json();

      if (result.success) {
        this.showNotification("Book added to bookshelf!", "success");
        this.updateBookshelfStats();
      } else {
        this.showNotification(result.message || "Failed to add book", "error");
      }
    } catch (error) {
      console.error("Error adding to bookshelf:", error);
      this.showNotification("Network error. Please try again.", "error");
    }
  }

  // Update bookshelf stats
  async updateBookshelfStats() {
    try {
      const response = await fetch("api/bookshelf.php?action=get_stats");
      const result = await response.json();

      if (result.success) {
        const stats = result.data;
        this.updateStatsDisplay(stats);
      }
    } catch (error) {
      console.error("Error updating stats:", error);
    }
  }

  updateStatsDisplay(stats) {
    const statElements = {
      want_to_read: document.querySelector(".shelf-item:nth-child(1) p"),
      reading: document.querySelector(".shelf-item:nth-child(2) p"),
      read: document.querySelector(".shelf-item:nth-child(3) p"),
    };

    Object.keys(statElements).forEach((key) => {
      if (statElements[key]) {
        statElements[key].textContent = `${stats[key]} books`;
      }
    });
  }

  // Notification system
  showNotification(message, type = "info") {
    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">&times;</button>
        `;

    // Add styles
    notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 300px;
            animation: slideIn 0.3s ease;
        `;

    const colors = {
      success: "#27ae60",
      error: "#e74c3c",
      info: "#3498db",
      warning: "#f39c12",
    };

    notification.style.backgroundColor = colors[type] || colors.info;

    document.body.appendChild(notification);

    // Auto remove after 3 seconds
    setTimeout(() => {
      if (notification.parentElement) {
        notification.remove();
      }
    }, 3000);
  }
}

// Utility functions
const HomepageUtils = {
  formatNumber: (num) => {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  },

  truncateText: (text, maxLength) => {
    if (text.length <= maxLength) return text;
    return text.substr(0, maxLength) + "...";
  },

  debounce: (func, wait) => {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },
};

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  new HomepageInteractions();
});

// Add CSS for notifications
const notificationStyles = `
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification button {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}
`;

// Inject notification styles
const styleSheet = document.createElement("style");
styleSheet.textContent = notificationStyles;
document.head.appendChild(styleSheet);
