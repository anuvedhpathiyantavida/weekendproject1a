document.getElementById("signupForm").addEventListener("submit", function(event) {
    event.preventDefault();

    const newUsername = document.getElementById("newUsername").value.trim();
    const newPassword = document.getElementById("newPassword").value.trim();

    if (newUsername === "" || newPassword === "") {
        alert("Username and Password cannot be empty!");
        return;
    }

    if (localStorage.getItem(newUsername)) {
        alert("Username already exists. Please choose another.");
        return;
    }

    localStorage.setItem(newUsername, newPassword);

    alert("Account created successfully! Redirecting to login...");
    window.location.href = "index.html";
});
