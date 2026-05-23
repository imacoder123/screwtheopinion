<?php
session_start();
require_once __DIR__ . '/api/config.php';

$userId = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;

if (!$userId) {
    header("Location: login.html");
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT id, username, name, email, avatar, bio FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.html");
    exit();
}

// Generate JWT for WebSocket/SSE auth
$jwtToken = generate_jwt([
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'is_admin' => false
]);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messenger — ScrewTheOpinion</title>
<link rel="icon" type="image/x-icon" href="SCT.png">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;700;800&family=Hanken+Grotesk:wght@400;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<script>
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                "on-error": "#ffffff",
                "on-tertiary-container": "#c79b5f",
                "error-container": "#ffdad6",
                "on-surface": "#1a1c1e",
                "on-error-container": "#93000a",
                "surface-container-highest": "#e3e2e6",
                "surface-container-high": "#e9e8eb",
                "on-secondary-fixed-variant": "#284968",
                "tertiary-container": "#513300",
                "inverse-surface": "#2f3033",
                "on-primary-fixed-variant": "#2b486e",
                "on-tertiary-fixed-variant": "#61400c",
                "surface": "#faf9fc",
                "primary-container": "#1c3a5f",
                "on-background": "#1a1c1e",
                "outline": "#74777f",
                "secondary-container": "#badaff",
                "secondary-fixed-dim": "#a9caee",
                "surface-tint": "#446087",
                "primary": "#002448",
                "background": "#faf9fc",
                "tertiary-fixed": "#ffddb4",
                "surface-variant": "#e3e2e6",
                "on-tertiary": "#ffffff",
                "on-surface-variant": "#43474e",
                "error": "#ba1a1a",
                "inverse-on-surface": "#f1f0f4",
                "primary-fixed": "#d4e3ff",
                "on-primary-fixed": "#001c3a",
                "on-secondary-container": "#406080",
                "on-secondary-fixed": "#001d34",
                "outline-variant": "#c3c6cf",
                "on-tertiary-fixed": "#291800",
                "surface-bright": "#faf9fc",
                "on-secondary": "#ffffff",
                "surface-dim": "#dad9dd",
                "on-primary": "#ffffff",
                "surface-container": "#eeedf1",
                "secondary-fixed": "#cfe5ff",
                "surface-container-lowest": "#ffffff",
                "tertiary-fixed-dim": "#eebe7f",
                "tertiary": "#341f00",
                "inverse-primary": "#acc8f5",
                "on-primary-container": "#88a4cf",
                "secondary": "#416181",
                "surface-container-low": "#f4f3f7",
                "primary-fixed-dim": "#acc8f5"
            },
            borderRadius: {
                DEFAULT: "0.25rem",
                lg: "0.5rem",
                xl: "0.75rem",
                full: "9999px"
            },
            spacing: {
                sm: "12px",
                lg: "48px",
                "margin": "32px",
                xs: "4px",
                xl: "80px",
                "gutter": "24px",
                md: "24px",
                base: "8px"
            },
            fontFamily: {
                "body": ["Hanken Grotesk"],
                "headline": ["Bricolage Grotesque"],
                "label": ["Space Mono"]
            }
        }
    }
}
</script>
<style>
body { background-color: #efeeda; }
.y2k-border { border: 2px solid #002448; }
.y2k-shadow-sm { box-shadow: 2px 2px 0px 0px rgba(0,36,72,1); }
.y2k-shadow-md { box-shadow: 4px 4px 0px 0px rgba(0,36,72,1); }
.y2k-shadow-lg { box-shadow: 8px 8px 0px 0px rgba(0,36,72,1); }
.active-btn:active { transform: translate(2px, 2px); box-shadow: none !important; }
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
.msg-in { animation: msgIn 0.3s ease-out; }
.msg-out { animation: msgOut 0.3s ease-out; }
@keyframes msgIn { from { opacity: 0; transform: translateX(-20px); } to { opacity: 1; transform: translateX(0); } }
@keyframes msgOut { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
.typing-dot { animation: typingBounce 1.4s infinite; }
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes typingBounce { 0%,60%,100% { transform: translateY(0); } 30% { transform: translateY(-6px); } }
.scrollbar-thin::-webkit-scrollbar { width: 4px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #c3c6cf; border-radius: 2px; }
.skeleton { background: linear-gradient(90deg, #e3e2e6 25%, #eeedf1 50%, #e3e2e6 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
#emojiPicker { display: none; }
#emojiPicker.active { display: flex; }
.modal-overlay { display: none; }
.modal-overlay.active { display: flex; }
.notif-dot { width: 8px; height: 8px; background: #ba1a1a; border-radius: 50%; position: absolute; top: 4px; right: 4px; }
@media (max-width: 768px) {
    .sidebar-nav { display: none; }
    .sidebar-nav.mobile-open { display: flex; position: fixed; inset: 0; z-index: 50; width: 100%; }
    .conv-list { width: 100% !important; }
    .conv-list.hide { display: none; }
    .chat-area.show { display: flex !important; }
    .chat-area { display: none; }
}
</style>
</head>
<body class="font-body text-on-surface min-h-screen flex overflow-hidden">

<!-- SIDEBAR NAV -->
<aside id="sidebarNav" class="hidden md:flex flex-col p-sm gap-base h-screen sticky left-0 bg-surface border-r-2 border-primary w-72 flex-shrink-0 z-40">
    <div class="mb-lg p-sm">
        <h1 class="font-headline text-2xl text-primary">InstantMsg</h1>
        <p class="font-label text-xs opacity-70">Active Sessions: <span id="activeUsersCount">0</span> online</p>
    </div>

    <nav class="flex flex-col gap-xs flex-grow">
        <a class="nav-item flex items-center gap-sm px-md py-sm bg-secondary-container text-on-secondary-container border-2 border-primary y2k-shadow-sm active-btn transition-all" href="#" data-view="all" onclick="switchView('all')">
            <span class="material-symbols-outlined">forum</span>
            <span class="font-body">All Chats</span>
        </a>
        <a class="nav-item flex items-center gap-sm px-md py-sm text-on-surface-variant hover:bg-surface-container-high active-btn transition-all" href="#" data-view="unread" onclick="switchView('unread')">
            <span class="material-symbols-outlined">mark_as_unread</span>
            <span class="font-body">Unread</span>
        </a>
        <a class="nav-item flex items-center gap-sm px-md py-sm text-on-surface-variant hover:bg-surface-container-high active-btn transition-all" href="#" data-view="groups" onclick="switchView('groups')">
            <span class="material-symbols-outlined">group</span>
            <span class="font-body">Groups</span>
        </a>
        <a class="nav-item flex items-center gap-sm px-md py-sm text-on-surface-variant hover:bg-surface-container-high active-btn transition-all" href="#" data-view="requests" onclick="openRequestsModal()">
            <span class="material-symbols-outlined">person_add</span>
            <span class="font-body">Requests</span>
            <span id="requestBadge" class="ml-auto bg-error text-on-error font-label text-[10px] px-1.5 py-0.5 y2k-border" style="display:none;">0</span>
        </a>
        <a class="nav-item flex items-center gap-sm px-md py-sm text-on-surface-variant hover:bg-surface-container-high active-btn transition-all" href="#" data-view="archived" onclick="switchView('archived')">
            <span class="material-symbols-outlined">archive</span>
            <span class="font-body">Archived</span>
        </a>
    </nav>

    <div class="mt-auto flex flex-col gap-xs pt-md border-t-2 border-primary">
        <button onclick="openInviteModal()" class="w-full bg-primary text-on-primary font-headline py-sm y2k-shadow-sm active-btn mb-md flex items-center justify-center gap-xs">
            <span class="material-symbols-outlined" style="font-size:18px;">person_add</span>
            <span>Invite Friends</span>
        </button>
        <div class="flex items-center gap-sm px-md py-sm text-on-surface-variant">
            <span class="material-symbols-outlined text-sm">
                <?php if ($user['avatar']): ?>account_circle<?php else: ?>account_circle<?php endif; ?>
            </span>
            <span class="font-label text-xs">@<?php echo htmlspecialchars($user['username']); ?></span>
        </div>
        <a class="flex items-center gap-sm px-md py-sm text-on-surface-variant hover:bg-secondary-fixed-dim transition-colors" href="#" onclick="toggleSettings()">
            <span class="material-symbols-outlined">settings</span>
            <span class="font-label text-xs">Settings</span>
        </a>
        <a class="flex items-center gap-sm px-md py-sm text-on-surface-variant hover:bg-secondary-fixed-dim transition-colors" href="logout.php">
            <span class="material-symbols-outlined">logout</span>
            <span class="font-label text-xs">Logout</span>
        </a>
    </div>
</aside>

<!-- MOBILE HAMBURGER -->
<button id="mobileMenuBtn" class="md:hidden fixed top-3 left-3 z-50 bg-primary text-on-primary p-2 y2k-border y2k-shadow-sm active-btn" onclick="toggleMobileMenu()">
    <span class="material-symbols-outlined">menu</span>
</button>

<main class="flex flex-1 h-screen">

    <!-- CONVERSATION LIST -->
    <section id="convList" class="conv-list w-80 border-r-2 border-primary flex flex-col bg-[#f4f3f7] flex-shrink-0">
        <div class="p-md bg-primary text-on-primary y2k-border flex items-center justify-between m-sm y2k-shadow-sm">
            <span class="font-label text-xs uppercase tracking-widest">Inbox</span>
            <button onclick="openSearchModal()" class="text-on-primary hover:opacity-80">
                <span class="material-symbols-outlined">search</span>
            </button>
        </div>

        <div id="convSearch" class="px-sm pb-sm">
            <input type="text" id="convSearchInput" placeholder="Search conversations..." oninput="filterConversations(this.value)"
                class="w-full h-10 px-md font-body bg-white y2k-border focus:outline-none focus:shadow-[3px_3px_0px_0px_#cfe5ff] transition-all text-sm">
        </div>

        <div id="conversationsList" class="overflow-y-auto flex-grow px-sm scrollbar-thin">
            <div class="text-center py-xl opacity-50 font-label text-xs">Loading conversations...</div>
        </div>
    </section>

    <!-- MAIN CHAT AREA -->
    <section id="chatArea" class="chat-area flex-grow flex flex-col bg-[#efeeda]">
        <!-- Chat Header -->
        <header id="chatHeader" class="h-16 flex items-center justify-between px-md bg-white border-b-2 border-primary">
            <div class="flex items-center gap-sm">
                <div id="chatAvatarPlaceholder" class="w-10 h-10 y2k-border bg-surface-container-high flex items-center justify-center">
                    <span class="material-symbols-outlined text-on-surface-variant">person</span>
                </div>
                <img id="chatAvatar" class="w-10 h-10 y2k-border hidden" src="" alt="">
                <div>
                    <h2 id="chatName" class="font-headline text-lg">Select a conversation</h2>
                    <div id="chatPresence" class="flex items-center gap-1">
                        <span id="presenceDot" class="w-2 h-2 bg-gray-400 rounded-full"></span>
                        <span id="presenceText" class="font-label text-[10px] opacity-70">Offline</span>
                    </div>
                </div>
            </div>
            <div id="chatActions" class="flex items-center gap-sm hidden">
                <button onclick="openUserProfile()" class="p-sm hover:bg-secondary-fixed-dim y2k-border active-btn bg-white" title="View profile">
                    <span class="material-symbols-outlined">info</span>
                </button>
            </div>
        </header>

        <!-- Messages Area -->
        <div id="messagesContainer" class="flex-grow overflow-y-auto p-lg flex flex-col gap-md scrollbar-thin">
            <div class="flex-1 flex items-center justify-center opacity-40">
                <div class="text-center">
                    <span class="material-symbols-outlined text-6xl">forum</span>
                    <p class="font-label text-sm mt-md">Select a conversation to start messaging</p>
                </div>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div id="typingIndicator" class="px-md py-xs bg-white border-t border-primary hidden">
            <div class="flex items-center gap-sm font-label text-xs opacity-60">
                <div class="flex gap-[2px]">
                    <span class="typing-dot w-2 h-2 bg-primary rounded-full inline-block"></span>
                    <span class="typing-dot w-2 h-2 bg-primary rounded-full inline-block"></span>
                    <span class="typing-dot w-2 h-2 bg-primary rounded-full inline-block"></span>
                </div>
                <span id="typingText">Someone is typing...</span>
            </div>
        </div>

        <!-- Input Bar -->
        <footer id="chatInputBar" class="p-md bg-white border-t-2 border-primary hidden">
            <div class="flex items-center gap-sm">
                <button onclick="document.getElementById('fileUpload').click()" class="p-sm hover:bg-secondary-fixed-dim y2k-border active-btn" title="Attach file">
                    <span class="material-symbols-outlined">attach_file</span>
                </button>
                <input type="file" id="fileUpload" accept="image/*,.pdf,.doc,.docx,.zip" class="hidden" onchange="handleFileUpload(event)">

                <div class="flex-grow relative">
                    <input type="text" id="messageInput" placeholder="Type a message..."
                        onkeydown="handleInputKeydown(event)"
                        oninput="handleTyping()"
                        class="w-full h-12 px-md font-body bg-white y2k-border focus:ring-0 focus:outline-none focus:shadow-[3px_3px_0px_0px_#cfe5ff] transition-all text-sm">
                    <button onclick="toggleEmojiPicker()" class="absolute right-3 top-1/2 -translate-y-1/2 text-primary opacity-50 hover:opacity-100">
                        <span class="material-symbols-outlined">mood</span>
                    </button>
                </div>

                <!-- Emoji Picker -->
                <div id="emojiPicker" class="absolute bottom-20 right-24 bg-white y2k-border y2k-shadow-lg p-sm z-50 flex-wrap gap-1 w-72 h-48 overflow-y-auto">
                </div>

                <button id="sendBtn" onclick="sendMessage()" class="h-12 px-xl bg-primary text-on-primary font-headline text-sm y2k-shadow-sm active-btn flex items-center gap-xs">
                    <span>SEND</span>
                    <span class="material-symbols-outlined" style="font-size:18px;">send</span>
                </button>
            </div>
        </footer>
    </section>

</main>

<!-- INVITE MODAL -->
<div id="inviteModal" class="modal-overlay fixed inset-0 bg-black/50 z-50 items-center justify-center" onclick="if(event.target===this)closeInviteModal()">
    <div class="bg-surface y2k-border y2k-shadow-lg w-full max-w-lg mx-4 max-h-[80vh] flex flex-col">
        <div class="bg-primary text-on-primary px-md py-sm flex items-center justify-between">
            <span class="font-label text-xs uppercase tracking-widest">Invite Friends</span>
            <button onclick="closeInviteModal()" class="text-on-primary hover:opacity-80">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-md">
            <input type="text" id="inviteSearchInput" placeholder="Search users by username..."
                oninput="searchUsers(this.value)"
                class="w-full h-12 px-md font-body bg-white y2k-border focus:outline-none focus:shadow-[3px_3px_0px_0px_#cfe5ff] transition-all text-sm">
        </div>
        <div id="inviteResults" class="overflow-y-auto flex-grow px-md pb-md scrollbar-thin">
            <div class="text-center py-xl opacity-40 font-label text-xs">Type to search users...</div>
        </div>
    </div>
</div>

<!-- REQUESTS MODAL -->
<div id="requestsModal" class="modal-overlay fixed inset-0 bg-black/50 z-50 items-center justify-center" onclick="if(event.target===this)closeRequestsModal()">
    <div class="bg-surface y2k-border y2k-shadow-lg w-full max-w-lg mx-4 max-h-[80vh] flex flex-col">
        <div class="bg-primary text-on-primary px-md py-sm flex items-center justify-between">
            <span class="font-label text-xs uppercase tracking-widest">Pending Requests</span>
            <button onclick="closeRequestsModal()" class="text-on-primary hover:opacity-80">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div id="requestsTabs" class="flex border-b-2 border-primary">
            <button class="flex-1 py-sm px-md font-label text-xs uppercase tracking-widest bg-secondary-container text-on-secondary-container y2k-border" data-tab="received" onclick="switchRequestsTab('received')">Received</button>
            <button class="flex-1 py-sm px-md font-label text-xs uppercase tracking-widest hover:bg-surface-container-high" data-tab="sent" onclick="switchRequestsTab('sent')">Sent</button>
        </div>
        <div id="requestsList" class="overflow-y-auto flex-grow px-md py-md scrollbar-thin">
        </div>
    </div>
</div>

<!-- SEARCH MESSAGES MODAL -->
<div id="searchModal" class="modal-overlay fixed inset-0 bg-black/50 z-50 items-center justify-center" onclick="if(event.target===this)closeSearchModal()">
    <div class="bg-surface y2k-border y2k-shadow-lg w-full max-w-lg mx-4 max-h-[80vh] flex flex-col">
        <div class="bg-primary text-on-primary px-md py-sm flex items-center justify-between">
            <span class="font-label text-xs uppercase tracking-widest">Search Messages</span>
            <button onclick="closeSearchModal()" class="text-on-primary hover:opacity-80">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-md">
            <input type="text" id="globalSearchInput" placeholder="Search all messages..."
                oninput="searchMessages(this.value)"
                class="w-full h-12 px-md font-body bg-white y2k-border focus:outline-none focus:shadow-[3px_3px_0px_0px_#cfe5ff] transition-all text-sm">
        </div>
        <div id="searchResults" class="overflow-y-auto flex-grow px-md pb-md scrollbar-thin">
            <div class="text-center py-xl opacity-40 font-label text-xs">Search across all your conversations</div>
        </div>
    </div>
</div>

<!-- SETTINGS MODAL -->
<div id="settingsModal" class="modal-overlay fixed inset-0 bg-black/50 z-50 items-center justify-center" onclick="if(event.target===this)closeSettings()">
    <div class="bg-surface y2k-border y2k-shadow-lg w-full max-w-md mx-4 flex flex-col">
        <div class="bg-primary text-on-primary px-md py-sm flex items-center justify-between">
            <span class="font-label text-xs uppercase tracking-widest">Settings</span>
            <button onclick="closeSettings()" class="text-on-primary hover:opacity-80">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-md space-y-md">
            <div>
                <label class="font-label text-xs opacity-70 block mb-xs">Display Name</label>
                <input type="text" id="settingsName" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" class="w-full h-10 px-md font-body bg-white y2k-border focus:outline-none focus:shadow-[3px_3px_0px_0px_#cfe5ff] transition-all text-sm">
            </div>
            <div>
                <label class="font-label text-xs opacity-70 block mb-xs">Bio</label>
                <textarea id="settingsBio" rows="3" class="w-full p-md font-body bg-white y2k-border focus:outline-none focus:shadow-[3px_3px_0px_0px_#cfe5ff] transition-all text-sm resize-none"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            </div>
            <button onclick="saveProfile()" class="w-full bg-primary text-on-primary font-headline py-sm y2k-shadow-sm active-btn">Save Changes</button>
        </div>
    </div>
</div>

<script>
// ============================================
// STATE
// ============================================
const STATE = {
    userId: <?php echo $userId; ?>,
    username: '<?php echo addslashes($username); ?>',
    jwtToken: '<?php echo $jwtToken; ?>',
    currentConversationId: null,
    currentOtherUserId: null,
    conversations: [],
    messages: [],
    typingTimeout: null,
    isTyping: false,
    pollInterval: null,
    typingPollInterval: null,
    loadMoreBefore: null,
    loadingMore: false,
    hasMore: true,
    selectedMessageId: null,
    replyToId: null,
    currentView: 'all'
};

// ============================================
// INIT
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    loadConversations();
    loadPendingRequests();
    initEmojiPicker();
    initPolling();
    initSSE();
    loadNotifications();
});

// ============================================
// NAVIGATION
// ============================================
function switchView(view) {
    STATE.currentView = view;
    document.querySelectorAll('.nav-item').forEach(el => {
        el.classList.remove('bg-secondary-container', 'text-on-secondary-container', 'y2k-shadow-sm', 'border-2', 'border-primary');
        el.classList.add('text-on-surface-variant');
    });
    const active = document.querySelector(`.nav-item[data-view="${view}"]`);
    if (active) {
        active.classList.add('bg-secondary-container', 'text-on-secondary-container', 'y2k-shadow-sm', 'border-2', 'border-primary');
        active.classList.remove('text-on-surface-variant');
    }
    loadConversations();
}

function toggleMobileMenu() {
    document.getElementById('sidebarNav').classList.toggle('mobile-open');
}

// ============================================
// CONVERSATIONS
// ============================================
function loadConversations() {
    fetch('/ScrewTheOpinion/api/get_conversations.php', {
        headers: { 'Authorization': 'Bearer ' + STATE.jwtToken }
    })
    .then(r => r.json())
    .then(convs => {
        STATE.conversations = convs;
        renderConversations(convs);
    })
    .catch(() => {});
}

function renderConversations(convs) {
    const container = document.getElementById('conversationsList');
    const view = STATE.currentView;
    const searchTerm = document.getElementById('convSearchInput').value.toLowerCase().trim();

    let filtered = convs;
    if (searchTerm) {
        filtered = filtered.filter(c =>
            (c.name && c.name.toLowerCase().includes(searchTerm)) ||
            (c.last_message && c.last_message.toLowerCase().includes(searchTerm))
        );
    }
    if (view === 'unread') filtered = filtered.filter(c => c.unread_count > 0);
    if (view === 'groups') filtered = filtered.filter(c => c.type === 'group');
    if (view === 'archived') filtered = filtered.filter(c => c.is_archived);

    if (filtered.length === 0) {
        container.innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs">No conversations found</div>';
        return;
    }

    container.innerHTML = filtered.map(c => {
        const isActive = c.id === STATE.currentConversationId;
        const avatar = c.type === 'direct' ? (c.other_user?.avatar || null) : (c.group_avatar || null);
        const name = c.type === 'direct' ? (c.other_user?.name || c.name || 'Unknown') : (c.group_name || 'Group');
        const presence = c.presence || 'offline';
        const presenceColor = presence === 'online' ? 'bg-green-500' : presence === 'away' ? 'bg-orange-400' : 'bg-gray-400';
        const time = c.last_message_time ? formatTime(c.last_message_time) : '';
        const unread = c.unread_count || 0;

        return `<div class="conv-item flex items-center gap-sm p-sm ${isActive ? 'bg-secondary-container y2k-border y2k-shadow-sm' : 'bg-white hover:bg-surface-container-high border-b-2 border-primary'} mb-xs cursor-pointer active-btn"
                    onclick="selectConversation(${c.id})" data-id="${c.id}">
            <div class="relative flex-shrink-0">
                ${avatar
                    ? `<img src="${avatar}" class="w-12 h-12 y2k-border object-cover" alt="">`
                    : `<div class="w-12 h-12 y2k-border bg-surface-container-high flex items-center justify-center">
                        <span class="material-symbols-outlined text-on-surface-variant">${c.type === 'group' ? 'group' : 'person'}</span>
                       </div>`
                }
                <div class="absolute bottom-0 right-0 w-3 h-3 ${presenceColor} border-2 border-primary rounded-full"></div>
            </div>
            <div class="flex-grow min-w-0">
                <div class="flex justify-between items-center">
                    <span class="font-headline text-sm truncate">${escapeHtml(name)}</span>
                    <span class="font-label text-[10px] opacity-60 flex-shrink-0">${time}</span>
                </div>
                <p class="font-body text-xs truncate opacity-60">${c.last_message ? escapeHtml(c.last_message) : (c.type === 'group' ? `${c.member_count || 0} members` : 'No messages yet')}</p>
            </div>
            ${unread > 0 ? `<div class="bg-primary text-on-primary font-label text-[10px] px-1.5 py-0.5 y2k-border flex-shrink-0">${unread > 99 ? '99+' : unread}</div>` : ''}
        </div>`;
    }).join('');
}

function filterConversations(term) {
    renderConversations(STATE.conversations);
}

// ============================================
// SELECT CONVERSATION
// ============================================
function selectConversation(convId) {
    STATE.currentConversationId = convId;
    STATE.loadMoreBefore = null;
    STATE.hasMore = true;
    STATE.replyToId = null;

    // Update active state in list
    document.querySelectorAll('.conv-item').forEach(el => {
        const isActive = parseInt(el.dataset.id) === convId;
        el.className = `conv-item flex items-center gap-sm p-sm ${isActive ? 'bg-secondary-container y2k-border y2k-shadow-sm' : 'bg-white hover:bg-surface-container-high border-b-2 border-primary'} mb-xs cursor-pointer active-btn`;
    });

    const conv = STATE.conversations.find(c => c.id === convId);
    if (!conv) return;

    // Update header
    const name = conv.type === 'direct' ? (conv.other_user?.name || 'Unknown') : (conv.group_name || 'Group');
    const avatar = conv.type === 'direct' ? (conv.other_user?.avatar || null) : (conv.group_avatar || null);
    const presence = conv.presence || 'offline';

    document.getElementById('chatName').textContent = name;
    document.getElementById('chatHeader').classList.remove('hidden');
    document.getElementById('chatInputBar').classList.remove('hidden');
    document.getElementById('chatActions').classList.remove('hidden');

    if (avatar) {
        document.getElementById('chatAvatar').src = avatar;
        document.getElementById('chatAvatar').classList.remove('hidden');
        document.getElementById('chatAvatarPlaceholder').classList.add('hidden');
    } else {
        document.getElementById('chatAvatar').classList.add('hidden');
        document.getElementById('chatAvatarPlaceholder').classList.remove('hidden');
    }

    updatePresenceUI(presence, conv.last_seen);

    // Set other user ID
    STATE.currentOtherUserId = conv.other_user?.id || null;

    // Show chat area on mobile
    document.getElementById('convList').classList.add('hide');
    document.getElementById('chatArea').classList.add('show');

    // Load messages
    loadMessages(true);

    // Scroll to top for load-more detection
    const container = document.getElementById('messagesContainer');
    container.scrollTop = container.scrollHeight;
}

function updatePresenceUI(status, lastSeen) {
    const dot = document.getElementById('presenceDot');
    const text = document.getElementById('presenceText');
    dot.className = 'w-2 h-2 rounded-full ' +
        (status === 'online' ? 'bg-green-500' : status === 'away' ? 'bg-orange-400' : 'bg-gray-400');
    text.textContent = status === 'online' ? 'Online' : status === 'away' ? 'Away' : lastSeen ? 'Last seen ' + formatTime(lastSeen) : 'Offline';
}

// ============================================
// MESSAGES
// ============================================
function loadMessages(reset = false) {
    if (!STATE.currentConversationId) return;

    const url = `/ScrewTheOpinion/api/get_messages.php?conversation_id=${STATE.currentConversationId}${STATE.loadMoreBefore ? '&before=' + STATE.loadMoreBefore : ''}&limit=50`;

    fetch(url, { headers: { 'Authorization': 'Bearer ' + STATE.jwtToken } })
    .then(r => r.json())
    .then(data => {
        const msgs = data.messages || [];
        STATE.hasMore = data.has_more;

        if (reset) {
            STATE.messages = msgs;
        } else {
            STATE.messages = [...msgs, ...STATE.messages];
        }

        if (msgs.length > 0) {
            STATE.loadMoreBefore = msgs[0].id;
        }

        renderMessages(reset);
    })
    .catch(() => {});
}

function renderMessages(scrollToBottom = true) {
    const container = document.getElementById('messagesContainer');

    if (STATE.messages.length === 0) {
        container.innerHTML = '<div class="flex-1 flex items-center justify-center opacity-40"><p class="font-label text-sm">No messages yet. Say hello!</p></div>';
        return;
    }

    let html = '';
    let lastDate = null;

    STATE.messages.forEach((m, i) => {
        if (m.is_deleted) {
            html += `<div class="text-center opacity-40 font-label text-[10px] py-xs"><em>This message was deleted</em></div>`;
            return;
        }

        const msgDate = m.created_at ? m.created_at.split(' ')[0] : '';
        if (msgDate && msgDate !== lastDate) {
            html += `<div class="flex justify-center"><span class="px-md py-xs bg-primary text-on-primary font-label text-[10px] y2k-border">${formatDate(msgDate)}</span></div>`;
            lastDate = msgDate;
        }

        const isMine = m.is_mine || m.sender_id === STATE.userId;
        const reactions = m.reactions || [];
        const reactionsHtml = reactions.length > 0
            ? `<div class="flex gap-1 mt-1 flex-wrap">${reactions.map(r => `<span class="text-sm cursor-pointer" onclick="toggleReaction(${m.id}, '${r.reaction}')">${r.reaction}</span>`).join('')}</div>`
            : '';

        const editedBadge = m.is_edited ? '<span class="font-label text-[8px] opacity-40 ml-1">(edited)</span>' : '';

        html += `<div class="flex items-start gap-sm max-w-[85%] ${isMine ? 'self-end flex-row-reverse' : ''} msg-${isMine ? 'out' : 'in'}">
            ${!isMine ? `<div class="w-8 h-8 y2k-border flex-shrink-0 bg-surface-container-high flex items-center justify-center overflow-hidden">
                ${m.sender_avatar ? `<img src="${m.sender_avatar}" class="w-full h-full object-cover">` : `<span class="material-symbols-outlined text-sm">person</span>`}
            </div>` : ''}
            <div class="${isMine ? 'bg-primary text-on-primary' : 'bg-[#7c9cbf] text-primary'} p-md y2k-border ${isMine ? '' : 'y2k-shadow-sm'} relative group max-w-full">
                ${!isMine ? `<p class="font-label text-[9px] opacity-70 mb-1">${m.sender_username || 'Unknown'}</p>` : ''}
                ${m.reply_to ? `<div class="text-xs opacity-70 border-l-2 border-current pl-2 mb-1">${m.reply_to.sender_username}: ${m.reply_to.content}</div>` : ''}
                ${m.type === 'text'
                    ? `<p class="font-body text-sm whitespace-pre-wrap break-words">${escapeHtml(m.content || '')}${editedBadge}</p>`
                    : m.type === 'image' || m.type === 'gif'
                        ? `<img src="${m.content}" class="max-w-full max-h-64 y2k-border cursor-pointer" onclick="window.open('${m.content}','_blank')" loading="lazy">`
                        : m.type === 'file'
                            ? `<a href="${m.content}" target="_blank" class="flex items-center gap-sm text-sm underline"><span class="material-symbols-outlined text-sm">attach_file</span>${m.content.split('/').pop()}</a>`
                            : m.type === 'voice'
                                ? `<audio controls src="${m.content}" class="w-full max-w-[200px] h-10"></audio>`
                                : `<p class="font-body text-sm italic opacity-70">${escapeHtml(m.content || '')}</p>`
                }
                <div class="flex items-center justify-between mt-1">
                    <span class="font-label text-[9px] opacity-50">${m.created_at ? formatTime(m.created_at) : ''}</span>
                    <div class="flex gap-1">
                        <button onclick="toggleReaction(${m.id}, '❤️')" class="opacity-0 group-hover:opacity-100 hover:opacity-100 transition-opacity text-xs ${isMine ? 'text-on-primary' : 'text-primary'}">❤️</button>
                        <button onclick="setReplyTo(${m.id})" class="opacity-0 group-hover:opacity-100 hover:opacity-100 transition-opacity text-xs ${isMine ? 'text-on-primary' : 'text-primary'}">↩️</button>
                        ${isMine ? `<button onclick="editMessage(${m.id})" class="opacity-0 group-hover:opacity-100 hover:opacity-100 transition-opacity text-xs">✏️</button>
                                    <button onclick="deleteMessage(${m.id})" class="opacity-0 group-hover:opacity-100 hover:opacity-100 transition-opacity text-xs">🗑️</button>` : ''}
                    </div>
                </div>
                ${reactionsHtml}
            </div>
        </div>`;
    });

    container.innerHTML = html;

    if (scrollToBottom) {
        setTimeout(() => { container.scrollTop = container.scrollHeight; }, 50);
    }

    // Load more on scroll to top
    container.onscroll = () => {
        if (container.scrollTop < 50 && STATE.hasMore && !STATE.loadingMore) {
            STATE.loadingMore = true;
            const prevHeight = container.scrollHeight;
            loadMessages(false);
            setTimeout(() => {
                STATE.loadingMore = false;
            }, 500);
        }
    };
}

// ============================================
// SEND MESSAGE
// ============================================
function sendMessage() {
    const input = document.getElementById('messageInput');
    const content = input.value.trim();
    if (!content || !STATE.currentConversationId) return;

    const body = {
        conversation_id: STATE.currentConversationId,
        content: content,
        type: 'text'
    };
    if (STATE.replyToId) {
        body.reply_to_id = STATE.replyToId;
        STATE.replyToId = null;
        document.getElementById('replyIndicator')?.remove();
    }

    fetch('/ScrewTheOpinion/api/send_message.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + STATE.jwtToken,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        if (data.message) {
            STATE.messages.push(data.message);
            renderMessages(true);
        }
        input.value = '';
        input.style.height = '48px';
    })
    .catch(() => {});

    // Broadcast via SSE/polling
    broadcastTyping(false);
}

function handleInputKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

// ============================================
// TYPING INDICATOR
// ============================================
function handleTyping() {
    if (!STATE.isTyping) {
        STATE.isTyping = true;
        broadcastTyping(true);
    }
    clearTimeout(STATE.typingTimeout);
    STATE.typingTimeout = setTimeout(() => {
        STATE.isTyping = false;
        broadcastTyping(false);
    }, 2000);
}

function broadcastTyping(isTyping) {
    if (!STATE.currentConversationId) return;
    fetch('/ScrewTheOpinion/api/typing.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + STATE.jwtToken,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            conversation_id: STATE.currentConversationId,
            typing: isTyping
        })
    }).catch(() => {});
}

// ============================================
// REACTIONS
// ============================================
function toggleReaction(messageId, reaction) {
    if (!STATE.currentConversationId) return;

    const msg = STATE.messages.find(m => m.id === messageId);
    const existingReaction = msg?.reactions?.find(r => r.user_id === STATE.userId && r.reaction === reaction);

    fetch('/ScrewTheOpinion/api/react_message.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + STATE.jwtToken,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            message_id: messageId,
            reaction: reaction,
            remove: !!existingReaction
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.message_id) {
            // Update local state
            if (msg) msg.reactions = data.reactions;
            renderMessages(false);
        }
    })
    .catch(() => {});
}

