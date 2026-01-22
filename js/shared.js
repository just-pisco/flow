function showToast(message, type = 'success') {
    if (typeof Toastify === 'undefined') {
        console.warn("Toastify not loaded. Fallback alert:", message);
        alert((type === 'error' ? 'Error: ' : '') + message);
        return;
    }
    Toastify({
        text: message,
        duration: 3000,
        gravity: "top", // `top` or `bottom`
        position: "right", // `left`, `center` or `right`
        className: type === 'success' ? "toast-success" : "toast-error",
        stopOnFocus: true, // Prevents dismissing of toast on hover
        style: {
            borderRadius: "8px",
            boxShadow: "0 44px 6px -1px rgba(0, 0, 0, 0.1)",
            fontSize: "14px",
            fontWeight: "600",
            // Fallback colors if classes not defined
            background: type === 'success' ? "linear-gradient(to right, #00b09b, #96c93d)" : "linear-gradient(to right, #ff5f6d, #ffc371)"
        }
    }).showToast();
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const desktopHamburger = document.getElementById('desktopHamburger');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');

    if (!sidebar) return;

    // Detect Mobile vs Desktop
    if (window.innerWidth >= 768) {
        // Desktop Logic: Toggle Width
        sidebar.classList.toggle('desktop-closed');

        // Toggle Button Visibility based on state
        if (desktopHamburger) {
            if (sidebar.classList.contains('desktop-closed')) {
                // Sidebar Closed -> Show Hamburger (Remove md:hidden)
                desktopHamburger.classList.remove('md:hidden');
            } else {
                // Sidebar Open -> Hide Hamburger (Show Close inside Sidebar)
                desktopHamburger.classList.add('md:hidden');
            }
        }

    } else {
        // Mobile Logic
        sidebar.classList.toggle('mobile-closed');

        if (sidebar.classList.contains('mobile-closed')) {
            if (overlay) overlay.classList.add('hidden');
        } else {
            if (overlay) overlay.classList.remove('hidden');
        }
    }
}
// Notification Logic
let lastPendingCount = -1; // -1 means not initialized

function updateFriendsBadge(count) {
    const badge = document.getElementById('sidebarFriendsBadge');
    if (!badge) return;

    if (count > 0) {
        badge.textContent = count;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

async function checkFriendNotifications() {
    try {
        const res = await fetch('api_friends.php?action=pending_count');
        const json = await res.json();
        if (json.success) {
            const currentCount = parseInt(json.count);

            // Update Badge
            updateFriendsBadge(currentCount);

            // Toast Logic
            if (lastPendingCount !== -1 && currentCount > lastPendingCount) {
                showToast(`Hai ${currentCount - lastPendingCount} nuova/e richiesta/e di amicizia!`, 'success');
            }

            // Sync state
            lastPendingCount = currentCount;
        }
    } catch (e) {
        // Silent fail on network error during polling
        console.error("Notification check failed", e);
    }
}

// Start polling
document.addEventListener('DOMContentLoaded', () => {
    // Initial check
    checkFriendNotifications();

    // Poll every 10 seconds
    setInterval(checkFriendNotifications, 10000);
});

function getAvatarHtml(user, sizeClass = 'w-10 h-10', fontSizeClass = '') {
    // Requires user object with: username, profile_image, nome, cognome (optional)

    if (user.profile_image) {
        return `<img src="uploads/avatars/${user.profile_image}" class="${sizeClass} rounded-lg object-cover border border-slate-200 shadow-sm" alt="${user.username}">`;
    }

    // Initials Logic
    let initials = user.username.substring(0, 2).toUpperCase();
    if (user.nome && user.cognome) {
        initials = (user.nome[0] + user.cognome[0]).toUpperCase();
    } else if (user.nome) {
        initials = user.nome.substring(0, 2).toUpperCase();
    }

    const colorClasses = [
        'bg-red-100 text-red-600',
        'bg-orange-100 text-orange-600',
        'bg-amber-100 text-amber-600',
        'bg-green-100 text-green-600',
        'bg-emerald-100 text-emerald-600',
        'bg-teal-100 text-teal-600',
        'bg-cyan-100 text-cyan-600',
        'bg-sky-100 text-sky-600',
        'bg-blue-100 text-blue-600',
        'bg-indigo-100 text-indigo-600',
        'bg-violet-100 text-violet-600',
        'bg-purple-100 text-purple-600',
        'bg-fuchsia-100 text-fuchsia-600',
        'bg-pink-100 text-pink-600',
        'bg-rose-100 text-rose-600'
    ];

    // Deterministic color based on char code sum
    let sum = 0;
    for (let i = 0; i < user.username.length; i++) {
        sum += user.username.charCodeAt(i);
    }
    const colorClass = colorClasses[sum % colorClasses.length];

    return `
        <div class="${sizeClass} rounded-lg ${colorClass} flex items-center justify-center font-bold ${fontSizeClass} border border-white shadow-sm">
            ${initials}
        </div>
    `;
}
