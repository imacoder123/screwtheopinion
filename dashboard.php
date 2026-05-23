<?php
session_start();

// Block access if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// In production, fetch from database
// For now, using placeholder data
$profile_data = [
    'pfp' => 'default-pfp.png',
    'name' => 'Your Name',
    'age' => '20',
    'gender' => 'Not Set',
    'about_me' => 'Click to edit your about me section...',
    'urls' => [],
    'music_file' => '',
    'pictures' => [],
    'blinkies' => [],
    'top_8' => []
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($username); ?>'s Profile</title>
<link rel="icon" type="image/x-icon" href="SCT.png">
<link rel="stylesheet" href="dashboard.css">
</head>

<body>

<!-- SAVE BUTTON (appears when changes are made) -->
<button id="save-btn" class="save-btn hidden">💾 SAVE CHANGES</button>

<!-- SEARCH BAR -->
<div class="search-container">
    <input type="text" id="search-input" placeholder="Search username..." class="search-input">
    <div id="search-result" class="search-result hidden">
        <span id="found-username"></span>
        <button id="view-profile-btn" class="view-profile-btn">View Profile</button>
    </div>
</div>

<!-- FLOATING SIDE ICONS -->
<div class="side-icons">
    <a href="dashboard.php" class="icon-item" title="Dashboard">
        <img src="cc.png" alt="Dashboard">
    </a>
    <a href="messenger.php" class="icon-item" title="Messages">
        <img src="ppo.png" alt="Messages">
    </a>
    <a href="blogs.php" class="icon-item" title="Blogs">
        <img src="blgs.png" alt="Blogs">
    </a>
    <a href="settings.php" class="icon-item" title="Settings">
        <img src="op.png" alt="Settings">
    </a>
    <a href="logout.php" class="icon-item" title="Logout">
        <img src="tr.png" alt="Logout">
    </a>
</div>

<!-- MAIN PROFILE CONTAINER -->
<div class="profile-wrapper">

    <!-- LEFT SECTION -->
    <div class="left-section">

        <!-- ABOUT ME -->
        <div class="panel about-panel">
            <div class="panel-header">About Me</div>
            <div class="panel-body">
                <div id="about-me-text" contenteditable="true" class="editable-text"><?php echo htmlspecialchars($profile_data['about_me']); ?></div>
            </div>
        </div>

        <!-- PICTURES -->
        <div class="panel pictures-panel">
            <div class="panel-header">Pictures</div>
            <div class="panel-body">
                <div id="pictures-grid" class="pictures-grid">
                    <!-- Pictures will be added here -->
                    <div class="add-picture-box">
                        <label for="picture-upload" class="upload-label">+ Add Picture</label>
                        <input type="file" id="picture-upload" accept="image/*" multiple hidden>
                    </div>
                </div>
            </div>
        </div>

        <!-- BLINKIES -->
        <div class="panel blinkies-panel">
            <div class="panel-header">Blinkies</div>
            <div class="panel-body">
                <div id="blinkies-container" class="blinkies-container">
                    <div class="add-blinkie-box">
                        <label for="blinkie-upload" class="upload-label">+ Add Blinkie</label>
                        <input type="file" id="blinkie-upload" accept="image/png,image/jpeg,image/gif" multiple hidden>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOP 8 -->
        <div class="panel top8-panel">
            <div class="panel-header">Top 8</div>
            <div class="panel-body">
                <div id="top8-grid" class="top8-grid">
                    <!-- 8 slots -->
                    <div class="top8-slot empty" data-slot="1">
                        <span class="slot-number">1</span>
                        <span class="add-friend">+</span>
                    </div>
                    <div class="top8-slot empty" data-slot="2">
                        <span class="slot-number">2</span>
                        <span class="add-friend">+</span>
                    </div>
                    <div class="top8-slot empty" data-slot="3">
                        <span class="slot-number">3</span>
                        <span class="add-friend">+</span>
                    </div>
                    <div class="top8-slot empty" data-slot="4">
                        <span class="slot-number">4</span>
                        <span class="add-friend">+</span>
                    </div>
                    <div class="top8-slot empty" data-slot="5">
                        <span class="slot-number">5</span>
                        <span class="add-friend">+</span>
                    </div>
                    <div class="top8-slot empty" data-slot="6">
                        <span class="slot-number">6</span>
                        <span class="add-friend">+</span>
                    </div>
                    <div class="top8-slot empty" data-slot="7">
                        <span class="slot-number">7</span>
                        <span class="add-friend">+</span>
                    </div>
                    <div class="top8-slot empty" data-slot="8">
                        <span class="slot-number">8</span>
                        <span class="add-friend">+</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- RIGHT SECTION -->
    <div class="right-section">

        <!-- PROFILE PIC -->
        <div class="pfp-container">
            <div class="pfp-circle">
                <img id="pfp-img" src="<?php echo htmlspecialchars($profile_data['pfp']); ?>" alt="Profile Picture">
            </div>
            <label for="pfp-upload" class="change-pfp-btn">Change PFP</label>
            <input type="file" id="pfp-upload" accept="image/*" hidden>
        </div>

        <!-- NAME / AGE / GENDER -->
        <div class="info-box">
            <input type="text" id="name-input" class="info-input" placeholder="Name" value="<?php echo htmlspecialchars($profile_data['name']); ?>">
            <input type="number" id="age-input" class="info-input" placeholder="Age" value="<?php echo htmlspecialchars($profile_data['age']); ?>">
            <select id="gender-input" class="info-input">
                <option value="Not Set">Prefer not to say</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Non-Binary">Non-Binary</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <!-- CONTACT ACTION BUTTONS -->
        <div class="action-buttons">
            <button class="action-btn">📨 Send Invite</button>
            <button class="action-btn">⭐ Add to Top 8</button>
            <button class="action-btn">📤 Forward Contact</button>
            <button class="action-btn">🚫 Block</button>
        </div>

        <!-- MY URLS -->
        <div class="panel urls-panel">
            <div class="panel-header">My URLs</div>
            <div class="panel-body">
                <div id="urls-container" class="urls-container">
                    <div class="add-url-box">
                        <input type="text" id="url-name-input" placeholder="Platform name" class="url-input">
                        <input type="url" id="url-link-input" placeholder="https://" class="url-input">
                        <button id="add-url-btn" class="add-url-btn">+ Add</button>
                    </div>
                    <div id="urls-list" class="urls-list">
                        <!-- URLs will appear here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- MUSIC PLAYER -->
        <div class="panel music-panel">
            <div class="panel-header">Music</div>
            <div class="panel-body">
                <div id="music-player" class="music-player">
                    <div id="music-square" class="music-square">
                        <span class="music-icon">♫</span>
                        <audio id="audio-element" src=""></audio>
                    </div>
                    <label for="music-upload" class="upload-music-btn">Upload MP3</label>
                    <input type="file" id="music-upload" accept="audio/mp3,audio/mpeg" hidden>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- IMAGE POPUP VIEWER -->
<div id="image-popup" class="image-popup hidden">
    <div class="popup-overlay"></div>
    <div class="popup-content">
        <button class="popup-close">✕</button>
        <button class="popup-nav popup-prev">&lt;</button>
        <img id="popup-image" src="" alt="View">
        <button class="popup-nav popup-next">&gt;</button>
    </div>
</div>

<script src="profile-dashboard.js"></script>

</body>
</html>