// ============================================
// REPLY
// ============================================
function setReplyTo(messageId) {
    STATE.replyToId = messageId;
    const msg = STATE.messages.find(m => m.id === messageId);

    // Remove existing reply indicator
    document.getElementById('replyIndicator')?.remove();

    const indicator = document.createElement('div');
    indicator.id = 'replyIndicator';
    indicator.className = 'flex items-center gap-sm px-md py-xs bg-surface-container border-b border-primary';
    indicator.innerHTML = `
        <span class="material-symbols-outlined text-sm text-on-surface-variant">reply</span>
        <span class="flex-1 font-label text-xs opacity-70 truncate">Replying to ${msg?.sender_username || 'Unknown'}: ${msg?.content?.substring(0, 50) || ''}</span>
        <button onclick="STATE.replyToId=null;this.parentElement.remove()" class="text-on-surface-variant hover:text-error">
            <span class="material-symbols-outlined text-sm">close</span>
        </button>
    `;
    document.getElementById('chatInputBar').before(indicator);
    document.getElementById('messageInput').focus();
}

// ============================================
// EDIT / DELETE
// ============================================
function editMessage(messageId) {
    const msg = STATE.messages.find(m => m.id === messageId);
    if (!msg) return;

    const newContent = prompt('Edit your message:', msg.content || '');
    if (!newContent || newContent === msg.content) return;

    fetch('/ScrewTheOpinion/api/edit_message.php', {
        method: 'PUT',
        headers: {
            'Authorization': 'Bearer ' + STATE.jwtToken,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ message_id: messageId, content: newContent })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'edited') {
            msg.content = data.content;
            msg.is_edited = true;
            renderMessages(false);
        }
    })
    .catch(() => {});
}

