<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$currentUserId = $_SESSION['user_id'];

// Check Permissions
$stmt = $pdo->prepare("SELECT global_role FROM users WHERE id = ?");
$stmt->execute([$currentUserId]);
$globalRole = $stmt->fetchColumn();

// Check Team Admin Role
$stmt = $pdo->prepare("SELECT 1 FROM team_members WHERE user_id = ? AND role = 'admin'");
$stmt->execute([$currentUserId]);
$isTeamAdmin = $stmt->fetchColumn();

if ($globalRole !== 'superadmin' && !$isTeamAdmin) {
    header("Location: index.php"); // Redirect unauthorized
    exit();
}
// Variables expected by sidebar.php
$userGlobalRole = $globalRole;
$isAnyTeamAdmin = $isTeamAdmin;

?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow - Dashboard Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="js/shared.js"></script>
</head>

<body class="bg-gray-50 text-gray-900 font-sans">

    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>

        <main class="flex-1 p-8 overflow-y-auto relative">
            <button onclick="toggleSidebar()" id="desktopHamburger"
                class="absolute top-4 left-4 z-30 p-2 text-slate-600 focus:outline-none transition-transform hover:scale-110 md:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <?php include 'includes/header_profile_widget.php'; ?>

            <div class="max-w-6xl mx-auto mt-10 md:mt-0">
                <header class="flex justify-between items-center mb-10">
                    <div class="flex items-center gap-4">
                        <h1 class="text-3xl font-bold text-slate-800">I Miei Team</h1>
                    </div>
                </header>

                <div class="space-y-12">

                    <section id="myTeamSection">
                        <div id="myTeamsList" class="space-y-8">
                            <!-- Populated via JS -->
                        </div>
                    </section>
                </div>
        </main>
    </div>

    <!-- Modals -->
    <!-- Create Team Modal (Superadmin) -->
    <div id="createTeamModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-xl w-full max-w-sm relative">
            <h3 class="text-lg font-bold mb-4">Crea Nuovo Team</h3>
            <input type="text" id="newTeamName" placeholder="Nome Team"
                class="w-full border p-2 rounded mb-4 focus:ring-2 focus:ring-indigo-500 outline-none">
            <div class="flex justify-end gap-2">
                <button onclick="document.getElementById('createTeamModal').classList.add('hidden')"
                    class="px-4 py-2 text-slate-600 rounded hover:bg-slate-100">Annulla</button>
                <button onclick="createTeam()"
                    class="px-4 py-2 bg-indigo-600 text-white rounded font-bold hover:bg-indigo-700">Crea</button>
            </div>
        </div>
    </div>

    <!-- Delete Team Modal -->
    <div id="deleteTeamModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-xl w-full max-w-sm relative">
            <h3 class="text-lg font-bold mb-2 text-red-600">Elimina Team</h3>
            <p class="text-sm text-slate-500 mb-6">Sei sicuro di voler eliminare il team <strong id="deleteTeamName"
                    class="text-slate-800"></strong>? <br>Questa azione è irreversibile.</p>
            <input type="hidden" id="deleteTeamId">
            <div class="flex justify-end gap-2">
                <button onclick="closeDeleteTeamModal()"
                    class="px-4 py-2 text-slate-600 rounded hover:bg-slate-100">Annulla</button>
                <button onclick="executeDeleteTeam()"
                    class="px-4 py-2 bg-red-600 text-white rounded font-bold hover:bg-red-700">Elimina</button>
            </div>
        </div>
    </div>

    <!-- Add User Modal (Team Admin) -->
    <div id="createUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-xl w-full max-w-md relative">
            <h3 class="text-lg font-bold mb-4">Aggiungi Utente al Team</h3>
            <input type="hidden" id="targetTeamId">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Username</label>
                    <input type="text" id="newUsername"
                        class="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Password Provvisoria</label>
                    <input type="text" id="newPassword"
                        class="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Nome (Opzionale)</label>
                        <input type="text" id="newNome"
                            class="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Cognome (Opzionale)</label>
                        <input type="text" id="newCognome"
                            class="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Email (Opzionale)</label>
                    <input type="email" id="newEmail"
                        class="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Ruolo</label>
                    <select id="newRole"
                        class="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="member">Utente Semplice</option>
                        <option value="admin">Admin Team</option>
                    </select>
                </div>

            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button onclick="document.getElementById('createUserModal').classList.add('hidden')"
                    class="px-4 py-2 text-slate-600 rounded hover:bg-slate-100">Annulla</button>
                <button onclick="createUserInTeam()"
                    class="px-4 py-2 bg-indigo-600 text-white rounded font-bold hover:bg-indigo-700">Crea
                    Utente</button>
            </div>
        </div>
    </div>


    <!-- Edit User Modal -->
    <div id="editUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-xl w-full max-w-md relative">
            <h3 class="text-lg font-bold mb-4">Modifica Utente</h3>
            <input type="hidden" id="editTeamId">
            <input type="hidden" id="editUserId">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Username</label>
                    <input type="text" id="editUsername" disabled
                        class="w-full border p-2 rounded bg-slate-100 text-slate-500 cursor-not-allowed">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Nome</label>
                        <input type="text" id="editNome"
                            class="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Cognome</label>
                        <input type="text" id="editCognome"
                            class="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Email</label>
                    <input type="email" id="editEmail"
                        class="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Ruolo</label>
                    <select id="editRole"
                        class="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="member">Utente Semplice</option>
                        <option value="admin">Admin Team</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nuova Password (lascia vuoto per non
                        cambiare)</label>
                    <input type="text" id="editPassword" placeholder="Solo se vuoi resettarla"
                        class="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button onclick="document.getElementById('editUserModal').classList.add('hidden')"
                    class="px-4 py-2 text-slate-600 rounded hover:bg-slate-100">Annulla</button>
                <button onclick="updateUserInTeam()"
                    class="px-4 py-2 bg-indigo-600 text-white rounded font-bold hover:bg-indigo-700">Salva
                    Modifiche</button>
            </div>
        </div>
    </div>

    <!-- Remove User Confirmation Modal -->
    <div id="removeUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-xl w-full max-w-sm relative">
            <h3 class="text-lg font-bold mb-2 text-red-600">Rimuovi Utente</h3>
            <p class="text-sm text-slate-500 mb-6">Rimuovere <strong id="removeUserName"></strong> dal team?
                <br>L'account rimarrà attivo nel sistema.
            </p>
            <input type="hidden" id="removeUserTeamId">
            <input type="hidden" id="removeUserId">
            <div class="flex justify-end gap-2">
                <button onclick="document.getElementById('removeUserModal').classList.add('hidden')"
                    class="px-4 py-2 text-slate-600 rounded hover:bg-slate-100">Annulla</button>
                <button onclick="executeRemoveUser()"
                    class="px-4 py-2 bg-red-600 text-white rounded font-bold hover:bg-red-700">Rimuovi</button>
            </div>
        </div>
    </div>

    <script>
        const GLOBAL_ROLE = '<?php echo $globalRole; ?>';

        document.addEventListener('DOMContentLoaded', () => {
            loadMyTeams();
        });

        async function loadMyTeams() {
            const res = await fetch('api_teams.php?action=my_team');
            const json = await res.json();
            if (json.success) {
                const container = document.getElementById('myTeamsList');
                if (json.data.length === 0) {
                    container.innerHTML = '<p class="text-slate-400">Non appartieni a nessun team.</p>';
                    return;
                }

                let html = '';
                for (let team of json.data) {
                    const memRes = await fetch('api_teams.php?action=team_members&team_id=' + team.id);
                    const memJson = await memRes.json();
                    const members = memJson.success ? memJson.data : [];

                    const canManage = (team.my_role === 'admin' || GLOBAL_ROLE === 'superadmin');

                    const membersHtml = members.map(m => `
                        <tr class="border-b last:border-0 hover:bg-slate-50 ${canManage ? 'cursor-pointer' : ''}" ${canManage ? `onclick="openEditUserModal(${team.id}, ${JSON.stringify(m).replace(/"/g, '&quot;')})"` : ''}>
                            <td class="py-3 px-4 font-medium whitespace-nowrap">${m.username}</td>
                            <td class="py-3 px-4 text-slate-600 whitespace-nowrap">${m.nome || ''} ${m.cognome || ''}</td>
                            <td class="py-3 px-4 text-slate-500 text-sm whitespace-nowrap">${m.email || '-'}</td>
                            <td class="py-3 px-4 text-slate-500 text-sm capitalize whitespace-nowrap">
                                <span class="${m.role === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-600'} px-2 py-0.5 rounded text-xs font-bold uppercase">
                                    ${m.role}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                ${canManage ?
                            `<button onclick="event.stopPropagation(); confirmRemoveUser(${team.id}, ${m.id}, '${m.username}')" class="text-red-500 hover:text-red-700 text-xs font-bold px-2 py-1 border border-red-200 rounded hover:bg-red-50">Rimuovi</button>` : ''}
                            </td>
                        </tr>
                    `).join('');

                    html += `
                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden overflow-x-auto">
                            <div class="bg-slate-50 p-4 border-b border-slate-200 flex justify-between items-center min-w-[600px]">
                                <div>
                                    <h3 class="font-bold text-lg text-slate-800">${team.name}</h3>
                                    <p class="text-xs text-slate-500 capitalize">Il tuo ruolo: ${team.my_role}</p>
                                </div>
                                ${canManage ?
                            `<button onclick="openCreateUserModal(${team.id})" class="bg-indigo-600 text-white px-3 py-1.5 rounded-lg text-sm font-bold hover:bg-indigo-700 shadow-sm">
                                        + Aggiungi Utente
                                    </button>` : ''}
                            </div>
                            <table class="w-full text-left min-w-[600px]">
                                <thead class="bg-slate-100 text-slate-500 text-xs uppercase font-semibold">
                                    <tr>
                                        <th class="py-2 px-4 whitespace-nowrap">Username</th>
                                        <th class="py-2 px-4 whitespace-nowrap">Nome Completo</th>
                                        <th class="py-2 px-4 whitespace-nowrap">Email</th>
                                        <th class="py-2 px-4 whitespace-nowrap">Ruolo</th>
                                        <th class="py-2 px-4 whitespace-nowrap"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${membersHtml}
                                </tbody>
                            </table>
                        </div>
                    `;
                }
                container.innerHTML = html;
            }
        }

        // Action Functions
        function openCreateUserModal(teamId) {
            document.getElementById('targetTeamId').value = teamId;
            document.getElementById('createUserModal').classList.remove('hidden');
        }

        async function createUserInTeam() {
            const teamId = document.getElementById('targetTeamId').value;
            const menuUsername = document.getElementById('newUsername').value;
            const password = document.getElementById('newPassword'); // Keep element ref for clearing if needed
            const nome = document.getElementById('newNome').value;
            const cognome = document.getElementById('newCognome').value;
            const email = document.getElementById('newEmail').value;
            const role = document.getElementById('newRole').value;

            const username = document.getElementById('newUsername').value;
            const passwordVal = document.getElementById('newPassword').value;

            if (!username || !passwordVal) { showToast("Username e password obbligatori.", "error"); return; }

            try {
                const res = await fetch('api_teams.php?action=create_user_in_team', {
                    method: 'POST',
                    body: JSON.stringify({ team_id: teamId, username, password: passwordVal, email, nome, cognome, role })
                });
                const json = await res.json();
                if (json.success) {
                    showToast("Utente creato con successo!", "success");
                    document.getElementById('createUserModal').classList.add('hidden');
                    loadMyTeams();
                    document.getElementById('newUsername').value = '';
                    document.getElementById('newPassword').value = '';
                    document.getElementById('newNome').value = '';
                    document.getElementById('newCognome').value = '';
                    document.getElementById('newEmail').value = '';
                } else showToast(json.error, 'error');
            } catch (e) { showToast('Errore di connessione', 'error'); }
        }

        // Edit User Logic
        function openEditUserModal(teamId, user) {
            document.getElementById('editTeamId').value = teamId;
            document.getElementById('editUserId').value = user.id;

            document.getElementById('editUsername').value = user.username;
            document.getElementById('editNome').value = user.nome || '';
            document.getElementById('editCognome').value = user.cognome || '';
            document.getElementById('editEmail').value = user.email || '';
            document.getElementById('editRole').value = user.role;
            document.getElementById('editPassword').value = ''; // Reset

            document.getElementById('editUserModal').classList.remove('hidden');
        }

        async function updateUserInTeam() {
            const teamId = document.getElementById('editTeamId').value;
            const userId = document.getElementById('editUserId').value;
            const nome = document.getElementById('editNome').value;
            const cognome = document.getElementById('editCognome').value;
            const email = document.getElementById('editEmail').value;
            const role = document.getElementById('editRole').value;
            const password = document.getElementById('editPassword').value;

            try {
                const res = await fetch('api_teams.php?action=update_team_member', {
                    method: 'POST',
                    body: JSON.stringify({ team_id: teamId, user_id: userId, nome, cognome, email, role, password })
                });
                const json = await res.json();
                if (json.success) {
                    showToast("Utente aggiornato!", "success");
                    document.getElementById('editUserModal').classList.add('hidden');
                    loadMyTeams();
                } else showToast(json.error, 'error');
            } catch (e) { showToast('Errore di connessione', 'error'); }
        }

        // Remove User Logic
        function confirmRemoveUser(teamId, userId, username) {
            document.getElementById('removeUserTeamId').value = teamId;
            document.getElementById('removeUserId').value = userId;
            document.getElementById('removeUserName').textContent = username;
            document.getElementById('removeUserModal').classList.remove('hidden');
        }

        async function executeRemoveUser() {
            const teamId = document.getElementById('removeUserTeamId').value;
            const userId = document.getElementById('removeUserId').value;
            if (!teamId || !userId) return;

            try {
                const res = await fetch('api_teams.php?action=remove_team_member', {
                    method: 'POST',
                    body: JSON.stringify({ team_id: teamId, user_id: userId })
                });
                const json = await res.json();
                if (json.success) {
                    showToast("Utente rimosso dal team.", "success");
                    document.getElementById('removeUserModal').classList.add('hidden');
                    loadMyTeams();
                } else showToast(json.error, 'error');
            } catch (e) { showToast('Errore di connessione', 'error'); }
        }

    </script>
</body>

</html>