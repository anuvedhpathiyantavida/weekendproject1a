document.addEventListener("DOMContentLoaded", function () {
    const loginForm = document.getElementById("loginForm");
    const errorAlert = document.getElementById("errorAlert");

    loginForm.addEventListener("submit", function (event) {
        event.preventDefault();

        const enteredUsername = document.getElementById("username").value.trim();
        const enteredPassword = document.getElementById("password").value.trim();

        const storedPassword = localStorage.getItem(enteredUsername);

        // ✅ Check if the user is an Admin
        if (enteredUsername === "123" && enteredPassword === "234") {
            window.location.href = "signup.html"; // Redirect Admin
            return; // Stop further execution
        }

        // ✅ Check if the user is a Regular User
        if (storedPassword && enteredPassword === storedPassword) {
            window.location.href = "http://localhost/wp1b/index.php";
        } else {
            // 🔴 Show Bootstrap Alert
            errorAlert.style.display = "block";
            errorAlert.classList.add("show");

            // 🔴 Hide Alert After 3 Seconds
            setTimeout(() => {
                errorAlert.classList.remove("show");
                errorAlert.style.display = "none";
            }, 3000);
        }
    });
});