function deleteMessage(messageId) {
    if (!confirm('Delete this message?')) return;

    fetch('/ScrewTheOpinion/api/delete_message.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + STATE.jwtToken,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ message_id: messageId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'deleted') {
            const idx = STATE.messages.findIndex(m => m.id === messageId);
            if (idx > -1) STATE.messages[idx].is_deleted = true;
            renderMessages(false);
        }
    })
    .catch(() => {});
}

// ============================================
// FILE UPLOAD
// ============================================
function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file || !STATE.currentConversationId) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', file.type.startsWith('image/') ? 'image' : 'file');
    formData.append('conversation_id', STATE.currentConversationId);

    fetch('/ScrewTheOpinion/api/upload.php', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + STATE.jwtToken },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.file_path) {
            // Send as message
            const msgType = data.type === 'image' ? 'image' : 'file';
            return fetch('/ScrewTheOpinion/api/send_message.php', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + STATE.jwtToken,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    conversation_id: STATE.currentConversationId,
                    content: data.url || data.file_path,
                    type: msgType
                })
            });
        }
    })
    .then(r => r?.json())
    .then(data => {
        if (data?.message) {
            STATE.messages.push(data.message);
            renderMessages(true);
        }
    })
    .catch(() => {});
}

// ============================================
// INVITE / REQUEST SYSTEM
// ============================================
function openInviteModal() {
    document.getElementById('inviteModal').classList.add('active');
    document.getElementById('inviteSearchInput').value = '';
    document.getElementById('inviteResults').innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs">Type to search users...</div>';
    document.getElementById('inviteSearchInput').focus();
}

