function showToast(message, type = 'success') {
    if (typeof Toastify === 'undefined') {
        console.warn("Toastify not loaded. Fallback alert:", message);
        alert((type === 'error' ? 'Error: ' : '') + message);
        return;
    }

    // Mobile Check
    const isMobile = window.innerWidth < 640;

    Toastify({
        text: message,
        duration: 3000,
        gravity: "top", // `top` or `bottom`
        position: isMobile ? "center" : "right", // `left`, `center` or `right`
        className: type === 'success' ? "toast-success" : "toast-error",
        stopOnFocus: true, // Prevents dismissing of toast on hover
        style: {
            borderRadius: "8px",
            boxShadow: "0 44px 6px -1px rgba(0, 0, 0, 0.1)",
            fontSize: "14px",
            fontWeight: "600",
            zIndex: "9999", // Ensure it's on top of everything
            // Fallback colors if classes not defined
            background: type === 'success' ? "linear-gradient(to right, #00b09b, #96c93d)" : "linear-gradient(to right, #ff5f6d, #ffc371)"
        },
        // Adjust offset for mobile if needed
        offset: {
            x: isMobile ? 0 : 5, // horizontal axis - can be a number or a string indicating unity. eg: '2em'
            y: 10 // vertical axis - can be a number or a string indicating unity. eg: '2em'
        },
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
// Notification Logic
let lastPendingFriendsCount = -1;
let lastUnreadNotifCount = -1;
let currentNotifications = [];

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

function updateNotificationBadge(count) {
    const badge = document.getElementById('headerNotifBadge');
    if (badge) {
        if (count > 0) {
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
}

function renderNotificationsList() {
    const list = document.getElementById('notifList');
    if (!list) return;

    if (currentNotifications.length === 0) {
        list.innerHTML = '<p class="p-4 text-center text-xs text-slate-400">Nessuna notifica.</p>';
        return;
    }

    list.innerHTML = currentNotifications.map(n => `
        <div class="px-4 py-3 hover:bg-slate-50 border-b border-slate-50 last:border-0 cursor-pointer transition-colors" onclick="handleNotifClick(${n.id}, '${n.link || ''}')">
            <p class="text-sm text-slate-800 leading-snug">${n.message}</p>
            <p class="text-[10px] text-slate-400 mt-1">${new Date(n.created_at).toLocaleString()}</p>
        </div>
    `).join('');
}

async function markAllRead() {
    try {
        await fetch('api_notifications.php?action=mark_all_read', { method: 'POST' });
        checkNotifications();
    } catch (e) { console.error(e); }
}

async function handleNotifClick(id, link) {
    try {
        await fetch('api_notifications.php?action=mark_read', {
            method: 'POST',
            body: JSON.stringify({ id: id })
        });
    } catch (e) { }

    if (link) window.location.href = link;
    else checkNotifications();
}

async function loadProjects() {
    const list = document.getElementById('sidebarProjectList');
    if (!list) return;

    // Avoid refreshing if dragging
    if (document.querySelector('.sortable-drag') || document.querySelector('.sortable-ghost')) {
        return;
    }

    try {
        const res = await fetch('api_projects.php?action=list_sidebar');
        const json = await res.json();

        if (json.success) {
            const projects = json.data;
            const currentProjectId = new URLSearchParams(window.location.search).get('project_id');

            // Render
            list.innerHTML = projects.map(p => {
                const isActive = (currentProjectId == p.id);
                // Active class doesn't override border-color if we use inline style correctly? 
                // We use border-l-4. Text-slate-800 for active background.
                const activeClass = isActive ? 'bg-slate-800' : '';
                const borderColor = p.colore || '#6366f1';

                // Escape HTML to prevent XSS
                const safeName = p.nome.replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");

                return `
                    <li data-id='${p.id}' class='hover:bg-slate-800 p-2 rounded-md cursor-pointer transition-colors border-l-4 ${activeClass}' style="border-left-color: ${borderColor};">
                        <a href='index.php?project_id=${p.id}' class='block w-full text-white no-underline flex items-center justify-between'>
                            <span>${safeName}</span>
                        </a>
                    </li>
                `;
            }).join('');
        }
    } catch (e) { console.error(e); }
}

async function checkNotifications() {
    try {
        // 1. Friend Requests
        const resFriends = await fetch('api_collaborators.php?action=pending_count');
        const jsonFriends = await resFriends.json();
        if (jsonFriends.success) {
            const currentCount = parseInt(jsonFriends.count);
            updateFriendsBadge(currentCount);
            if (lastPendingFriendsCount !== -1 && currentCount > lastPendingFriendsCount) {
                showToast(`Hai ${currentCount - lastPendingFriendsCount} nuova/e richiesta/e di amicizia!`, 'success');
            }
            lastPendingFriendsCount = currentCount;
        }

        // 2. Generic Notifications
        const resNotif = await fetch('api_notifications.php?action=get_unread');
        const jsonNotif = await resNotif.json();

        if (jsonNotif.success) {
            currentNotifications = jsonNotif.data; // Store globally
            const count = currentNotifications.length;

            updateNotificationBadge(count);
            renderNotificationsList();

            // Toast Logic
            if (lastUnreadNotifCount !== -1 && count > lastUnreadNotifCount) {
                // Show toast for the latest one
                const latest = currentNotifications[0];
                if (latest) {
                    showToast(latest.message, 'success');
                }
            }
            lastUnreadNotifCount = count;
        }

    } catch (e) {
        console.error("Notification check failed", e);
    }
}

// Start polling
document.addEventListener('DOMContentLoaded', () => {
    checkNotifications();
    loadProjects();
    setInterval(() => {
        checkNotifications();
        loadProjects();
    }, 3000); // Faster polling (3s)
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

// Toggle Dropdown logic
document.addEventListener('click', function (e) {
    const btn = document.getElementById('notifBellBtn');
    const dropdown = document.getElementById('notifDropdown');

    if (btn && dropdown) {
        if (dropdown.contains(e.target)) return;
        if (btn.contains(e.target)) {
            dropdown.classList.toggle('hidden');
        } else {
            dropdown.classList.add('hidden');
        }
    }
});

// --- Project Details Modal Logic ---

let currentDetailProjectId = null;

function openProjectDetails(defaultTab = 'info') {
    if (typeof CURRENT_PROJECT_ID === 'undefined' || !CURRENT_PROJECT_ID) return;
    currentDetailProjectId = CURRENT_PROJECT_ID;
    window.currentProjectId = CURRENT_PROJECT_ID;
    window.currentTaskId = null;

    const modal = document.getElementById('projectDetailsModal');
    const backdrop = document.getElementById('projectDetailsBackdrop');
    const content = document.getElementById('projectDetailsContent');

    modal.classList.remove('hidden');
    // Animate
    requestAnimationFrame(() => {
        backdrop.classList.remove('opacity-0');
        content.classList.remove('opacity-0', 'scale-95');
        content.classList.add('scale-100');
    });

    // Load Data
    loadProjectDetails();
    switchProjectTab(defaultTab);
}

function closeProjectDetailsModal() {
    const modal = document.getElementById('projectDetailsModal');
    const backdrop = document.getElementById('projectDetailsBackdrop');
    const content = document.getElementById('projectDetailsContent');

    backdrop.classList.add('opacity-0');
    content.classList.add('opacity-0', 'scale-95');
    content.classList.remove('scale-100');

    setTimeout(() => {
        modal.classList.add('hidden');
        const desc = document.getElementById('detailProjectDesc');
        if (desc) desc.style.minHeight = '';
    }, 300);
}

function switchProjectTab(tabName) {
    // Hide all
    ['info', 'members', 'attachments'].forEach(t => {
        document.getElementById(`tab-${t}`).classList.add('hidden');
        const btn = document.getElementById(`tabBtn-${t}`);
        btn.classList.remove('border-indigo-600', 'text-indigo-600');
        btn.classList.add('border-transparent', 'text-slate-500');
    });

    // Show selected
    document.getElementById(`tab-${tabName}`).classList.remove('hidden');
    const activeBtn = document.getElementById(`tabBtn-${tabName}`);
    activeBtn.classList.remove('border-transparent', 'text-slate-500');
    activeBtn.classList.add('border-indigo-600', 'text-indigo-600');

    if (tabName === 'members') loadProjectMembers();
    if (tabName === 'members') loadProjectMembers(); // duplicate line removed in replacement? No, just copy-paste error in view?
    if (tabName === 'attachments' && typeof loadProjectAttachments === 'function') {
        loadProjectAttachments(currentDetailProjectId);
    }
}

async function loadProjectDetails() {
    try {
        const res = await fetch(`api_projects.php?action=get_details&project_id=${currentDetailProjectId}`);
        const json = await res.json();
        if (json.success) {
            const p = json.data;
            document.getElementById('detailProjectName').value = p.nome;
            document.getElementById('detailProjectDesc').value = p.descrizione || '';

            const colorInput = document.getElementById('detailProjectColor');
            if (colorInput) colorInput.value = p.colore || '#6366f1';

            // Read-only if not owner?
            const isOwner = (p.role === 'owner');
            document.getElementById('detailProjectName').disabled = !isOwner;
            document.getElementById('detailProjectDesc').disabled = !isOwner;
            if (colorInput) colorInput.disabled = !isOwner;
            document.getElementById('btnSaveDetails').style.display = isOwner ? 'block' : 'none';
        }
    } catch (e) { console.error(e); }
}

async function saveProjectDetails() {
    const nome = document.getElementById('detailProjectName').value;
    const desc = document.getElementById('detailProjectDesc').value;
    const colorInput = document.getElementById('detailProjectColor');
    const colore = colorInput ? colorInput.value : '#6366f1';

    try {
        await fetch('api_projects.php?action=update_details', {
            method: 'POST',
            body: JSON.stringify({ project_id: currentDetailProjectId, nome: nome, descrizione: desc, colore: colore })
        });
        showToast('Dettagli aggiornati!', 'success');
        // Update Title in UI if name changed
        const titleDisplay = document.getElementById('projectTitleDisplay');
        if (titleDisplay) titleDisplay.textContent = nome;
        loadProjects(); // Refresh sidebar manually
    } catch (e) { showToast('Errore salvataggio', 'error'); }
}

// Members Logic (Re-implementation of old logic but tailored for new modal)
async function loadProjectMembers() {
    const list = document.getElementById('detailMembersList');
    list.innerHTML = '<p class="text-sm text-slate-400 italic">Caricamento...</p>';

    try {
        const res = await fetch(`api_collaboration.php?action=list_members&project_id=${currentDetailProjectId}`);
        const json = await res.json();
        if (json.success) {
            if (json.data.length === 0) list.innerHTML = '<p class="text-sm text-slate-500">Nessun membro.</p>';
            else {
                list.innerHTML = json.data.map(m => `
                    <div class="flex justify-between items-center bg-white p-3 rounded-lg border border-slate-100 shadow-sm">
                        <div class="flex items-center gap-3">
                            ${getAvatarHtml(m)} 
                            <div>
                                <p class="text-sm font-bold text-slate-700">${m.username}</p>
                                <p class="text-xs text-slate-400 capitalize">${m.role}</p>
                            </div>
                        </div>
                        ${m.role !== 'owner' ? `
                        <button onclick="removeProjectMember(${m.id})" class="text-red-400 hover:text-red-600 p-1">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>` : ''}
                    </div>
                `).join('');
            }
        }
    } catch (e) { list.innerHTML = 'Errore caricamento.'; }
}

async function searchUsersDetails(query) {
    const results = document.getElementById('detailMemberResults');
    if (query.length < 2) {
        results.classList.add('hidden');
        return;
    }

    try {
        const res = await fetch(`api_collaboration.php?action=search_users&q=${query}`);
        const json = await res.json();
        if (json.success) {
            results.innerHTML = json.data.map(u => `
                <div class="flex items-center gap-3 px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700 border-b last:border-0 border-slate-50"
                     onclick="addProjectMember(${u.id}, '${u.username}')">
                     ${getAvatarHtml(u, 'w-8 h-8 text-xs')}
                    <span>${u.username}</span>
                </div>
            `).join('');
            results.classList.remove('hidden');
        }
    } catch (e) { }
}

async function addProjectMember(userId, source) { // source is usually 'search', logic varies
    // Assuming api_collaboration handles adding
    try {
        const res = await fetch('api_collaboration.php?action=add_member', {
            method: 'POST',
            body: JSON.stringify({ project_id: currentDetailProjectId, user_id: userId, role: 'editor' })
        });
        const json = await res.json();
        if (json.success) {
            showToast('Membro aggiunto!', 'success');
            document.getElementById('detailMemberResults').classList.add('hidden');
            document.getElementById('detailMemberSearch').value = '';
            loadProjectMembers();
        } else {
            showToast(json.error || 'Errore', 'error');
        }
    } catch (e) { showToast('Errore rete', 'error'); }
}

// Remove Member Modal Logic
let currentRemoveMemberId = null;

function removeProjectMember(userId) {
    currentRemoveMemberId = userId;
    const modal = document.getElementById('removeMemberModal');
    const backdrop = document.getElementById('removeMemberBackdrop');
    const content = document.getElementById('removeMemberContent');

    if (!modal) {
        if (confirm('Rimuovere questo membro?')) executeRemoveMember();
        return;
    }

    modal.classList.remove('hidden');
    // Animate
    requestAnimationFrame(() => {
        backdrop.classList.remove('opacity-0');
        content.classList.remove('opacity-0', 'scale-95');
        content.classList.add('scale-100');
    });
}

function closeRemoveMemberModal() {
    const modal = document.getElementById('removeMemberModal');
    const backdrop = document.getElementById('removeMemberBackdrop');
    const content = document.getElementById('removeMemberContent');

    currentRemoveMemberId = null;
    backdrop.classList.add('opacity-0');
    content.classList.add('opacity-0', 'scale-95');
    content.classList.remove('scale-100');

    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

async function executeRemoveMember() {
    if (!currentRemoveMemberId) return;

    try {
        const res = await fetch('api_collaboration.php?action=remove_member', {
            method: 'POST',
            body: JSON.stringify({ project_id: currentDetailProjectId, user_id: currentRemoveMemberId })
        });
        const json = await res.json();
        if (json.success) {
            showToast('Membro rimosso', 'success');
            loadProjectMembers();
        } else {
            showToast(json.error || 'Errore', 'error');
        }
    } catch (e) {
        showToast('Errore rete', 'error');
    }

    closeRemoveMemberModal();
}

