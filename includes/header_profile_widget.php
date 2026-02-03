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
<a href="profile.php"
    class="flex items-center gap-2 bg-white rounded-lg p-1 pl-3 pr-1 shadow-sm border border-slate-200 hover:shadow-md hover:border-indigo-300 transition-all group no-underline text-slate-700">
    <span class="text-xs font-bold group-hover:text-indigo-600 transition-colors mr-1">
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
</a>
<?php
$profileHtml = ob_get_clean();
?>

<!-- Notification Layer (High Z-Index) -->
<div class="absolute top-4 right-4 flex items-center gap-3 z-[10] pointer-events-none">

    <!-- Notification Bell (Interactive) -->
    <div class="relative cursor-pointer p-2 rounded-lg group pointer-events-auto" id="notifBellBtn">

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
                class="hidden absolute top-1 right-1 h-2.5 w-2.5 bg-red-500 rounded-full border-2 border-white"></span>
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

    <!-- Ghost Profile (Invisible, for layout spacing) -->
    <div class="invisible select-none" aria-hidden="true">
        <?php echo $profileHtml; ?>
    </div>
</div>

<!-- Profile Layer (Standard Z-Index) -->
<div class="absolute top-4 right-4 flex items-center gap-3 z-[50]">
    <?php echo $profileHtml; ?>
</div>