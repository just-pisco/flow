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
<a href="profile.php"
    class="absolute top-4 right-4 z-[60] flex items-center gap-2 bg-white rounded-lg p-1 pl-3 pr-1 shadow-sm border border-slate-200 hover:shadow-md hover:border-indigo-300 transition-all group no-underline text-slate-700">
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