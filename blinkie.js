const BLINKIE_KEY = 'userBlinkies';
const input = document.getElementById('blinkieUrlInput');
const btn = document.getElementById('addBlinkieBtn');
const banner = document.getElementById('user-blinkies');

// Load and render blinkies
function renderUserBlinkies() {
    banner.innerHTML = '';
    const blinkies = JSON.parse(localStorage.getItem(BLINKIE_KEY) || '[]');
    blinkies.forEach(url => {
        const img = document.createElement('img');
        img.src = url;
        img.alt = "User Blinkie";
        img.title = url;
        img.style.cursor = "pointer";
        // Optional: Remove blinkie on click
        img.onclick = function() {
            if (confirm("Remove this blinkie?")) {
                removeBlinkie(url);
            }
        };
        banner.appendChild(img);
    });
}

// Add blinkie
btn.onclick = function() {
    const url = input.value.trim();
    if (!url) return;
    // Basic image URL validation
    if (!url.match(/\.(gif|png|jpg|jpeg)$/i)) {
        alert("Please enter a valid image URL (gif, png, jpg, jpeg).");
        return;
    }
    let blinkies = JSON.parse(localStorage.getItem(BLINKIE_KEY) || '[]');
    if (!blinkies.includes(url)) {
        blinkies.push(url);
        localStorage.setItem(BLINKIE_KEY, JSON.stringify(blinkies));
        renderUserBlinkies();
    }
    input.value = '';
};

// Remove blinkie
function removeBlinkie(url) {
    let blinkies = JSON.parse(localStorage.getItem(BLINKIE_KEY) || '[]');
    blinkies = blinkies.filter(u => u !== url);
    localStorage.setItem(BLINKIE_KEY, JSON.stringify(blinkies));
    renderUserBlinkies();
}

// Initial render
renderUserBlinkies();