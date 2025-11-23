document.addEventListener("DOMContentLoaded", function () {
  const bookCards = document.querySelectorAll(".book-card");

  bookCards.forEach((card) => {
    card.addEventListener("mouseenter", function () {
      this.style.transform = "translateY(-5px)";
    });

    card.addEventListener("mouseleave", function () {
      this.style.transform = "translateY(0)";
    });
  });

  const statusElements = document.querySelectorAll(".book-status");

  statusElements.forEach((status) => {
    status.addEventListener("click", function (e) {
      e.stopPropagation();
      console.log("Update book status clicked");
    });
  });

  const genreCards = document.querySelectorAll(".genre-card");

  genreCards.forEach((card) => {
    card.addEventListener("click", function (e) {
      const genre = this.querySelector(".genre-name").textContent;
      console.log("Filter by genre:", genre);
    });
  });
});
