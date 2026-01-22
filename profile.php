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
    <title>Flow - Il Mio Profilo</title>
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
            </button>
            <?php include 'includes/header_profile_widget.php'; ?>

            <div class="max-w-2xl mx-auto mt-10 md:mt-0">
                <header class="mb-10">
                    <h1 class="text-3xl font-bold text-slate-800">Il Mio Profilo</h1>
                    <p class="text-slate-500">Gestisci le tue informazioni personali</p>
                </header>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
                    <form id="profileForm" onsubmit="saveProfile(event)" class="space-y-8">

                        <!-- Avatar Section -->
                        <div class="flex flex-col items-center gap-4 border-b border-slate-100 pb-8">
                            <div class="relative group">
                                <div id="avatarPreview"
                                    class="w-32 h-32 rounded-full overflow-hidden bg-slate-100 border-4 border-white shadow-lg flex items-center justify-center text-4xl font-bold text-slate-400">
                                    <span>?</span>
                                </div>
                                <div class="absolute inset-0 bg-black bg-opacity-40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"
                                    onclick="document.getElementById('avatarInput').click()">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                            </div>
                            <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp"
                                class="hidden" onchange="previewAvatar(this)">
                            <div>
                                <p class="text-xs text-slate-400 text-center">Clicca sull'immagine per
                                    modificarla.<br>Max 5MB. JPG, PNG, WebP.</p>
                            </div>
                        </div>

                        <!-- Data Section -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Nome</label>
                                <input type="text" id="nome" name="nome"
                                    class="w-full border border-slate-300 rounded-lg p-2.5 focus:ring-2 focus:ring-indigo-500 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Cognome</label>
                                <input type="text" id="cognome" name="cognome"
                                    class="w-full border border-slate-300 rounded-lg p-2.5 focus:ring-2 focus:ring-indigo-500 outline-none transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                            <input type="email" id="email" name="email"
                                class="w-full border border-slate-300 rounded-lg p-2.5 focus:ring-2 focus:ring-indigo-500 outline-none transition">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Username (non
                                modificabile)</label>
                            <input type="text" id="username" disabled
                                class="w-full border border-slate-200 bg-slate-50 text-slate-500 rounded-lg p-2.5 cursor-not-allowed">
                        </div>

                        <div class="pt-4 border-t border-slate-100">
                            <h3 class="font-bold text-slate-800 mb-4">Sicurezza</h3>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Nuova Password</label>
                                <input type="password" id="password" name="password"
                                    placeholder="Lascia vuoto per non cambiare"
                                    class="w-full border border-slate-300 rounded-lg p-2.5 focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                <p class="text-xs text-slate-400 mt-1">Minimo 8 caratteri consigliati.</p>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit"
                                class="bg-indigo-600 text-white font-bold py-3 px-8 rounded-xl shadow hover:bg-indigo-700 transform active:scale-95 transition-all">
                                Salva Modifiche
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', loadProfile);

        async function loadProfile() {
            try {
                const res = await fetch('api_profile.php?action=get_profile');
                const json = await res.json();
                if (json.success) {
                    const u = json.data;
                    document.getElementById('username').value = u.username;
                    document.getElementById('nome').value = u.nome || '';
                    document.getElementById('cognome').value = u.cognome || '';
                    document.getElementById('email').value = u.email || '';

                    if (u.profile_image_url) {
                        document.getElementById('avatarPreview').innerHTML = `<img src="${u.profile_image_url}" class="w-full h-full object-cover">`;
                    } else {
                        document.getElementById('avatarPreview').innerHTML = `<span class="text-4xl font-bold text-slate-400">${u.username.substring(0, 2).toUpperCase()}</span>`;
                    }
                }
            } catch (e) { showToast("Errore caricamento profilo", "error"); }
        }

        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('avatarPreview').innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        async function saveProfile(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('profileForm'));

            try {
                const res = await fetch('api_profile.php?action=update_profile', {
                    method: 'POST',
                    body: formData
                });
                const json = await res.json();
                if (json.success) {
                    showToast("Profilo aggiornato!", "success");
                    loadProfile(); // Refresh to ensure image URL is updated
                } else {
                    showToast(json.error || "Errore salvataggio", "error");
                }
            } catch (e) { showToast("Errore di connessione", "error"); }
        }
    </script>
</body>

</html>