function closeInviteModal() {
    document.getElementById('inviteModal').classList.remove('active');
}

function searchUsers(query) {
    if (query.length < 1) {
        document.getElementById('inviteResults').innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs">Type to search users...</div>';
        return;
    }

    document.getElementById('inviteResults').innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs"><div class="skeleton h-12 w-full mb-xs"></div><div class="skeleton h-12 w-full mb-xs"></div></div>';

    fetch('/ScrewTheOpinion/api/search_users.php?q=' + encodeURIComponent(query), {
        headers: { 'Authorization': 'Bearer ' + STATE.jwtToken }
    })
    .then(r => r.json())
    .then(users => {
        if (users.length === 0) {
            document.getElementById('inviteResults').innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs">No users found</div>';
            return;
        }

        document.getElementById('inviteResults').innerHTML = users.map(u => {
            const presenceColor = u.presence === 'online' ? 'bg-green-500' : u.presence === 'away' ? 'bg-orange-400' : 'bg-gray-400';
            let actionBtn = '';

            if (u.relationship === 'contacts') {
                actionBtn = `<span class="font-label text-[10px] opacity-50">Connected</span>`;
            } else if (u.request_status === 'pending') {
                actionBtn = `<span class="font-label text-[10px] opacity-50">Request Pending</span>`;
            } else if (u.request_status === 'rejected') {
                actionBtn = `<button onclick="sendInvite(${u.id})" class="px-sm py-xs bg-primary text-on-primary font-label text-[10px] y2k-border active-btn">Invite</button>`;
            } else {
                actionBtn = `<button onclick="sendInvite(${u.id})" class="px-sm py-xs bg-primary text-on-primary font-label text-[10px] y2k-border active-btn">Invite</button>`;
            }

            return `<div class="flex items-center gap-sm p-sm bg-white hover:bg-surface-container-high border-b-2 border-primary">
                <div class="relative flex-shrink-0">
                    <div class="w-10 h-10 y2k-border bg-surface-container-high flex items-center justify-center">
                        <span class="material-symbols-outlined text-sm">person</span>
                    </div>
                    <div class="absolute bottom-0 right-0 w-2.5 h-2.5 ${presenceColor} border-2 border-primary rounded-full"></div>
                </div>
                <div class="flex-grow">
                    <span class="font-headline text-sm">${escapeHtml(u.name || u.username)}</span>
                    <span class="font-label text-[10px] opacity-50 ml-1">@${escapeHtml(u.username)}</span>
                </div>
                ${actionBtn}
            </div>`;
        }).join('');
    })
    .catch(() => {
        document.getElementById('inviteResults').innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs">Error loading users</div>';
    });
}

