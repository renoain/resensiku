// REVIEWS SYSTEM JAVASCRIPT - UPDATED FOR NEW API
class ReviewSystem {
  constructor() {
    this.currentRating = 0;
    this.init();
  }

  init() {
    this.initStarRating();
    this.initReviewForm();
    this.initReplySystem();
    this.initLikeSystem();
    this.initCharacterCounter();
  }

  // STAR RATING SYSTEM (tetap sama)
  initStarRating() {
    const stars = document.querySelectorAll(".star");

    stars.forEach((star, index) => {
      star.addEventListener("mouseover", () => {
        this.highlightStars(index);
      });

      star.addEventListener("click", () => {
        this.setRating(index + 1);
      });

      star.closest(".star-rating").addEventListener("mouseleave", () => {
        this.highlightStars(this.currentRating - 1);
      });
    });
  }

  highlightStars(upToIndex) {
    const stars = document.querySelectorAll(".star");
    stars.forEach((star, index) => {
      if (index <= upToIndex) {
        star.classList.add("active");
      } else {
        star.classList.remove("active");
      }
    });
  }

  setRating(rating) {
    this.currentRating = rating;
    document.getElementById("rating").value = rating;
    this.highlightStars(rating - 1);
    this.showRatingFeedback(rating);
  }

  showRatingFeedback(rating) {
    const existingFeedback = document.querySelector(".rating-feedback");
    if (existingFeedback) {
      existingFeedback.remove();
    }

    const feedback = document.createElement("div");
    feedback.className = "rating-feedback";
    feedback.style.cssText = `
            margin-top: 5px;
            font-size: 0.9rem;
            color: var(--success);
            font-weight: 500;
        `;

    const messages = {
      1: "Tidak suka",
      2: "Kurang suka",
      3: "Biasa saja",
      4: "Suka",
      5: "Sangat suka!",
    };

    feedback.textContent = `Rating: ${rating}/5 - ${messages[rating]}`;
    document.querySelector(".star-rating").appendChild(feedback);
  }

