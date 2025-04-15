/**
 * CampusBuzz - Main JavaScript File
 */

document.addEventListener("DOMContentLoaded", function () {
  // Fade out alerts after 5 seconds
  const alerts = document.querySelectorAll(".alert");
  if (alerts.length > 0) {
    setTimeout(function () {
      alerts.forEach(function (alert) {
        alert.style.transition = "opacity 1s";
        alert.style.opacity = "0";
        setTimeout(function () {
          alert.style.display = "none";
        }, 1000);
      });
    }, 5000);
  }

  // Form validation
  const forms = document.querySelectorAll("form");
  forms.forEach(function (form) {
    form.addEventListener("submit", function (e) {
      let hasError = false;

      // Check required fields
      const requiredFields = form.querySelectorAll("[required]");
      requiredFields.forEach(function (field) {
        if (!field.value.trim()) {
          field.classList.add("error");
          hasError = true;
        } else {
          field.classList.remove("error");
        }
      });

      // Check password match if confirm password exists
      const password = form.querySelector('input[name="password"]');
      const confirmPassword = form.querySelector(
        'input[name="confirm_password"]'
      );
      if (password && confirmPassword) {
        if (password.value !== confirmPassword.value) {
          confirmPassword.classList.add("error");
          hasError = true;

          // Create error message if it doesn't exist
          let errorMsg =
            confirmPassword.parentElement.querySelector(".password-error");
          if (!errorMsg) {
            errorMsg = document.createElement("div");
            errorMsg.classList.add("password-error");
            errorMsg.style.color = "#721c24";
            errorMsg.textContent = "Passwords do not match";
            confirmPassword.parentElement.appendChild(errorMsg);
          }
        } else {
          confirmPassword.classList.remove("error");
          const errorMsg =
            confirmPassword.parentElement.querySelector(".password-error");
          if (errorMsg) {
            errorMsg.remove();
          }
        }
      }

      if (hasError) {
        e.preventDefault();
      }
    });
  });

  // Date picker enhancement if available
  const datePickers = document.querySelectorAll('input[type="date"]');
  datePickers.forEach(function (datePicker) {
    // Add minimum date as today
    const today = new Date().toISOString().split("T")[0];
    datePicker.setAttribute("min", today);
  });

  // Initialize current year in the footer
  const yearEl = document.getElementById("current-year");
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }
});
