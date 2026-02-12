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

    /* Input Fix for Dark Mode / Autofill */
    #sidebar input,
    #sidebar input:-webkit-autofill {
        background-color: #1e293b !important;
        /* bg-slate-800 */
        color: white !important;
        -webkit-text-fill-color: white !important;
        -webkit-box-shadow: 0 0 0 30px #1e293b inset !important;
        border-color: #334155 !important;
        /* border-slate-700 */
    }
    
    /* Custom Sidebar Scrollbar - Target specific scroll container */
    #sidebarProjectNav::-webkit-scrollbar {
        width: 4px; /* Thinner */
    }

    #sidebarProjectNav::-webkit-scrollbar-track {
        background: transparent;
    }

    #sidebarProjectNav::-webkit-scrollbar-thumb {
        background-color: transparent; /* Hidden by default */
        border-radius: 4px;
    }

    /* Show on hover */
    #sidebarProjectNav:hover::-webkit-scrollbar-thumb {
        background-color: #475569; /* slate-600 */
    }

    #sidebarProjectNav {
        scrollbar-width: thin;
        scrollbar-color: transparent transparent; /* Hidden by default for Firefox */
    }
    
    #sidebarProjectNav:hover {
        scrollbar-color: #475569 transparent; /* Show on hover for Firefox */
    }
    
    /* Ensure project items have explicit display for JS toggling */
    .project-item {
        display: list-item;
    }
    
    /* Fix for bottom section overlap if needed */
    .sidebar-bottom-shadow {
        box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1), 0 -2px 4px -1px rgba(0, 0, 0, 0.06);
    }
</style>

<!-- Script moved to bottom -->

<div id="sidebarOverlay" onclick="toggleSidebar()"
    class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden glass"></div>
    
<aside id="sidebar"
    class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white transition-transform duration-300 ease-in-out shadow-xl flex flex-col flex-shrink-0 mobile-closed md:relative md:inset-auto">

    <!-- Header Section (Fixed Top) -->
    <div class="flex justify-between items-center h-16 px-6 shrink-0">
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

    <!-- Projects Controls (Fixed Top) -->
    <div class="px-6 pb-2 shrink-0">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
        <div class="flex justify-between items-center mb-4">
            <p class="text-xs uppercase text-slate-500 font-semibold tracking-wider">Progetti</p>
        </div>

        <style>
            /* Custom Color Picker Styles */
            input[type="color"]::-webkit-color-swatch-wrapper {
                padding: 0;
            }
            input[type="color"]::-webkit-color-swatch {
                border: none;
                border-radius: 0.25rem; 
            }
        </style>

        <form action="add_project.php" method="POST" class="mb-4 flex gap-2">
            <div class="relative flex-1">
                 <input type="text" name="nome_progetto" placeholder="Nuovo progetto..." required
                class="w-full bg-slate-800 text-sm border-none rounded p-2 focus:ring-2 focus:ring-indigo-500 text-white pl-3">
            </div>
            <input type="color" name="colore" value="#6366f1" 
                class="h-9 w-9 p-0 border-none rounded bg-transparent cursor-pointer overflow-hidden" title="Scegli colore">
        </form>
        
        <!-- Project Search Input - Fixed Red Border Removed -->
        <div class="mb-2 relative">
             <input type="text" id="projectSearchInput" placeholder="Cerca progetti..." 
                class="w-full bg-slate-800/50 text-xs border-slate-700 rounded p-2 focus:ring-1 focus:ring-indigo-500 text-slate-300 pl-8 placeholder-slate-500">
             <svg class="w-3 h-3 text-slate-500 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
             </svg>
        </div>
    </div>

    <!-- Scrollable Project List -->
    <nav id="sidebarProjectNav" class="flex-1 overflow-y-auto px-6 min-h-0">
        <ul class="space-y-2 pb-4" id="sidebarProjectList">
            <?php
            // Recuperiamo i progetti dal DB (Owner o Membro)
            $stmt = $pdo->prepare("
                SELECT p.*, pm.role 
                FROM projects p 
                JOIN project_members pm ON p.id = pm.project_id 
                WHERE pm.user_id = ? 
                ORDER BY p.ordinamento ASC, p.data_modifica DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            while ($row = $stmt->fetch()) {
                $activeClass = (isset($_GET['project_id']) && $_GET['project_id'] == $row['id']) ? 'bg-slate-800' : '';
                $borderColor = $row['colore'] ?? '#6366f1'; // Fallback
                
                // Use inline style for border-left-color to show project color
                // Also add data-id for sorting
                echo "<li data-id='{$row['id']}' class='project-item hover:bg-slate-800 p-2 rounded-md cursor-pointer transition-colors border-l-4 $activeClass' style='border-left-color: {$borderColor};'>";
                echo "<a href='index.php?project_id={$row['id']}' class='block w-full text-white no-underline flex items-center justify-between'>";
                echo "<span class='project-name text-sm'>" . htmlspecialchars($row['nome']) . "</span>"; // Added class project-name
                
                echo "</a></li>";
            }
            ?>
        </ul>
    </nav>
    
    <!-- Fixed Bottom Section: Social & Admin Links -->
    <div class="p-6 border-t border-slate-800 bg-slate-900 shrink-0 z-10 sidebar-bottom-shadow">
        <ul class="space-y-2">
            <li
                class="hover:bg-slate-800 p-2 rounded-md cursor-pointer transition-colors border-l-4 border-transparent">
                <a href="collaborators.php" class="flex items-center gap-3 w-full text-white no-underline">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-400" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    Collaboratori
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

            <li
                class="hover:bg-slate-800 p-2 rounded-md cursor-pointer transition-colors border-l-4 border-transparent">
                <a href="calendar.php" class="flex items-center gap-3 w-full text-white no-underline">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-400" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Calendario
                </a>
            </li>

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
    </div>
</aside>

<script>
    console.log("Sidebar v2.0 - Starting...");

    function initSidebarLogic() {
        console.log("Sidebar Logic - Running");
        
        // Sortable
        const listEl = document.getElementById('sidebarProjectList');
        if (listEl) {
            if (typeof Sortable !== 'undefined') {
                new Sortable(listEl, {
                    animation: 150,
                    ghostClass: 'bg-slate-700',
                    delay: 150, // Wait 150ms before starting drag
                    delayOnTouchOnly: true, // Only applies to touch
                    touchStartThreshold: 5, // Allow 5px movement before cancelling
                    onEnd: function(evt) {
                        const searchTerm = document.getElementById('projectSearchInput')?.value || '';
                        if(searchTerm.length > 0) return;
                        
                        const order = Array.from(listEl.querySelectorAll('li[data-id]')).map(li => li.getAttribute('data-id'));
                        
                        fetch('reorder_projects.php', {
                            method: 'POST',
                            body: JSON.stringify({ order: order }),
                            headers: { 'Content-Type': 'application/json' }
                        }).catch(console.error);
                    }
                });
            } else {
                console.warn("SortableJS not loaded or undefined");
            }
        }
        
        // Search
        const searchInput = document.getElementById('projectSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                const items = document.querySelectorAll('#sidebarProjectList li');
                
                items.forEach(li => {
                    const text = li.textContent.toLowerCase();
                    if (text.includes(term)) {
                        li.classList.remove('hidden');
                        li.style.display = ''; // fallback
                    } else {
                        li.classList.add('hidden');
                        li.style.display = 'none'; // fallback
                    }
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarLogic);
    } else {
        initSidebarLogic();
    }
</script>