<style>
    /* SIDEBAR CSS */
    .glass {
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    #sidebar {
        /* Default mobile state: shown (we control via mobile-closed class) */
    }

    /* FORCE SIDEBAR VISIBILITY ON DESKTOP */
    @media (min-width: 768px) {
        #sidebar {
            transition: width 0.3s ease-in-out, padding 0.3s ease-in-out;
        }

        #sidebar.desktop-closed {
            width: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }
    }

    /* MOBILE ONLY: Hide sidebar by default */
    @media (max-width: 767px) {
        .mobile-closed {
            transform: translateX(-100%);
        }
    }
</style>

<div id="sidebarOverlay" onclick="toggleSidebar()"
    class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden glass"></div>
<aside id="sidebar"
    class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white p-6 transition-transform duration-300 ease-in-out shadow-xl flex flex-col flex-shrink-0 mobile-closed md:relative md:inset-auto">

    <div class="flex justify-between items-center h-10">
        <a href="index.php"
            class="text-2xl font-bold tracking-tight text-indigo-400 no-underline hover:text-indigo-300 transition block">Flow.</a>

        <!-- Close Button (Desktop & Mobile) -->
        <button onclick="toggleSidebar()" id="sidebarCloseBtn"
            class="text-slate-400 hover:text-white focus:outline-none hidden md:block">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="mt-10">
        <div class="flex justify-between items-center mb-4">
            <p class="text-xs uppercase text-slate-500 font-semibold tracking-wider">Progetti</p>
        </div>

        <form action="add_project.php" method="POST" class="mb-6">
            <input type="text" name="nome_progetto" placeholder="Nuovo progetto..."
                class="w-full bg-slate-800 text-sm border-none rounded p-2 focus:ring-2 focus:ring-indigo-500 text-white">
        </form>

        <ul class="space-y-2">
            <?php
            // Recuperiamo i progetti dal DB (Owner o Membro)
            $stmt = $pdo->prepare("
                SELECT p.*, pm.role 
                FROM projects p 
                JOIN project_members pm ON p.id = pm.project_id 
                WHERE pm.user_id = ? 
                ORDER BY p.data_modifica DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            while ($row = $stmt->fetch()) {
                $activeClass = (isset($_GET['project_id']) && $_GET['project_id'] == $row['id']) ? 'bg-slate-800 border-indigo-500' : 'border-transparent';
                echo "<li class='hover:bg-slate-800 p-2 rounded-md cursor-pointer transition-colors border-l-4 $activeClass'>";
                echo "<a href='index.php?project_id={$row['id']}' class='block w-full text-white no-underline'>";
                echo htmlspecialchars($row['nome']);
                echo "</a></li>";
            }
            ?>
        </ul>

        <!-- Divider -->
        <div class="my-6 border-t border-slate-700"></div>

        <!-- Social & Admin Links -->
        <ul class="space-y-2">
            <li
                class="hover:bg-slate-800 p-2 rounded-md cursor-pointer transition-colors border-l-4 border-transparent">
                <a href="friends.php" class="flex items-center gap-3 w-full text-white no-underline">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-400" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    </svg>
                    Amici
                    <span id="sidebarFriendsBadge"
                        class="hidden ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"></span>
                </a>
            </li>

            <li
                class="hover:bg-slate-800 p-2 rounded-md cursor-pointer transition-colors border-l-4 border-transparent">
                <a href="teams.php" class="flex items-center gap-3 w-full text-white no-underline">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-400" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Team
                </a>
            </li>

            <?php
            // Ensure variables are set to avoid warning if not set in parent
            $uRole = isset($userGlobalRole) ? $userGlobalRole : 'user';
            $isTeamAdm = isset($isAnyTeamAdmin) ? $isAnyTeamAdmin : false;

            // If not set in parent logic yet (e.g. friends.php doesn't fetch them yet), we fetch here? 
            // Better to rely on parent providing it or fetch it if missing.
            if (!isset($userGlobalRole)) {
                $st = $pdo->prepare("SELECT global_role FROM users WHERE id = ?");
                $st->execute([$_SESSION['user_id']]);
                $uRole = $st->fetchColumn();
            }
            if (!isset($isAnyTeamAdmin)) {
                $st = $pdo->prepare("SELECT 1 FROM team_members WHERE user_id = ? AND role = 'admin' LIMIT 1");
                $st->execute([$_SESSION['user_id']]);
                $isTeamAdm = $st->fetchColumn();
            }
            ?>

            <?php if ($uRole === 'superadmin' || $isTeamAdm): ?>
                <li
                    class="hover:bg-slate-800 p-2 rounded-md cursor-pointer transition-colors border-l-4 border-transparent">
                    <a href="admin_dashboard.php" class="flex items-center gap-3 w-full text-white no-underline">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-orange-400" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Admin
                    </a>
                </li>
            <?php endif; ?>

        </ul>
    </nav>

    <div class="absolute bottom-6 left-6 right-6 space-y-2">
        <a href="export_data.php"
            class="flex items-center gap-3 text-slate-400 hover:text-white transition-colors p-2 rounded-md hover:bg-slate-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span class="font-medium text-sm">Backup Dati</span>
        </a>

        <!-- Settings Button - Depends on openSettingsModal being available in parent -->
        <!-- We can check if function exists in JS ?? No PHP. -->
        <!-- We just assume parent has the modal. index.php has it. friends/admin might not have it yet. -->
        <!-- If they don't have it, we should probably hide this or move Settings modal to included file too. -->
        <!-- For now, friends/admin pages are simpler. Let's hide Settings/Backup on other pages? -->
        <!-- OR imply that Sidebar is mostly for index.php navigation. -->
        <!-- But User wants sidebar on Friends page too. -->
        <!-- Let's keep it. If openSettingsModal is not defined, error. -->
        <!-- Ideally, create includes/modals.php -->
        <a href="profile.php"
            class="flex items-center gap-3 text-slate-400 hover:text-indigo-400 transition-colors p-2 rounded-md hover:bg-slate-800 w-full text-left">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span class="font-medium text-sm">Il Mio Profilo</span>
        </a>

        <script>
            function safeOpenSettings() {
                if (typeof openSettingsModal === 'function') openSettingsModal();
                else window.location.href = 'index.php'; // Fallback
            }
        </script>

        <button onclick="safeOpenSettings()"
            class="flex items-center gap-3 text-slate-400 hover:text-indigo-400 transition-colors p-2 rounded-md hover:bg-slate-800 w-full text-left">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="font-medium text-sm">Impostazioni</span>
        </button>
        <a href="logout.php"
            class="flex items-center gap-3 text-slate-400 hover:text-white transition-colors p-2 rounded-md hover:bg-slate-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span class="font-medium text-sm">Esci</span>
        </a>
    </div>
</aside>