function sendInvite(receiverId) {
    fetch('/ScrewTheOpinion/api/send_invite.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + STATE.jwtToken,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ receiver_id: receiverId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'invite_sent') {
            // Refresh search results
            const q = document.getElementById('inviteSearchInput').value;
            searchUsers(q);
            loadPendingRequests();
        } else {
            alert(data.message || 'Error sending request');
        }
    })
    .catch(() => alert('Error sending request'));
}

// ============================================
// REQUESTS MANAGEMENT
// ============================================
let requestsTab = 'received';

function openRequestsModal() {
    document.getElementById('requestsModal').classList.add('active');
    switchRequestsTab('received');
}

function closeRequestsModal() {
    document.getElementById('requestsModal').classList.remove('active');
}

function switchRequestsTab(tab) {
    requestsTab = tab;
    document.querySelectorAll('#requestsTabs button').forEach(btn => {
        if (btn.dataset.tab === tab) {
            btn.classList.add('bg-secondary-container', 'text-on-secondary-container', 'y2k-border');
            btn.classList.remove('hover:bg-surface-container-high');
        } else {
            btn.classList.remove('bg-secondary-container', 'text-on-secondary-container', 'y2k-border');
            btn.classList.add('hover:bg-surface-container-high');
        }
    });
    loadRequests(tab);
}

