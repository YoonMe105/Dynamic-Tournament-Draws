document.addEventListener("DOMContentLoaded", function () {

    const password = document.getElementById("password");
    const confirmPassword = document.getElementById("confirm_password");
    const togglePassword = document.getElementById("togglePassword");
    const toggleConfirmPassword = document.getElementById("toggleConfirmPassword");
    const passwordMessage = document.getElementById("passwordMessage");
    const signinForm = document.getElementById("signinForm");

    togglePassword.addEventListener("click", function () {
        if (password.type === "password") {
            password.type = "text";
            togglePassword.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
            togglePassword.setAttribute("aria-label", "Hide password");
        } else {
            password.type = "password";
            togglePassword.innerHTML = '<i class="fa-solid fa-eye"></i>';
            togglePassword.setAttribute("aria-label", "Show password");
        }
    });

    toggleConfirmPassword.addEventListener("click", function () {
        if (confirmPassword.type === "password") {
            confirmPassword.type = "text";
            toggleConfirmPassword.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
            toggleConfirmPassword.setAttribute("aria-label", "Hide password");
        } else {
            confirmPassword.type = "password";
            toggleConfirmPassword.innerHTML = '<i class="fa-solid fa-eye"></i>';
            toggleConfirmPassword.setAttribute("aria-label", "Show password");
        }
    });

    function checkPassword() {
        if (confirmPassword.value === "") {
            passwordMessage.textContent = "";
            return;
        }

        if (password.value === confirmPassword.value) {
            passwordMessage.textContent = "Passwords match.";
            passwordMessage.style.color = "green";
        } else {
            passwordMessage.textContent = "Passwords do not match.";
            passwordMessage.style.color = "red";
        }
    }

    password.addEventListener("input", checkPassword);
    confirmPassword.addEventListener("input", checkPassword);

    signinForm.addEventListener("submit", function (event) {
        if (password.value !== confirmPassword.value) {
            event.preventDefault();
            passwordMessage.textContent = "Passwords do not match.";
            passwordMessage.style.color = "red";
            confirmPassword.focus();
        }
    });

});