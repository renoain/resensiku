// books.js - Books related functionality

class BookManager {
  constructor() {
    this.books = [];
    this.filters = {
      search: "",
      genre: "",
      rating: 0,
    };
  }

  init() {
    this.bindEvents();
    this.loadBooks();
  }

  bindEvents() {
    // Search functionality
    const searchInput = document.getElementById("bookSearch");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => {
        this.filters.search = e.target.value;
        this.filterBooks();
      });
    }

    // Genre filter
    const genreFilter = document.getElementById("genreFilter");
    if (genreFilter) {
      genreFilter.addEventListener("change", (e) => {
        this.filters.genre = e.target.value;
        this.filterBooks();
      });
    }

    // Rating filter
    const ratingFilters = document.querySelectorAll(".rating-filter");
    ratingFilters.forEach((filter) => {
      filter.addEventListener("click", (e) => {
        const rating = parseInt(e.target.dataset.rating);
        this.filters.rating = rating;
        this.filterBooks();
      });
    });
  }

  async loadBooks() {
    try {
      // This would typically fetch from an API
      const response = await fetch("/api/books");
      this.books = await response.json();
      this.displayBooks(this.books);
    } catch (error) {
      console.error("Failed to load books:", error);
    }
  }

  filterBooks() {
    let filteredBooks = this.books;

    // Search filter
    if (this.filters.search) {
      filteredBooks = filteredBooks.filter(
        (book) =>
          book.title
            .toLowerCase()
            .includes(this.filters.search.toLowerCase()) ||
          book.author.toLowerCase().includes(this.filters.search.toLowerCase())
      );
    }

    // Genre filter
    if (this.filters.genre) {
      filteredBooks = filteredBooks.filter((book) =>
        book.genres.includes(this.filters.genre)
      );
    }

    // Rating filter
    if (this.filters.rating > 0) {
      filteredBooks = filteredBooks.filter(
        (book) => book.average_rating >= this.filters.rating
      );
    }

    this.displayBooks(filteredBooks);
  }

  displayBooks(books) {
    const container = document.getElementById("booksContainer");
    if (!container) return;

    if (books.length === 0) {
      container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <p>Tidak ada buku yang ditemukan</p>
                </div>
            `;
      return;
    }

    container.innerHTML = books
      .map(
        (book) => `
            <div class="book-card" data-book-id="${book.id}">
                <div class="book-cover">
                    <img src="/assets/images/books/${book.cover_image}" alt="${
          book.title
        }">
                    <div class="book-actions">
                        <button class="btn-action" onclick="addToBookshelf(${
                          book.id
                        }, 'want_to_read')" title="Ingin Dibaca">
                            <i class="fas fa-bookmark"></i>
                        </button>
                        <button class="btn-action" onclick="quickReview(${
                          book.id
                        })" title="Beri Review">
                            <i class="fas fa-star"></i>
                        </button>
                    </div>
                </div>
                <div class="book-info">
                    <h3>${book.title}</h3>
                    <p class="book-author">${book.author}</p>
                    <div class="book-rating">
                        ${this.generateStarRating(book.average_rating)}
                        <span class="rating-value">${book.average_rating.toFixed(
                          1
                        )}</span>
                    </div>
                    <p class="book-genres">
                        ${book.genres
                          .map(
                            (genre) => `<span class="genre-tag">${genre}</span>`
                          )
                          .join("")}
                    </p>
                </div>
            </div>
        `
      )
      .join("");
  }

  generateStarRating(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);

    return `
            ${'<i class="fas fa-star"></i>'.repeat(fullStars)}
            ${hasHalfStar ? '<i class="fas fa-star-half-alt"></i>' : ""}
            ${'<i class="far fa-star"></i>'.repeat(emptyStars)}
        `;
  }
}

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  const bookManager = new BookManager();
  bookManager.init();
});

// Global functions for book actions
function addToBookshelf(bookId, status) {
  // Implementation for adding to bookshelf
  console.log(`Adding book ${bookId} to bookshelf with status: ${status}`);
}

function quickReview(bookId) {
  // Implementation for quick review modal
  console.log(`Quick review for book: ${bookId}`);
}