  // REVIEW FORM SUBMISSION - UPDATED API ENDPOINT
  initReviewForm() {
    const reviewForm = document.getElementById("reviewForm");
    if (reviewForm) {
      reviewForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.submitReview();
      });
    }
  }

  async submitReview() {
    const form = document.getElementById("reviewForm");
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    // Validation
    if (this.currentRating === 0) {
      this.showAlert("Harap beri rating terlebih dahulu", "error");
      return;
    }

    // Show loading
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
    submitBtn.disabled = true;

    try {
      // Updated API endpoint
      const response = await fetch("api/reviews.php?action=submit", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        this.showAlert("Review berhasil dikirim!", "success");
        form.reset();
        this.currentRating = 0;
        this.highlightStars(-1);
        this.updateReviewList(result.review);
      } else {
        this.showAlert(result.message || "Gagal mengirim review", "error");
      }
    } catch (error) {
      this.showAlert("Terjadi kesalahan saat mengirim review", "error");
      console.error("Review submission error:", error);
    } finally {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    }
  }

  // REPLY SYSTEM - UPDATED API ENDPOINT
  initReplySystem() {
    document.addEventListener("click", (e) => {
      if (e.target.classList.contains("reply-btn")) {
        const reviewId = e.target.dataset.reviewId;
        this.toggleReplyForm(reviewId);
      }

      if (e.target.classList.contains("cancel-reply")) {
        this.hideReplyForm(e.target.closest(".reply-form"));
      }

      if (e.target.classList.contains("submit-reply")) {
        this.submitReply(e.target);
      }
    });
  }

  toggleReplyForm(reviewId) {
    const replyForm = document.getElementById(`reply-form-${reviewId}`);
    const allForms = document.querySelectorAll(".reply-form");

    allForms.forEach((form) => {
      if (form.id !== `reply-form-${reviewId}`) {
        form.classList.remove("active");
      }
    });

    replyForm.classList.toggle("active");

    if (replyForm.classList.contains("active")) {
      const textarea = replyForm.querySelector(".reply-textarea");
      setTimeout(() => textarea.focus(), 100);
    }
  }

  hideReplyForm(form) {
    form.classList.remove("active");
    form.querySelector(".reply-textarea").value = "";
  }

  async submitReply(button) {
    const form = button.closest(".reply-form");
    const reviewId = form.id.replace("reply-form-", "");
    const textarea = form.querySelector(".reply-textarea");
    const content = textarea.value.trim();

    if (!content) {
      this.showAlert("Harap tulis balasan terlebih dahulu", "error");
      return;
    }

    const submitBtn = form.querySelector(".submit-reply");
    const originalText = submitBtn.innerHTML;

    // Show loading
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    submitBtn.disabled = true;

    try {
      const formData = new FormData();
      formData.append("review_id", reviewId);
      formData.append("reply_content", content);

      // Updated API endpoint
      const response = await fetch("api/reviews.php?action=submit_reply", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        this.showAlert("Balasan berhasil dikirim!", "success");
        textarea.value = "";
        form.classList.remove("active");
        this.addReplyToDOM(reviewId, result.reply);
      } else {
        this.showAlert(result.message || "Gagal mengirim balasan", "error");
      }
    } catch (error) {
      this.showAlert("Terjadi kesalahan saat mengirim balasan", "error");
      console.error("Reply submission error:", error);
    } finally {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    }
  }

  // LIKE SYSTEM - UPDATED API ENDPOINT
  initLikeSystem() {
    document.addEventListener("click", (e) => {
      if (
        e.target.classList.contains("like-btn") ||
        e.target.closest(".like-btn")
      ) {
        const likeBtn = e.target.classList.contains("like-btn")
          ? e.target
          : e.target.closest(".like-btn");
        this.toggleLike(likeBtn);
      }
    });
  }

  async toggleLike(likeBtn) {
    const reviewId = likeBtn.dataset.reviewId;
    const isLiked = likeBtn.classList.contains("liked");

    try {
      const formData = new FormData();
      formData.append("review_id", reviewId);
      formData.append("action", isLiked ? "unlike" : "like");

      // Updated API endpoint
      const response = await fetch("api/reviews.php?action=like", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        likeBtn.classList.toggle("liked");
        const likeCount = likeBtn.querySelector(".like-count");
        likeCount.textContent = result.likes_count;

        // Update icon
        const icon = likeBtn.querySelector("i");
        icon.className = likeBtn.classList.contains("liked")
          ? "fas fa-heart"
          : "far fa-heart";
      }
    } catch (error) {
      console.error("Like toggle error:", error);
    }
  }

  // CHARACTER COUNTER (tetap sama)
  initCharacterCounter() {
    const textarea = document.getElementById("review_text");
    const counter = document.querySelector(".char-counter");

    if (textarea && counter) {
      textarea.addEventListener("input", () => {
        const length = textarea.value.length;
        counter.textContent = `${length} karakter`;

        counter.classList.remove("warning", "error");
        if (length > 500) {
          counter.classList.add("warning");
        }
        if (length > 1000) {
          counter.classList.add("error");
        }
      });
    }
  }

  // HELPER METHODS (tetap sama)
  showAlert(message, type) {
    const existingAlert = document.querySelector(".alert-toast");
    if (existingAlert) {
      existingAlert.remove();
    }

    const alert = document.createElement("div");
    alert.className = `alert-toast alert-${type}`;
    alert.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: var(--space-md);
            border-radius: var(--radius-md);
            color: var(--white);
            z-index: 1000;
            max-width: 300px;
            box-shadow: var(--shadow-lg);
            animation: slideInRight 0.3s ease;
        `;

    if (type === "success") {
      alert.style.background = "var(--success)";
    } else if (type === "error") {
      alert.style.background = "var(--error)";
    } else {
      alert.style.background = "var(--info)";
    }

    alert.innerHTML = `
            <div style="display: flex; align-items: center; gap: var(--space-sm);">
                <i class="fas fa-${
                  type === "success" ? "check" : "exclamation"
                }"></i>
                <span>${message}</span>
            </div>
        `;

    document.body.appendChild(alert);

    setTimeout(() => {
      alert.style.animation = "slideOutRight 0.3s ease";
      setTimeout(() => alert.remove(), 300);
    }, 3000);
  }

  updateReviewList(review) {
    setTimeout(() => {
      window.location.reload();
    }, 1500);
  }

  addReplyToDOM(reviewId, reply) {
    const repliesList = document.querySelector(`#replies-${reviewId}`);
    if (!repliesList) {
      const reviewItem = document.querySelector(
        `[data-review-id="${reviewId}"]`
      );
      const repliesHTML = `
                <div class="replies-list" id="replies-${reviewId}">
                    ${this.createReplyHTML(reply)}
                </div>
            `;
      reviewItem.insertAdjacentHTML("beforeend", repliesHTML);
    } else {
      const replyHTML = this.createReplyHTML(reply);
      repliesList.insertAdjacentHTML("beforeend", replyHTML);
    }
  }

  createReplyHTML(reply) {
    return `
            <div class="reply-item">
                <div class="reply-header">
                    <span class="reply-author">${reply.user_name}</span>
                    <span class="reply-date">${reply.created_at}</span>
                </div>
                <div class="reply-content">${reply.reply_content}</div>
            </div>
        `;
  }
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  new ReviewSystem();
});
