<?php
// Ensure we have user details
if (!isset($userHeaderData)) {
    // Check if we already have it in $user variable from parent? 
    // Sometimes $user might be just ID or unrelated. Safer to fetch specific header data.
    // Use a unique variable name to avoid conflicts
    $stmtHeader = $pdo->prepare("SELECT username, nome, cognome, profile_image FROM users WHERE id = ?");
    $stmtHeader->execute([$_SESSION['user_id']]);
    $userHeaderData = $stmtHeader->fetch(PDO::FETCH_ASSOC);
}
?>
<?php
// Buffer Profile HTML for reuse in layout
ob_start();
?>
<div class="relative group" id="userDropdownContainer">
    <button onclick="toggleUserDropdown()"
        class="flex items-center gap-2 bg-white rounded-lg p-1 pl-3 pr-1 shadow-sm border border-slate-200 hover:shadow-md hover:border-indigo-300 transition-all no-underline text-slate-700 focus:outline-none">
        
        <span class="text-xs font-bold w-full text-right block truncate max-w-[100px] md:max-w-[150px] mr-1">
            <?php echo htmlspecialchars($userHeaderData['username']); ?>
        </span>

        <?php if (!empty($userHeaderData['profile_image'])): ?>
            <img src="uploads/avatars/<?php echo htmlspecialchars($userHeaderData['profile_image']); ?>?t=<?php echo time(); ?>"
                class="w-9 h-9 rounded-lg object-cover border-2 border-white shadow-sm" alt="Profilo">
        <?php else:
            $initials = strtoupper(substr($userHeaderData['username'], 0, 2));
            if (!empty($userHeaderData['nome'])) {
                if (!empty($userHeaderData['cognome'])) {
                    $initials = strtoupper(substr($userHeaderData['nome'], 0, 1) . substr($userHeaderData['cognome'], 0, 1));
                } else {
                    $initials = strtoupper(substr($userHeaderData['nome'], 0, 2));
                }
            }

            // Simple deterministic color
            $colors = ['bg-red-100 text-red-600', 'bg-green-100 text-green-600', 'bg-blue-100 text-blue-600', 'bg-indigo-100 text-indigo-600', 'bg-purple-100 text-purple-600'];
            $sum = 0;
            foreach (str_split($userHeaderData['username']) as $char)
                $sum += ord($char);
            $colorClass = $colors[$sum % count($colors)];
            ?>
            <div
                class="w-9 h-9 rounded-lg <?php echo $colorClass; ?> flex items-center justify-center text-xs font-bold border-2 border-white shadow-sm">
                <?php echo $initials; ?>
            </div>
        <?php endif; ?>
        
    </button>
    
    <!-- User Dropdown Menu -->
    <div id="userDropdownMenu" 
        class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-slate-100 py-1 z-[100] origin-top-right focus:outline-none">
        
        <div class="px-4 py-2 border-b border-slate-50">
            <p class="text-xs text-slate-500">Loggato come</p>
            <p class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($userHeaderData['username']); ?></p>
        </div>

        <a href="profile.php" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Il Mio Profilo
        </a>
        
        <button onclick="safeOpenSettings(); toggleUserDropdown();" class="block w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Impostazioni
        </button>

        <a href="export_data.php" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Backup Dati
        </a>

        <div class="border-t border-slate-100 my-1"></div>

        <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2 font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Esci
        </a>
    </div>
</div>

<script>
    function toggleUserDropdown() {
        const menu = document.getElementById('userDropdownMenu');
        menu.classList.toggle('hidden');
    }
    
    // Fallback safeOpenSettings if not defined
    if(typeof safeOpenSettings === 'undefined') {
        function safeOpenSettings() {
             if (typeof openSettingsModal === 'function') openSettingsModal();
             else window.location.href = 'index.php';
        }
    }

    // Close on clicking outside - consolidated in one listener if possible or add here
    document.addEventListener('click', function(e) {
        const container = document.getElementById('userDropdownContainer');
        const menu = document.getElementById('userDropdownMenu');
        if(container && menu && !container.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });
</script>

<?php
$profileHtml = ob_get_clean();
?>

<!-- Notification Layer (High Z-Index) -->
<div class="absolute top-4 right-4 flex items-center gap-3 z-[100]">

    <!-- Notification Bell (Interactive) -->
    <div class="relative cursor-pointer p-2 rounded-lg group" id="notifBellBtn">

        <!-- Background/Blur Layer (Separate to avoid trapping fixed dropdown) -->
        <div
            class="absolute inset-0 bg-white/80 backdrop-blur-sm border border-slate-200 shadow-sm rounded-lg transition-colors group-hover:bg-slate-100">
        </div>

        <!-- Icon Container -->
        <div class="relative z-10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-500 group-hover:text-slate-700"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span id="headerNotifBadge"
                class="hidden absolute top-0 right-0 h-2.5 w-2.5 bg-red-500 rounded-full border-2 border-white"></span>
        </div>

        <!-- Dropdown -->
        <div id="notifDropdown"
            class="hidden fixed top-16 left-4 right-4 sm:absolute sm:top-auto sm:left-auto sm:right-4 sm:mt-4 sm:w-80 bg-white rounded-xl shadow-lg border border-slate-100 py-2 z-[100] sm:max-w-[90vw]">
            <div class="px-4 py-2 border-b border-slate-50 flex justify-between items-center">
                <h3 class="font-bold text-sm text-slate-700">Notifiche</h3>
                <button onclick="markAllRead()" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Segna
                    tutte lette</button>
            </div>
            <div id="notifList" class="max-h-64 overflow-y-auto">
                <p class="p-4 text-center text-xs text-slate-400">Nessuna notifica.</p>
            </div>
        </div>
    </div>

    <!-- Profile Dropdown Widget -->
    <?php echo $profileHtml; ?>
    
</div>