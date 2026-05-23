/* ============================================================
   PROFILE DASHBOARD — FRONTEND INTERACTIONS
   Matches dashboard.php + dashboard.css
============================================================ */

/* ---------------------------
   SAVE BUTTON LOGIC
--------------------------- */
const saveBtn = document.getElementById("save-btn");

function showSaveButton() {
    saveBtn.classList.remove("hidden");
}

document.querySelectorAll("input, textarea, select, [contenteditable]").forEach(el => {
    el.addEventListener("input", showSaveButton);
});

/* ---------------------------
   ABOUT ME (contenteditable)
--------------------------- */
const aboutMe = document.getElementById("about-me-text");
aboutMe.addEventListener("input", showSaveButton);

/* ---------------------------
   PROFILE PICTURE PREVIEW
--------------------------- */
const pfpUpload = document.getElementById("pfp-upload");
const pfpImg = document.getElementById("pfp-img");

pfpUpload.addEventListener("change", () => {
    const file = pfpUpload.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = () => {
        pfpImg.src = reader.result;
        showSaveButton();
    };
    reader.readAsDataURL(file);
});

/* ---------------------------
   URL ADDING / REMOVING
--------------------------- */
const addUrlBtn = document.getElementById("add-url-btn");
const urlNameInput = document.getElementById("url-name-input");
const urlLinkInput = document.getElementById("url-link-input");
const urlsList = document.getElementById("urls-list");

addUrlBtn.addEventListener("click", () => {
    const name = urlNameInput.value.trim();
    const link = urlLinkInput.value.trim();

    if (!name || !link) return;

    const item = document.createElement("div");
    item.classList.add("url-item");

    item.innerHTML = `
        <a href="${link}" target="_blank">${name}</a>
        <button class="remove-url">Remove</button>
    `;

    urlsList.appendChild(item);

    urlNameInput.value = "";
    urlLinkInput.value = "";

    showSaveButton();
});

urlsList.addEventListener("click", (e) => {
    if (e.target.classList.contains("remove-url")) {
        e.target.parentElement.remove();
        showSaveButton();
    }
});

/* ---------------------------
   PICTURE UPLOAD + POPUP VIEWER
--------------------------- */
const pictureUpload = document.getElementById("picture-upload");
const picturesGrid = document.getElementById("pictures-grid");

let pictureArray = [];
let currentPictureIndex = 0;

pictureUpload.addEventListener("change", () => {
    const files = Array.from(pictureUpload.files);

    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = () => {
            pictureArray.push(reader.result);

            const div = document.createElement("div");
            div.classList.add("picture-item");
            div.innerHTML = `<img src="${reader.result}">`;

            div.addEventListener("click", () => openImagePopup(reader.result));

            picturesGrid.appendChild(div);
        };
        reader.readAsDataURL(file);
    });

    showSaveButton();
});

/* ---------------------------
   IMAGE POPUP
--------------------------- */
const popup = document.getElementById("image-popup");
const popupImage = document.getElementById("popup-image");
const popupClose = document.querySelector(".popup-close");
const popupPrev = document.querySelector(".popup-prev");
const popupNext = document.querySelector(".popup-next");

function openImagePopup(src) {
    popup.classList.remove("hidden");
    popupImage.src = src;
    currentPictureIndex = pictureArray.indexOf(src);
}

popupClose.addEventListener("click", () => {
    popup.classList.add("hidden");
});

popupPrev.addEventListener("click", () => {
    if (pictureArray.length === 0) return;
    currentPictureIndex = (currentPictureIndex - 1 + pictureArray.length) % pictureArray.length;
    popupImage.src = pictureArray[currentPictureIndex];
});

popupNext.addEventListener("click", () => {
    if (pictureArray.length === 0) return;
    currentPictureIndex = (currentPictureIndex + 1) % pictureArray.length;
    popupImage.src = pictureArray[currentPictureIndex];
});

/* ---------------------------
   BLINKIE UPLOAD
--------------------------- */
const blinkieUpload = document.getElementById("blinkie-upload");
const blinkiesContainer = document.getElementById("blinkies-container");

blinkieUpload.addEventListener("change", () => {
    const files = Array.from(blinkieUpload.files);

    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = () => {
            const img = document.createElement("img");
            img.src = reader.result;
            img.classList.add("blinkie-item");
            blinkiesContainer.appendChild(img);
        };
        reader.readAsDataURL(file);
    });

    showSaveButton();
});

/* ---------------------------
   MUSIC SQUARE PLAYER
--------------------------- */
const musicUpload = document.getElementById("music-upload");
const audioElement = document.getElementById("audio-element");
const musicSquare = document.getElementById("music-square");

musicUpload.addEventListener("change", () => {
    const file = musicUpload.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = () => {
        audioElement.src = reader.result;
        showSaveButton();
    };
    reader.readAsDataURL(file);
});

musicSquare.addEventListener("click", () => {
    if (!audioElement.src) return;

    if (audioElement.paused) {
        audioElement.play();
        musicSquare.classList.add("playing");
    } else {
        audioElement.pause();
        musicSquare.classList.remove("playing");
    }
});

/* ---------------------------
   SEARCH BAR (FRONTEND MOCK)
--------------------------- */
const searchInput = document.getElementById("search-input");
const searchResult = document.getElementById("search-result");
const foundUsername = document.getElementById("found-username");
const viewProfileBtn = document.getElementById("view-profile-btn");

searchInput.addEventListener("input", () => {
    const value = searchInput.value.trim();

    if (value.length < 3) {
        searchResult.classList.add("hidden");
        return;
    }

    // Placeholder behavior
    foundUsername.textContent = value;
    searchResult.classList.remove("hidden");
});

viewProfileBtn.addEventListener("click", () => {
    const username = foundUsername.textContent;
    window.location.href = `view_profile.php?user=${encodeURIComponent(username)}`;
});

/* ---------------------------
   TOP 8 (Placeholder Logic)
--------------------------- */
document.querySelectorAll(".top8-slot").forEach(slot => {
    slot.addEventListener("click", () => {
        alert("Top 8 selection system will be added later.");
        showSaveButton();
    });
});
