<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow - Amici</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="js/shared.js"></script>
</head>

<body class="bg-gray-50 text-gray-900 font-sans">

    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>

        <main class="flex-1 p-8 overflow-y-auto relative">
            <!-- Mobile Hamburger -->
            <button onclick="toggleSidebar()" id="desktopHamburger"
                class="absolute top-4 left-4 z-30 p-2 text-slate-600 focus:outline-none transition-transform hover:scale-110 md:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </button>
            <?php include 'includes/header_profile_widget.php'; ?>

            <div class="max-w-4xl mx-auto mt-10 md:mt-0">
                <header class="flex justify-between items-center mb-8">
                    <div class="flex items-center gap-4">
                        <h1 class="text-3xl font-bold text-slate-800">I Miei Contatti</h1>
                    </div>
                </header>

                <!-- Tabs -->
                <div class="flex border-b border-slate-200 mb-6">
                    <button onclick="switchTab('friends')" id="tab-friends"
                        class="px-6 py-3 border-b-2 font-medium text-sm transition-colors text-indigo-600 border-indigo-600">I
                        Miei Amici</button>
                    <button onclick="switchTab('add')" id="tab-add"
                        class="px-6 py-3 border-b-2 border-transparent text-slate-500 hover:text-slate-700 font-medium text-sm transition-colors">Aggiungi
                        Amico</button>
                    <button onclick="switchTab('requests')" id="tab-requests"
                        class="px-6 py-3 border-b-2 border-transparent text-slate-500 hover:text-slate-700 font-medium text-sm transition-colors relative">
                        Richieste
                        <span id="req-badge" class="hidden absolute top-2 right-2 flex h-2 w-2">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                        </span>
                    </button>
                </div>

                <!-- Tab Contents -->
                <div id="content-friends" class="space-y-4">
                    <!-- Friend List -->
                    <div id="friendListContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <p class="text-slate-400 italic col-span-2">Caricamento...</p>
                    </div>
                </div>

                <div id="content-add" class="hidden space-y-6">
                    <div class="relative max-w-lg">
                        <input type="text" id="userSearchInput" onkeyup="searchUsersGlobal()"
                            placeholder="Cerca persone per nome utente..."
                            class="w-full border border-slate-300 rounded-lg p-3 pl-10 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3.5" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <div id="searchResults" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Results -->
                    </div>
                </div>

                <div id="content-requests" class="hidden space-y-4">
                    <div id="requestsListContainer" class="space-y-3 max-w-2xl">
                        <!-- Incoming Requests -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Remove Friend Modal -->
    <div id="removeFriendModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-xl w-full max-w-sm relative">
            <h3 class="text-lg font-bold mb-2 text-red-600">Rimuovi Amico</h3>
            <p class="text-sm text-slate-500 mb-6">Sei sicuro di voler rimuovere <strong id="removeFriendName"
                    class="text-slate-800"></strong> dagli amici?</p>
            <input type="hidden" id="removeFriendId">
            <div class="flex justify-end gap-2">
                <button onclick="document.getElementById('removeFriendModal').classList.add('hidden')"
                    class="px-4 py-2 text-slate-600 rounded hover:bg-slate-100">Annulla</button>
                <button onclick="executeRemoveFriend()"
                    class="px-4 py-2 bg-red-600 text-white rounded font-bold hover:bg-red-700">Rimuovi</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Init
        document.addEventListener('DOMContentLoaded', () => {
            loadFriends();
            loadRequests();
        });

        function switchTab(tab) {
            ['friends', 'add', 'requests'].forEach(t => {
                document.getElementById('content-' + t).classList.add('hidden');
                document.getElementById('tab-' + t).classList.remove('text-indigo-600', 'border-indigo-600');
                document.getElementById('tab-' + t).classList.add('text-slate-500', 'border-transparent');
            });
            document.getElementById('content-' + tab).classList.remove('hidden');
            document.getElementById('tab-' + tab).classList.add('text-indigo-600', 'border-indigo-600');
            document.getElementById('tab-' + tab).classList.remove('text-slate-500', 'border-transparent');
        }

        async function loadFriends() {
            try {
                const res = await fetch('api_friends.php?action=list_friends');
                const json = await res.json();
                if (json.success) {
                    const container = document.getElementById('friendListContainer');
                    if (json.data.length === 0) {
                        container.innerHTML = '<p class="text-slate-500 italic col-span-2">Non hai ancora amici. Cerca qualcuno!</p>';
                        return;
                    }
                    container.innerHTML = json.data.map(u => `
                        <div class="flex items-center justify-between bg-white p-4 rounded-lg shadow-sm border border-slate-200 hover:shadow-md transition-shadow">
                             <div class="flex items-center gap-3">
                                ${getAvatarHtml(u, 'w-10 h-10', 'text-sm')}
                                <div>
                                    <span class="font-medium text-slate-800 block">${u.username}</span>
                                    <span class="text-xs text-slate-500">${u.nome ? u.nome + ' ' + (u.cognome || '') : ''}</span>
                                </div>
                             </div>
                             <div class="flex items-center gap-2">
                                <span class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded-full font-medium">Amico</span>
                                <button onclick="removeFriend(${u.id}, '${u.username}')" class="text-red-400 hover:text-red-600 p-1 rounded-full hover:bg-red-50" title="Rimuovi Amico">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                             </div>
                        </div>
                    `).join('');
                }
            } catch (e) { console.error(e); }
        }

        async function loadRequests() {
            try {
                const res = await fetch('api_friends.php?action=list_requests');
                const json = await res.json();
                if (json.success) {
                    const container = document.getElementById('requestsListContainer');
                    const badge = document.getElementById('req-badge');

                    if (json.data.length > 0) badge.classList.remove('hidden');
                    else badge.classList.add('hidden');

                    if (json.data.length === 0) {
                        container.innerHTML = '<p class="text-slate-500 italic">Nessuna richiesta in sospeso.</p>';
                        return;
                    }
                    container.innerHTML = json.data.map(req => `
                        <div class="flex items-center justify-between bg-white p-4 rounded-lg shadow-sm border border-slate-200">
                             <div class="flex items-center gap-3">
                                ${getAvatarHtml(req, 'w-10 h-10', 'text-sm')}
                                <div>
                                    <span class="font-medium text-slate-800 block">Richiesta da <strong>${req.username}</strong></span>
                                    <span class="text-xs text-slate-400">${new Date(req.created_at).toLocaleDateString()}</span>
                                </div>
                             </div>
                             <div class="flex gap-2">
                                <button onclick="respondRequest(${req.friendship_id}, 'accept')" class="px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm font-bold">Accetta</button>
                                <button onclick="respondRequest(${req.friendship_id}, 'decline')" class="px-3 py-1 bg-white border border-slate-300 text-slate-600 rounded hover:bg-slate-50 text-sm">Rifiuta</button>
                             </div>
                        </div>
                    `).join('');
                }
            } catch (e) { console.error(e); }
        }

        async function respondRequest(id, decision) {
            try {
                const res = await fetch('api_friends.php?action=respond_request', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ friendship_id: id, decision })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(decision === 'accept' ? 'Amico aggiunto!' : 'Richiesta rifiutata.', 'success');
                    loadRequests();
                    if (decision === 'accept') loadFriends();
                } else showToast('Errore: ' + json.error, 'error');
            } catch (e) { showToast('Errore di rete', 'error'); }
        }

        let searchTimeout;
        function searchUsersGlobal() {
            clearTimeout(searchTimeout);
            const q = document.getElementById('userSearchInput').value;
            if (q.length < 3) {
                document.getElementById('searchResults').innerHTML = '';
                return;
            }
            searchTimeout = setTimeout(async () => {
                try {
                    const res = await fetch('api_friends.php?action=search_global&q=' + encodeURIComponent(q));
                    const json = await res.json();
                    if (json.success) {
                        const container = document.getElementById('searchResults');
                        if (json.data.length === 0) {
                            container.innerHTML = '<p class="text-slate-500 mt-2">Nessun utente trovato.</p>';
                            return;
                        }
                        container.innerHTML = json.data.map(u => {
                            let btn = '';
                            if (u.friendship_status === 'none') {
                                btn = `<button onclick="sendRequest(${u.id})" class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 text-sm font-bold">Aggiungi</button>`;
                            } else if (u.friendship_status === 'pending') {
                                btn = `<span class="text-xs text-orange-500 font-medium">${u.is_requester ? 'Richiesta Inviata' : 'Richiesta Ricevuta'}</span>`;
                            } else {
                                btn = `<span class="text-xs text-green-600 font-medium">Già Amici</span>`;
                            }

                            return `
                    <div class="flex items-center justify-between bg-white p-3 rounded-lg border border-slate-200 hover:border-indigo-300 transition-colors">
                    <div class="flex items-center gap-3">
                        ${getAvatarHtml(u, 'w-10 h-10', 'text-sm')}
                        <div class="flex flex-col">
                            <span class="font-medium text-slate-800">${u.username}</span>
                            <span class="text-xs text-slate-500">${u.nome || ''} ${u.cognome || ''}</span>
                        </div>
                    </div>
                                ${btn}
                            </div>`;
                        }).join('');
                    }
                } catch (e) { }
            }, 300);
        }

        async function sendRequest(userId) {
            try {
                const res = await fetch('api_friends.php?action=send_request', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId })
                });
                const json = await res.json();
                if (json.success) {
                    showToast('Richiesta inviata!', 'success');
                    searchUsersGlobal(); // Refresh UI
                } else showToast(json.error, 'error');
            } catch (e) { showToast('Errore di rete', 'error'); }
        }

        function removeFriend(friendId, username) {
            document.getElementById('removeFriendId').value = friendId;
            document.getElementById('removeFriendName').textContent = username;
            document.getElementById('removeFriendModal').classList.remove('hidden');
        }

        async function executeRemoveFriend() {
            const friendId = document.getElementById('removeFriendId').value;
            const username = document.getElementById('removeFriendName').textContent;

            try {
                const res = await fetch('api_friends.php?action=remove_friend', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ friend_user_id: friendId })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(`${username} rimosso dagli amici.`, 'success');
                    document.getElementById('removeFriendModal').classList.add('hidden');
                    loadFriends();
                } else showToast('Errore: ' + (json.error || 'Sconosciuto'), 'error');
            } catch (e) { showToast('Errore di rete', 'error'); }
        }


    </script>
</body>

</html>