function loadRequests(tab) {
    const container = document.getElementById('requestsList');
    container.innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs">Loading...</div>';

    fetch('/ScrewTheOpinion/api/get_invites.php?type=' + tab, {
        headers: { 'Authorization': 'Bearer ' + STATE.jwtToken }
    })
    .then(r => r.json())
    .then(invites => {
        if (invites.length === 0) {
            container.innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs">No ' + tab + ' requests</div>';
            return;
        }

        container.innerHTML = invites.map(inv => {
            const presenceColor = inv.presence === 'online' ? 'bg-green-500' : inv.presence === 'away' ? 'bg-orange-400' : 'bg-gray-400';
            const actions = tab === 'received'
                ? `<div class="flex gap-xs">
                    <button onclick="processRequest(${inv.id}, 'accept')" class="px-sm py-xs bg-primary text-on-primary font-label text-[10px] y2k-border active-btn">Accept</button>
                    <button onclick="processRequest(${inv.id}, 'reject')" class="px-sm py-xs bg-error text-on-error font-label text-[10px] y2k-border active-btn">Reject</button>
                   </div>`
                : `<button onclick="cancelRequest(${inv.id})" class="px-sm py-xs bg-error text-on-error font-label text-[10px] y2k-border active-btn">Cancel</button>`;

            return `<div class="flex items-center gap-sm p-sm bg-white border-b-2 border-primary">
                <div class="relative flex-shrink-0">
                    <div class="w-10 h-10 y2k-border bg-surface-container-high flex items-center justify-center">
                        <span class="material-symbols-outlined text-sm">person</span>
                    </div>
                    <div class="absolute bottom-0 right-0 w-2.5 h-2.5 ${presenceColor} border-2 border-primary rounded-full"></div>
                </div>
                <div class="flex-grow">
                    <span class="font-headline text-sm">${escapeHtml(inv.name || inv.username)}</span>
                    <span class="font-label text-[10px] opacity-50 ml-1">@${escapeHtml(inv.username)}</span>
                </div>
                ${actions}
            </div>`;
        }).join('');
    })
    .catch(() => {
        container.innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs">Error loading requests</div>';
    });
}

