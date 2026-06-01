(function () {
    "use strict";

    var form = document.getElementById("signupForm");
    var nameInput = document.getElementById("signupName");
    var emailInput = document.getElementById("signupEmail");
    var usernameInput = document.getElementById("signupUsername");
    var passwordInput = document.getElementById("signupPassword");
    var confirmPasswordInput = document.getElementById("signupConfirmPassword");
    var submitBtn = document.getElementById("signupBtn");

    var errorBox = document.getElementById("signupError");
    var successBox = document.getElementById("signupSuccess");

    var nameError = document.getElementById("nameError");
    var emailError = document.getElementById("emailError");
    var usernameError = document.getElementById("usernameError");
    var passwordError = document.getElementById("passwordError");
    var confirmPasswordError = document.getElementById("confirmPasswordError");

    function clearAllErrors() {
        errorBox.textContent = "";
        errorBox.classList.remove("visible");
        successBox.textContent = "";
        successBox.classList.remove("visible");

        var errors = [nameError, emailError, usernameError, passwordError, confirmPasswordError];
        var inputs = [nameInput, emailInput, usernameInput, passwordInput, confirmPasswordInput];

        for (var i = 0; i < errors.length; i++) {
            errors[i].textContent = "";
            errors[i].classList.remove("visible");
        }
        for (var j = 0; j < inputs.length; j++) {
            inputs[j].classList.remove("input-error");
        }
    }

    function showFieldError(input, errorEl, message) {
        input.classList.add("input-error");
        errorEl.textContent = message;
        errorEl.classList.add("visible");
    }

    function showGeneralError(message) {
        errorBox.textContent = message;
        errorBox.classList.add("visible");
    }

    function showSuccess(message) {
        successBox.textContent = message;
        successBox.classList.add("visible");
    }

    function setLoading(isLoading) {
        if (isLoading) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Creating...";
        } else {
            submitBtn.disabled = false;
            submitBtn.textContent = "Create Dashboard";
        }
    }

    function validateFields() {
        var valid = true;
        clearAllErrors();

        var name = nameInput.value.trim();
        var email = emailInput.value.trim();
        var username = usernameInput.value.trim();
        var password = passwordInput.value;
        var confirmPassword = confirmPasswordInput.value;

        if (!name) {
            showFieldError(nameInput, nameError, "Name is required");
            valid = false;
        }

        if (!email) {
            showFieldError(emailInput, emailError, "Email is required");
            valid = false;
        } else {
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                showFieldError(emailInput, emailError, "Please enter a valid email address");
                valid = false;
            }
        }

        if (!username) {
            showFieldError(usernameInput, usernameError, "Username is required");
            valid = false;
        } else if (username.length < 3 || username.length > 30) {
            showFieldError(usernameInput, usernameError, "Username must be 3-30 characters");
            valid = false;
        } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
            showFieldError(usernameInput, usernameError, "Username can only contain letters, numbers, and underscores");
            valid = false;
        }

        if (!password) {
            showFieldError(passwordInput, passwordError, "Password is required");
            valid = false;
        } else if (password.length < 6) {
            showFieldError(passwordInput, passwordError, "Password must be at least 6 characters");
            valid = false;
        }

        if (!confirmPassword) {
            showFieldError(confirmPasswordInput, confirmPasswordError, "Please confirm your password");
            valid = false;
        } else if (password !== confirmPassword) {
            showFieldError(confirmPasswordInput, confirmPasswordError, "Passwords do not match");
            valid = false;
        }

        return valid;
    }

    function handleSignup(e) {
        e.preventDefault();

        clearAllErrors();

        if (!validateFields()) {
            return;
        }

        var payload = {
            name: nameInput.value.trim(),
            email: emailInput.value.trim(),
            username: usernameInput.value.trim(),
            password: passwordInput.value,
            confirm_password: confirmPasswordInput.value
        };

        setLoading(true);

        fetch("/api/register", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        })
        .then(function (response) {
            var contentType = response.headers.get("content-type") || "";

            if (!response.ok) {
                return response.text().then(function (text) {
                    var errorData;
                    try {
                        errorData = JSON.parse(text);
                    } catch (err) {
                        errorData = null;
                    }

                    if (errorData && errorData.message) {
                        throw new Error(errorData.message);
                    }

                    switch (response.status) {
                        case 400:
                            throw new Error("Please check your input and try again");
                        case 409:
                            throw new Error("An account with this email or username already exists");
                        case 429:
                            throw new Error("Too many attempts. Please try again later");
                        default:
                            throw new Error("Server error (" + response.status + "). Please try again");
                    }
                });
            }

            if (contentType.indexOf("application/json") === -1) {
                throw new Error("Unexpected response from server. Please try again");
            }

            return response.json();
        })
        .then(function (data) {
            if (!data) return;

            if (!data.user) {
                throw new Error("Invalid response from server. Please try again");
            }

            var userName = data.user.name || payload.name;
            var userUsername = data.user.username || payload.username;

            localStorage.setItem("name", userName);
            localStorage.setItem("username", userUsername);

            if (data.access_token) {
                localStorage.setItem("access_token", data.access_token);
            }
            if (data.refresh_token) {
                localStorage.setItem("refresh_token", data.refresh_token);
            }

            showSuccess("Account created successfully! Redirecting...");

            setTimeout(function () {
                window.location.href = "dashboard.html";
            }, 1000);
        })
        .catch(function (err) {
            if (err.name === "TypeError" && err.message === "Failed to fetch") {
                showGeneralError("Network error. Please check your connection and try again");
            } else {
                showGeneralError(err.message || "An unexpected error occurred. Please try again");
            }
        })
        .finally(function () {
            setLoading(false);
        });
    }

    form.addEventListener("submit", handleSignup);
})();
