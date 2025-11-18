// Toggle password visibility
function togglePasswordVisibility(fieldId = "password") {
  const passwordInput = document.getElementById(fieldId);
  const eyeIcon = document.getElementById(
    `eyeIcon${fieldId === "password" ? "Password" : "Confirm"}`
  );

  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    eyeIcon.classList.remove("fa-eye-slash");
    eyeIcon.classList.add("fa-eye");
  } else {
    passwordInput.type = "password";
    eyeIcon.classList.remove("fa-eye");
    eyeIcon.classList.add("fa-eye-slash");
  }
}

// Form validation
function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

function validatePassword(password) {
  return password.length >= 6;
}

// Remember Me modal functionality
document.addEventListener("DOMContentLoaded", function () {
  const rememberCheckbox = document.getElementById("rememberCheckbox");
  const modal = document.getElementById("rememberMeModal");
  const modalSaveBtn = document.getElementById("modalSaveBtn");
  const modalCancelBtn = document.getElementById("modalCancelBtn");

  if (rememberCheckbox && modal) {
    rememberCheckbox.addEventListener("change", function () {
      if (this.checked) {
        modal.style.display = "block";
      }
    });

    modalSaveBtn.addEventListener("click", function () {
      modal.style.display = "none";
      // Here you would typically save to localStorage
      console.log("Remember me saved");
    });

    modalCancelBtn.addEventListener("click", function () {
      modal.style.display = "none";
      rememberCheckbox.checked = false;
    });

    // Close modal when clicking outside
    window.addEventListener("click", function (event) {
      if (event.target === modal) {
        modal.style.display = "none";
        rememberCheckbox.checked = false;
      }
    });
  }

  // Form validation for login
  const loginForm = document.getElementById("loginForm");
  if (loginForm) {
    loginForm.addEventListener("submit", function (e) {
      const email = document.getElementById("email").value;
      const password = document.getElementById("password").value;

      if (!validateEmail(email)) {
        e.preventDefault();
        alert("Please enter a valid email address");
        return false;
      }

      if (!validatePassword(password)) {
        e.preventDefault();
        alert("Password must be at least 6 characters long");
        return false;
      }
    });
  }

  // Form validation for signup
  const signupForm = document.getElementById("signupForm");
  if (signupForm) {
    signupForm.addEventListener("submit", function (e) {
      const firstName = document.getElementById("first_name").value;
      const lastName = document.getElementById("last_name").value;
      const email = document.getElementById("email").value;
      const password = document.getElementById("password").value;
      const confirmPassword = document.getElementById("confirm_password").value;

      if (!firstName || !lastName) {
        e.preventDefault();
        alert("Please enter both first and last name");
        return false;
      }

      if (!validateEmail(email)) {
        e.preventDefault();
        alert("Please enter a valid email address");
        return false;
      }

      if (!validatePassword(password)) {
        e.preventDefault();
        alert("Password must be at least 6 characters long");
        return false;
      }

      if (password !== confirmPassword) {
        e.preventDefault();
        alert("Passwords do not match");
        return false;
      }
    });
  }

  // Google login button functionality
  const googleBtn = document.querySelector(".btn-google");
  if (googleBtn) {
    googleBtn.addEventListener("click", function () {
      alert("Google login functionality would be implemented here");
      // In a real application, this would redirect to Google OAuth
    });
  }
});