function processRequest(inviteId, action) {
    fetch('/ScrewTheOpinion/api/accept_invite.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + STATE.jwtToken,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ invite_id: inviteId, action: action })
    })
    .then(r => r.json())
    .then(data => {
        loadRequests(requestsTab);
        loadConversations();
        loadPendingRequests();
    })
    .catch(() => {});
}

function cancelRequest(inviteId) {
    fetch('/ScrewTheOpinion/api/cancel_invite.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + STATE.jwtToken,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ invite_id: inviteId })
    })
    .then(r => r.json())
    .then(data => {
        loadRequests(requestsTab);
        loadPendingRequests();
    })
    .catch(() => {});
}

function loadPendingRequests() {
    fetch('/ScrewTheOpinion/api/get_invites.php?type=received', {
        headers: { 'Authorization': 'Bearer ' + STATE.jwtToken }
    })
    .then(r => r.json())
    .then(invites => {
        const badge = document.getElementById('requestBadge');
        if (invites.length > 0) {
            badge.textContent = invites.length;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    })
    .catch(() => {});
}

// ============================================
// SEARCH MESSAGES
// ============================================
let searchTimeout = null;

function openSearchModal() {
    document.getElementById('searchModal').classList.add('active');
    document.getElementById('globalSearchInput').value = '';
    document.getElementById('searchResults').innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs">Search across all your conversations</div>';
    document.getElementById('globalSearchInput').focus();
}

function closeSearchModal() {
    document.getElementById('searchModal').classList.remove('active');
}

function searchMessages(query) {
    clearTimeout(searchTimeout);
    if (query.length < 2) {
        document.getElementById('searchResults').innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs">Type at least 2 characters</div>';
        return;
    }

    searchTimeout = setTimeout(() => {
        document.getElementById('searchResults').innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs"><div class="skeleton h-12 w-full mb-xs"></div></div>';

        fetch('/ScrewTheOpinion/api/search_messages.php?q=' + encodeURIComponent(query), {
            headers: { 'Authorization': 'Bearer ' + STATE.jwtToken }
        })
        .then(r => r.json())
        .then(results => {
            if (results.length === 0) {
                document.getElementById('searchResults').innerHTML = '<div class="text-center py-xl opacity-40 font-label text-xs">No messages found</div>';
                return;
            }

            document.getElementById('searchResults').innerHTML = results.map(r => {
                const sender = r.sender_id === STATE.userId ? 'You' : escapeHtml(r.sender_username || 'Unknown');
                const convName = escapeHtml(r.conversation_display_name || 'Unknown');
                return `<div class="p-sm bg-white hover:bg-surface-container-high border-b-2 border-primary cursor-pointer active-btn" onclick="selectConversation(${r.conversation_id});closeSearchModal()">
                    <div class="flex justify-between">
                        <span class="font-label text-[10px] opacity-50">${convName}</span>
                        <span class="font-label text-[10px] opacity-50">${r.created_at ? formatTime(r.created_at) : ''}</span>
                    </div>
                    <p class="font-body text-sm"><strong>${sender}:</strong> ${escapeHtml(r.content?.substring(0, 120) || '')}</p>
                </div>`;
            }).join('');
        })
        .catch(() => {});
    }, 300);
}

// ============================================
// EMOJI PICKER
// ============================================
const EMOJIS = ['😀','😃','😄','😁','😅','😂','🤣','😊','😇','🙂','😉','😌','😍','🥰','😘','😗','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🥴','😵','🤯','🤠','🥳','😎','🤓','🧐','😕','😟','🙁','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😤','😡','😠','🤬','💀','☠️','💩','🤡','👹','👺','👻','👽','👾','🤖','😺','😸','😹','😻','😼','😽','🙀','😿','😾','❤️','🧡','💛','💚','💙','💜','🖤','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','👍','👎','👊','✊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','✌️','🤞','🤟','🤘','👌','👉','👆','👇','☝️','✋','💪','🦵','🦶','👂','👃','🧠','👀','👅','👄'];

function initEmojiPicker() {
    const picker = document.getElementById('emojiPicker');
    picker.innerHTML = EMOJIS.map(e =>
        `<button onclick="insertEmoji('${e}')" class="text-lg hover:bg-surface-container-high p-1 rounded active-btn" title="${e}">${e}</button>`
    ).join('');
}

function toggleEmojiPicker() {
    document.getElementById('emojiPicker').classList.toggle('active');
}

function insertEmoji(emoji) {
    const input = document.getElementById('messageInput');
    input.value += emoji;
    input.focus();
    document.getElementById('emojiPicker').classList.remove('active');
}

// ============================================
// SETTINGS
// ============================================
function toggleSettings() {
    document.getElementById('settingsModal').classList.toggle('active');
}

function closeSettings() {
    document.getElementById('settingsModal').classList.remove('active');
}

function saveProfile() {
    const name = document.getElementById('settingsName').value;
    const bio = document.getElementById('settingsBio').value;

    fetch('/ScrewTheOpinion/api/profile.php', {
        method: 'PUT',
        headers: {
            'Authorization': 'Bearer ' + STATE.jwtToken,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ name: name, bio: bio })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'updated') {
            closeSettings();
            loadConversations();
        }
    })
    .catch(() => {});
}

