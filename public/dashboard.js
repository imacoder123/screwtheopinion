(function () {
    "use strict";

    var name = localStorage.getItem("name");
    var username = localStorage.getItem("username");

    if (!name || !name.trim() || !username || !username.trim()) {
        window.location.href = "signup.html";
        return;
    }

    name = name.trim();
    username = username.trim();

    document.getElementById("displayName").textContent = name;
    document.getElementById("displayUsername").textContent = username;
    document.getElementById("infoName").textContent = name;
    document.getElementById("infoUsername").textContent = "@" + username;
})();