// ============================================
// NOTIFICATIONS
// ============================================
function loadNotifications() {
    fetch('/ScrewTheOpinion/api/notifications.php', {
        headers: { 'Authorization': 'Bearer ' + STATE.jwtToken }
    })
    .then(r => r.json())
    .then(data => {
        if (data.unread_count > 0) {
            // Update page title with unread count
            document.title = `(${data.unread_count}) Messenger — ScrewTheOpinion`;
        }
    })
    .catch(() => {});
}

// ============================================
// REAL-TIME: POLLING (fallback)
// ============================================
function initPolling() {
    // Poll for new messages
    STATE.pollInterval = setInterval(() => {
        if (STATE.currentConversationId) {
            const lastMsg = STATE.messages[STATE.messages.length - 1];
            const url = `/ScrewTheOpinion/api/get_messages.php?conversation_id=${STATE.currentConversationId}&limit=10`;
            fetch(url, { headers: { 'Authorization': 'Bearer ' + STATE.jwtToken } })
            .then(r => r.json())
            .then(data => {
                const msgs = data.messages || [];
                if (msgs.length > 0) {
                    const lastFetched = msgs[msgs.length - 1];
                    const lastLocal = STATE.messages[STATE.messages.length - 1];
                    if (!lastLocal || lastFetched.id !== lastLocal.id) {
                        STATE.messages = msgs;
                        renderMessages(true);
                    }
                }
            })
            .catch(() => {});
        }
        loadConversations();
        loadPendingRequests();
        loadNotifications();
    }, 3000);
}

// ============================================
// REAL-TIME: SSE (preferred)
// ============================================
function initSSE() {
    if (typeof EventSource === 'undefined') return;

    const source = new EventSource('/ScrewTheOpinion/api/sse.php?token=' + STATE.jwtToken);

    source.addEventListener('notification', (e) => {
        try {
            const notifications = JSON.parse(e.data);
            loadNotifications();
            loadConversations();
            if (STATE.currentConversationId) {
                loadMessages(true);
            }
        } catch (err) {}
    });

    source.addEventListener('heartbeat', () => {});

    source.onerror = () => {
        // Reconnect on error
        source.close();
        setTimeout(initSSE, 5000);
    };
}

// ============================================
// HELPERS
// ============================================
function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'Z');
    const now = new Date();
    const diff = now - d;
    const days = Math.floor(diff / 86400000);

    if (days === 0) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } else if (days === 1) {
        return 'Yesterday';
    } else if (days < 7) {
        return d.toLocaleDateString([], { weekday: 'short' });
    } else {
        return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00Z');
    const now = new Date();
    const diff = now - d;
    const days = Math.floor(diff / 86400000);

    if (days === 0) return 'TODAY';
    if (days === 1) return 'YESTERDAY';

    return d.toLocaleDateString([], { month: 'long', day: 'numeric', year: 'numeric' }).toUpperCase();
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Close modals on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeInviteModal();
        closeRequestsModal();
        closeSearchModal();
        closeSettings();
    }
});
</script>
</body>
</html>
