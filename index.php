<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'includes/db.php';
//push

// Auto-redirect al progetto più recente se non c'è project_id
if (!isset($_GET['project_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE user_id = ? ORDER BY data_modifica DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $lastProject = $stmt->fetch();
    if ($lastProject) {
        header("Location: index.php?project_id=" . $lastProject['id']);
        exit();
    }
}

// Fetch User's Gemini API Key
$stmt = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$apiKey = $user['gemini_api_key'] ?? null;
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow - Gestione Progetti</title>
    <title>Flow - Gestione Progetti</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
</head>

<body class="bg-gray-50 text-gray-900">
    <div class="flex h-screen">
        <?php
        require_once 'includes/db.php';

        // Fetch Statuses for Dynamic Coloring
        $statusMap = [];
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM task_statuses WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($statuses as $s) {
                $statusMap[$s['nome']] = $s['colore'];
            }
        }

        function hex2rgba($color, $opacity = false)
        {
            $default = 'rgb(0,0,0)';

            if (empty($color))
                return $default;

            if ($color[0] == '#') {
                $color = substr($color, 1);
            }

            if (strlen($color) == 6) {
                $hex = array($color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]);
            } elseif (strlen($color) == 3) {
                $hex = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
            } else {
                return $default;
            }

            $rgb = array_map('hexdec', $hex);

            if ($opacity) {
                if (abs($opacity) > 1)
                    $opacity = 1.0;
                $output = 'rgba(' . implode(",", $rgb) . ',' . $opacity . ')';
            } else {
                $output = 'rgb(' . implode(",", $rgb) . ')';
            }

            return $output;
        }
        ?>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
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
                    // Recuperiamo i progetti dal DB
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
            </nav>
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
                <button onclick="openSettingsModal()"
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

        <main class="flex-1 p-8 overflow-y-auto relative">

            <button onclick="toggleSidebar()" id="desktopHamburger"
                class="absolute top-4 left-4 z-30 p-2 text-slate-600 focus:outline-none transition-transform hover:scale-110 md:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <?php
            $project_id = $_GET['project_id'] ?? null;
            $project_name = "Seleziona un progetto";

            if ($project_id) {
                if ($project_id) {
                    // Verify ownership
                    // Verify ownership or membership
                    // Verify access level
                    $stmt = $pdo->prepare("
                        SELECT p.nome, pm.role 
                        FROM projects p 
                        LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = ?
                        WHERE p.id = ? AND (
                            p.user_id = ? 
                            OR pm.user_id IS NOT NULL 
                            OR EXISTS (SELECT 1 FROM tasks t JOIN task_assignments ta ON t.id = ta.task_id WHERE t.project_id = p.id AND ta.user_id = ?)
                        )
                    ");
                    $stmt->execute([$_SESSION['user_id'], $project_id, $_SESSION['user_id'], $_SESSION['user_id']]);
                    $project = $stmt->fetch();

                    // Determine if restricted view (Not owner, not member, but has assignments)
                    $isRestrictedView = false;
                    if ($project && $project['role'] === null && $project['nome']) {
                        // Check if truly restricted (not owner) - wait, query checks p.user_id too. 
                        // If p.user_id == me, I am owner.
                        // But we didn't fetch p.user_id in select.
                        // Let's assume if role is null and we found it, it might be owner or restricted.
                        // Actually, let's fetch p.user_id to be sure.
                    }

                    if ($project) {
                        $project_name = $project['nome'];
                    } else {
                        // Project not found or access denied
                        $project_id = null;
                        echo "<div class='bg-red-100 text-red-700 p-4 rounded-lg mb-4'>Progetto non trovato o accesso negato.</div>";
                    }
                }
            } ?>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b pb-6 pt-10 md:pt-12">
                <div class="flex items-center gap-4 md:pl-0">
                    <h2 class="text-3xl font-bold text-slate-800 tracking-tight" id="projectTitleDisplay">
                        <?php echo htmlspecialchars($project_name); ?>
                    </h2>
                    <?php if ($project_id): ?>
                        <div class="flex items-center gap-2">
                            <button
                                onclick="editProject(<?php echo $project_id; ?>, '<?php echo addslashes($project_name); ?>')"
                                class="text-slate-400 hover:text-indigo-600 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                            <button onclick="confirmDeleteProject(<?php echo $project_id; ?>)"
                                class="text-slate-400 hover:text-red-600 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Members Button -->
                    <?php if ($project_id): ?>
                        <button onclick="openMembersModal()"
                            class="ml-4 flex items-center gap-2 text-slate-500 hover:text-indigo-600 transition-colors bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm text-sm"
                            title="Gestisci Membri">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="hidden sm:inline">Membri</span>
                        </button>
                        <!-- Global Project ID for JS -->
                        <script>const CURRENT_PROJECT_ID = <?php echo $project_id; ?>;</script>
                    <?php endif; ?>
                </div>

                <!-- Gemini Magic Button -->
                <?php if ($project_id): ?>
                    <button onclick="openCommitModal()"
                        class="flex items-center gap-2 bg-gradient-to-r from-purple-700 to-indigo-800 text-white px-4 py-2 rounded-lg font-bold hover:shadow-xl hover:opacity-100 transition transform hover:-translate-y-0.5 text-sm shadow-lg border border-indigo-500/50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Genera Commit AI
                    </button>
                <?php endif; ?>

                <div class="relative w-full md:w-64">
                    <input type="text" id="taskSearch" name="search_tasks_unique_v2" autocomplete="off"
                        placeholder="Cerca task..." readonly onfocus="this.removeAttribute('readonly')"
                        onkeyup="searchTasks()"
                        class="w-full bg-white border border-slate-200 rounded-full px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>



            <?php if ($project_id): ?>
                <form action="add_task.php" method="POST" class="mt-6 flex flex-col md:flex-row gap-2">
                    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                    <div class="flex-1 flex flex-col md:flex-row gap-2">
                        <input type="text" name="titolo" placeholder="Aggiungi un nuovo task..." required
                            class="w-full md:flex-1 border border-slate-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-sm">

                        <div class="relative w-full md:w-auto">
                            <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z" />
                                </svg>
                            </div>
                            <input type="text" name="scadenza" datepicker datepicker-autohide datepicker-format="dd/mm/yyyy"
                                datepicker-orientation="bottom right"
                                class="bg-white border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full md:w-40 ps-10 p-3"
                                placeholder="Scadenza">
                        </div>
                    </div>
                    <button type="submit"
                        class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-indigo-700 transition w-full sm:w-auto">Aggiungi</button>
                </form>

                </form>

                <div id="taskList" class="mt-8 space-y-3">
                    <?php
                    // Check if column exists to avoid crash before migration (Optional safety, or just assume migration)
                    // We'll stick to the query. If migration not run, it might error.
                    // Let's assume user runs migration.
                    // Default order: ordinamento ASC (custom), then data_creazione DESC (newest top if tie)
                    $isOwner = false;
                    $isMember = false;

                    $checkRole = $pdo->prepare("SELECT user_id FROM projects WHERE id = ?");
                    $checkRole->execute([$project_id]);
                    $ownerId = $checkRole->fetchColumn();

                    if ($ownerId == $_SESSION['user_id']) {
                        $isOwner = true;
                    } else {
                        $checkMember = $pdo->prepare("SELECT role FROM project_members WHERE project_id = ? AND user_id = ?");
                        $checkMember->execute([$project_id, $_SESSION['user_id']]);
                        if ($checkMember->fetch())
                            $isMember = true;
                    }

                    $sqlTasks = "SELECT t.*, 
                        (CASE WHEN t.scadenza < CURDATE() AND t.stato != 'completato' THEN 1 ELSE 0 END) as is_overdue,
                        GROUP_CONCAT(ta.user_id) as assigned_user_ids
                        FROM tasks t 
                        LEFT JOIN task_assignments ta ON t.id = ta.task_id
                        WHERE t.project_id = ?";

                    $paramsTasks = [$project_id];

                    if (!$isOwner && !$isMember) {
                        // Restricted: Only Assigned Tasks
                        $sqlTasks .= " AND EXISTS (SELECT 1 FROM task_assignments ta2 WHERE ta2.task_id = t.id AND ta2.user_id = ?)";
                        $paramsTasks[] = $_SESSION['user_id'];
                    }

                    $sqlTasks .= " GROUP BY t.id ORDER BY t.ordinamento ASC, t.id DESC";

                    $stmt = $pdo->prepare($sqlTasks);
                    $stmt->execute($paramsTasks);
                    while ($task = $stmt->fetch()):
                        $isDone = ($task['stato'] === 'completato');
                        ?>
                        <div id="task-<?php echo $task['id']; ?>" data-id="<?php echo $task['id']; ?>"
                            onclick="openEditTask(<?php echo htmlspecialchars(json_encode($task)); ?>)"
                            class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center transition-all cursor-pointer hover:shadow-md hover:border-indigo-300 <?php echo $isDone ? 'opacity-50' : ''; ?>">

                            <div class="flex items-center gap-3 overflow-hidden w-full sm:w-auto">
                                <!-- Drag Handle -->
                                <div class="cursor-move drag-handle text-slate-300 hover:text-slate-500"
                                    onclick="event.stopPropagation()">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 8h16M4 16h16" />
                                    </svg>
                                </div>

                                <input type="checkbox" onclick="event.stopPropagation()"
                                    onchange="toggleTask(<?php echo $task['id']; ?>, this.checked)" <?php echo $isDone ? 'checked' : ''; ?>
                                    class="w-5 h-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">

                                <span
                                    class="task-title text-slate-700 font-medium whitespace-nowrap overflow-x-auto block w-full sm:max-w-xs md:max-w-sm <?php echo $isDone ? 'line-through' : ''; ?>"
                                    style="-ms-overflow-style: none; scrollbar-width: none;"> <!-- Hide scrollbar Firefox/IE -->
                                    <style>
                                        /* Hide scrollbar Chrome/Safari/Webkit */
                                        span::-webkit-scrollbar {
                                            display: none;
                                        }
                                    </style>
                                    <?php echo htmlspecialchars($task['titolo']); ?>
                                </span>
                            </div>

                            <div class="flex items-center gap-2 mt-2 sm:mt-0 w-full sm:w-auto justify-end sm:justify-start">
                                <?php if (!empty($task['scadenza'])):
                                    $scadenza = new DateTime($task['scadenza']);
                                    $oggi = new DateTime('today');
                                    $differenza = $oggi->diff($scadenza);
                                    $isScaduto = $scadenza < $oggi && !$isDone;
                                    $isOggi = $scadenza == $oggi && !$isDone;

                                    $dateClass = 'text-slate-400';
                                    if ($isScaduto)
                                        $dateClass = 'text-red-500 font-bold';
                                    if ($isOggi)
                                        $dateClass = 'text-orange-500 font-bold';
                                    ?>
                                    <span class="text-xs <?php echo $dateClass; ?> mr-2 flex items-center gap-1"
                                        title="Scadenza: <?php echo $scadenza->format('d/m/Y'); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <?php echo $scadenza->format('d M'); ?>
                                    </span>
                                <?php endif; ?>

                                <?php
                                $statusColor = $statusMap[$task['stato']] ?? '#64748b'; // Default slate
                                $bgColor = hex2rgba($statusColor, 0.1);
                                $textColor = $statusColor;
                                ?>
                                <div id="task-badge-<?php echo $task['id']; ?>"
                                    onclick="openStatusMenu(event, <?php echo $task['id']; ?>)"
                                    class="text-xs font-bold uppercase px-2 py-1 rounded cursor-pointer hover:opacity-80 transition-opacity"
                                    style="background-color: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>;">
                                    <?php echo str_replace('_', ' ', $task['stato']); ?>
                                </div>
                                <button onclick="event.stopPropagation(); deleteTask(<?php echo $task['id']; ?>)"
                                    class="text-slate-400 hover:text-red-500 p-1 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="mt-10 text-slate-500 italic text-center text-lg">Seleziona un progetto dalla sidebar per iniziare
                    a lavorare.</p>
            <?php endif; ?>
        </main>
    </div>


    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center hidden"
        aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop">
        </div>
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm relative z-10 transform transition-all scale-95 opacity-0"
            id="modalContent">
            <div
                class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2" id="modal-title">Elimina Task</h3>
            <p class="text-slate-500 mb-6 text-sm">Sei sicuro di voler eliminare questo task? Questa azione non può
                essere annullata.</p>
            <div class="flex flex-col sm:flex-row justify-end gap-3">
                <button onclick="closeModal()"
                    class="w-full sm:w-auto px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium transition-colors border border-slate-300">Annulla</button>
                <button onclick="confirmDelete()"
                    class="w-full sm:w-auto px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-bold shadow-lg shadow-red-200 transition-all">Elimina</button>
            </div>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div id="editProjectModal" class="fixed inset-0 z-50 flex items-center justify-center hidden"
        aria-labelledby="modal-title-edit" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity opacity-0"
            id="editProjectBackdrop"></div>
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm relative z-10 transform transition-all scale-95 opacity-0"
            id="editProjectContent">
            <h3 class="text-lg font-bold text-slate-900 mb-4">Rinomina Progetto</h3>
            <input type="text" id="editProjectInput"
                class="w-full border border-slate-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-6"
                placeholder="Nome progetto...">
            <div class="flex flex-col sm:flex-row justify-end gap-3">
                <button onclick="closeEditProjectModal()"
                    class="w-full sm:w-auto px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium transition-colors border border-slate-300">Annulla</button>
                <button onclick="saveProjectName()"
                    class="w-full sm:w-auto px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-bold shadow-lg shadow-indigo-200 transition-all">Salva</button>
            </div>
        </div>
    </div>

    <!-- Delete Project Modal -->
    <div id="deleteProjectModal" class="fixed inset-0 z-50 flex items-center justify-center hidden"
        aria-labelledby="modal-title-delete-project" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity opacity-0"
            id="deleteProjectBackdrop"></div>
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm relative z-10 transform transition-all scale-95 opacity-0"
            id="deleteProjectContent">
            <div
                class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Elimina Progetto</h3>
            <p class="text-slate-500 mb-6 text-sm">Sei sicuro di voler eliminare questo progetto e <strong>tutti i task
                    associati</strong>? Questa azione è irreversibile.</p>
            <div class="flex flex-col sm:flex-row justify-end gap-3">
                <button onclick="closeDeleteProjectModal()"
                    class="w-full sm:w-auto px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium transition-colors border border-slate-300">Annulla</button>
                <button onclick="executeDeleteProject()"
                    class="w-full sm:w-auto px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-bold shadow-lg shadow-red-200 transition-all">Elimina
                    Tutto</button>
            </div>
        </div>
    </div>

    <!-- Edit Task Details Modal -->
    <div id="editTaskModal" class="fixed inset-0 z-50 flex items-center justify-center hidden"
        aria-labelledby="modal-title-edit-task" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity opacity-0" id="editTaskBackdrop"
            onclick="closeEditTaskModal()"></div>
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg md:max-w-3xl relative z-10 transform transition-all scale-95 opacity-0 flex flex-col max-h-[90vh]"
            id="editTaskContent">

            <!-- Modal Header -->
            <div class="p-6 border-b border-slate-100 flex-none">
                <h3 class="text-xl font-bold text-slate-900">Modifica Task</h3>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-6 overflow-y-auto flex-1">
                <input type="hidden" id="editTaskId">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Titolo</label>
                    <input type="text" id="editTaskTitle"
                        class="w-full border border-slate-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="mb-4 flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Scadenza</label>
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z" />
                                </svg>
                            </div>
                            <input type="text" id="editTaskDate" datepicker datepicker-autohide
                                datepicker-format="dd/mm/yyyy" datepicker-orientation="bottom left"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full ps-10 p-3"
                                placeholder="Seleziona data">
                        </div>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Stato</label>
                        <select id="editTaskStatus"
                            class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-3">
                            <!-- Populated via JS -->
                        </select>
                    </div>
                </div>

                <!-- Add Assignees Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Assegnatari</label>
                    <div class="border border-slate-300 rounded-lg p-3 max-h-32 overflow-y-auto bg-gray-50"
                        id="editTaskAssigneesList">
                        <p class="text-xs text-slate-400">Caricamento...</p>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Descrizione</label>
                    <textarea id="editTaskDesc" rows="4"
                        class="w-full border border-slate-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Aggiungi dettagli..."></textarea>
                </div>
            </div>

            <!-- Modal Footer (Fixed) -->
            <div
                class="p-6 border-t border-slate-100 flex flex-col sm:flex-row justify-end gap-3 flex-none bg-gray-50 rounded-b-xl">
                <button onclick="closeEditTaskModal()"
                    class="w-full sm:w-auto px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium transition-colors border border-slate-300">Annulla</button>
                <button onclick="saveTaskChanges()"
                    class="w-full sm:w-auto px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-bold shadow-lg shadow-indigo-200 transition-all">Salva
                    Modifiche</button>
            </div>
        </div>
    </div>

    <!-- AI Settings Modal -->
    <div id="settingsModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm" onclick="closeSettingsModal()"></div>
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md relative z-10">
            <h3 class="text-xl font-bold text-slate-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                Impostazioni
            </h3>
            <p class="text-sm text-slate-500 mb-4">Gestisci la tua API Key e gli stati personalizzati dei task.</p>

            <div class="bg-indigo-50 text-indigo-700 p-4 rounded-lg text-sm mb-4 border border-indigo-100">
                <p class="font-bold mb-1">Non hai una chiave?</p>
                <p class="mb-2">Puoi generarla gratuitamente su Google AI Studio.</p>
                <a href="https://aistudio.google.com/app/apikey" target="_blank"
                    class="inline-flex items-center gap-1 text-indigo-600 font-bold hover:text-indigo-800 hover:underline">
                    Ottieni API Key
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                </a>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Gemini API Key</label>
                <div class="relative">
                    <input type="password" id="geminiKeyInput"
                        placeholder="<?php echo $apiKey ? '••••••••••••••••' : 'AIhaSy...'; ?>"
                        class="w-full border border-slate-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 pr-10">
                    <?php if ($apiKey): ?>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-green-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($apiKey): ?>
                    <p class="text-xs text-green-600 mt-1 font-semibold flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Chiave salvata e attiva.
                    </p>
                <?php else: ?>
                    <p class="text-xs text-slate-400 mt-1">Nessuna chiave salvata.</p>
                <?php endif; ?>
            </div>

            <div class="flex justify-between items-center mt-6">
                <?php if ($apiKey): ?>
                    <button onclick="deleteGeminiKey()"
                        class="text-red-500 hover:text-red-700 text-sm font-medium underline">Elimina Chiave</button>
                <?php else: ?>
                    <div></div> <!-- Spacer -->
                <?php endif; ?>

                <div class="flex gap-3">
                    <button onclick="closeSettingsModal()"
                        class="px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium transition-colors text-sm">Annulla</button>
                    <button onclick="saveGeminiKey()"
                        class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-bold transition-all text-sm">Salva
                        Key</button>
                </div>
            </div>


            <hr class="my-6 border-slate-100">

            <!-- Task Statuses Section -->
            <div>
                <h4 class="text-sm font-bold text-slate-900 uppercase tracking-wide mb-3">Gestione Stati Task</h4>

                <div id="statusListSettings" class="space-y-2 mb-4 max-h-40 overflow-y-auto pr-1">
                    <!-- Populated by JS -->
                </div>

                <div class="flex gap-2 items-end">
                    <div class="flex-1">
                        <label class="text-xs text-slate-500 block mb-1">Nome</label>
                        <input type="text" id="newStatusName" class="w-full border border-slate-300 rounded text-sm p-2"
                            placeholder="Nuovo stato">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 block mb-1">Colore</label>
                        <input type="color" id="newStatusColor"
                            class="h-9 w-12 border border-slate-300 rounded cursor-pointer p-1" value="#6366f1">
                    </div>
                    <button onclick="addNewStatus()"
                        class="h-9 px-3 bg-slate-800 text-white rounded hover:bg-slate-700 transition flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button onclick="closeSettingsModal()"
                    class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium transition-all w-full">Chiudi</button>
            </div>
        </div>
    </div>

    <!-- Generate Commit Modal -->
    <div id="commitModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm" onclick="closeCommitModal()"></div>
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-lg relative z-10 h-[80vh] flex flex-col">
            <div class="flex justify-between items-center mb-2">
                <h3 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-500" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                    Generatore Commit AI
                </h3>
                <button onclick="closeCommitModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="text-sm text-slate-500 mb-4">Seleziona i task completati da includere nel commit.</p>

            <div id="commitTaskList"
                class="flex-1 overflow-y-auto border rounded-lg p-2 mb-4 space-y-2 bg-slate-50 min-h-[150px]">
                <!-- Javascript will populate this -->
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Messaggio Generato</label>
                <textarea id="generatedCommitMsg"
                    class="w-full border border-slate-300 rounded-lg p-3 h-24 text-sm font-mono bg-slate-800 text-green-400 focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Il messaggio apparirà qui..."></textarea>
            </div>

            <div class="flex justify-between items-center gap-3">
                <button onclick="generateCommit()" id="btnGenerate"
                    class="flex-1 px-4 py-2 rounded-lg bg-gradient-to-r from-purple-700 to-indigo-800 text-white hover:opacity-90 font-bold transition-all shadow-lg">
                    ✨ Genera
                </button>
                <button onclick="copyToClipboard()"
                    class="px-4 py-2 rounded-lg bg-slate-200 text-slate-700 hover:bg-slate-300 font-bold transition-all">
                    Copia
                </button>
            </div>

        </div>
        <!-- Bottom close button removed -->
    </div>
    </div>

    <!-- Confirm Delete Key Modal -->
    <div id="confirmKeyDeleteModal" class="fixed inset-0 z-[60] flex items-center justify-center hidden">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm" onclick="closeConfirmKeyDeleteModal()"></div>
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm relative z-10 scale-95 opacity-0 transition-all duration-300"
            id="confirmKeyDeleteContent">
            <h3 class="text-lg font-bold text-slate-900 mb-2">Conferma Eliminazione</h3>
            <p class="text-slate-600 mb-6">Sei sicuro di voler eliminare la tua API Key dal database? Non potrai più
                usare le funzioni AI finché non ne inserirai una nuova.</p>
            <div class="flex justify-end gap-3">
                <button onclick="closeConfirmKeyDeleteModal()"
                    class="px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium transition-colors">Annulla</button>
                <button onclick="executeDeleteKey()"
                    class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-bold transition-all shadow-md">Elimina</button>
            </div>
        </div>
    </div>

    <!-- Members Management Modal -->
    <div id="membersModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm" onclick="closeMembersModal()"></div>
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md relative z-10 flex flex-col max-h-[85vh]">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800">Membri del Progetto</h3>
                <button onclick="closeMembersModal()" class="text-slate-400 hover:text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 flex-1 overflow-y-auto">
                <!-- Add Member Form -->
                <div class="mb-6">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Aggiungi
                        Membro</label>
                    <div class="relative">
                        <input type="text" id="memberSearchInput" placeholder="Cerca username..."
                            onkeyup="searchUsers(this.value)"
                            class="w-full border border-slate-200 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">

                        <!-- Search Results Dropdown -->
                        <div id="userSearchResults"
                            class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-100 shadow-xl rounded-lg hidden z-20 max-h-40 overflow-y-auto">
                        </div>
                    </div>
                </div>

                <!-- Members List -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Membri
                        Attuali</label>
                    <div id="projectMembersList" class="space-y-2">
                        <!-- Populated via JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Delete Status Confirmation Modal (Z-Index 60 to overlay Settings) -->
    <div id="deleteStatusModal" class="fixed inset-0 z-[60] flex items-center justify-center hidden">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity opacity-0"
            id="deleteStatusBackdrop" onclick="closeDeleteStatusModal()"></div>
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm relative z-10 transform transition-all scale-95 opacity-0"
            id="deleteStatusContent">
            <h3 class="text-lg font-bold text-slate-900 mb-2">Elimina Stato</h3>
            <p class="text-slate-600 mb-6">Sei sicuro? I task con questo stato manterranno il nome ma perderanno il
                colore associato.</p>
            <div class="flex justify-end gap-3">
                <button onclick="closeDeleteStatusModal()"
                    class="px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium transition-colors">Annulla</button>
                <button onclick="executeDeleteStatus()"
                    class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-bold transition-all shadow-md">Elimina</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }

        // Inject Statuses from PHP to JS
        let USER_STATUSES = <?php echo json_encode($statuses ?? []); ?>;
        const STATUS_MAP = <?php echo json_encode($statusMap ?? []); ?>;
        let statusHasChanged = false;
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .toast-success {
            background: linear-gradient(to right, #00b09b, #96c93d) !important;
        }

        .toast-error {
            background: linear-gradient(to right, #ff5f6d, #ffc371) !important;
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

        /* Drag & Drop Styling */
        .sortable-ghost {
            opacity: 0.4;
            background-color: #f3f4f6;
            /* gray-100 */
            border: 2px dashed #6366f1;
            /* indigo-500 */
        }

        .sortable-drag {
            cursor: grabbing;
        }

        .sortable-chosen {
            background-color: #e0e7ff;
            /* indigo-50 */
        }
    </style>
    </head>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/datepicker.min.js"></script>
    <script>

        // Toast Helper
        function showToast(message, type = 'success') {
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
                    fontWeight: "600"
                }
            }).showToast();
        }

        let currentDeleteTaskId = null;
        const modal = document.getElementById('deleteModal');
        const backdrop = document.getElementById('modalBackdrop');
        const content = document.getElementById('modalContent');

        // Project Modal Selectors
        let currentEditProjectId = null;
        const editProjectModal = document.getElementById('editProjectModal');
        const editProjectBackdrop = document.getElementById('editProjectBackdrop');
        const editProjectContent = document.getElementById('editProjectContent');
        const editProjectInput = document.getElementById('editProjectInput');

        let currentDeleteProjectId = null;
        const deleteProjectModal = document.getElementById('deleteProjectModal');
        const deleteProjectBackdrop = document.getElementById('deleteProjectBackdrop');
        const deleteProjectContent = document.getElementById('deleteProjectContent');

        // Edit Task Modal Selectors
        const editTaskModal = document.getElementById('editTaskModal');
        const editTaskBackdrop = document.getElementById('editTaskBackdrop');
        const editTaskContent = document.getElementById('editTaskContent');
        const editTaskId = document.getElementById('editTaskId');
        const editTaskTitle = document.getElementById('editTaskTitle');
        const editTaskDate = document.getElementById('editTaskDate');
        const editTaskDesc = document.getElementById('editTaskDesc');

        // EDIT TASK DETAILS
        function openEditTask(task) {
            editTaskId.value = task.id;
            editTaskTitle.value = task.titolo;

            // Convert YYYY-MM-DD to DD/MM/YYYY for display
            if (task.scadenza) {
                const parts = task.scadenza.split('-');
                if (parts.length === 3) {
                    editTaskDate.value = `${parts[2]}/${parts[1]}/${parts[0]}`;
                } else {
                    editTaskDate.value = task.scadenza;
                }
            } else {
                editTaskDate.value = '';
            }

            editTaskDesc.value = task.descrizione || '';

            // Populate Status Dropdown
            populateEditTaskStatus(task.stato);

            editTaskModal.classList.remove('hidden');

            // Load Assignees
            const assigneesList = document.getElementById('editTaskAssigneesList');
            assigneesList.innerHTML = '<p class="text-xs text-slate-400">Caricamento...</p>';

            if (typeof CURRENT_PROJECT_ID !== 'undefined') {
                fetch(`api_collaboration.php?action=list_members&project_id=${CURRENT_PROJECT_ID}`)
                    .then(res => res.json())
                    .then(data => {
                        assigneesList.innerHTML = '';
                        if (data.data) {
                            const currentAssignees = task.assigned_user_ids ? task.assigned_user_ids.toString().split(',') : [];

                            data.data.forEach(m => {
                                const isChecked = currentAssignees.includes(m.id.toString());
                                const div = document.createElement('label');
                                div.className = "flex items-center gap-2 p-2 hover:bg-slate-50 rounded cursor-pointer";
                                div.innerHTML = `
                            <input type="checkbox" value="${m.id}" ${isChecked ? 'checked' : ''} class="assignee-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-slate-700 font-medium">${m.username}</span>
                            ${m.role === 'owner' ? '<span class="text-[10px] bg-amber-100 text-amber-700 px-1 rounded">Owner</span>' : ''}
                        `;
                                assigneesList.appendChild(div);
                            });
                        }
                    });
            }

            setTimeout(() => {
                editTaskBackdrop.classList.remove('opacity-0');
                editTaskContent.classList.remove('opacity-0', 'scale-95');
                editTaskContent.classList.add('scale-100');
            }, 10);
        }

        function saveTaskChanges() {
            // defined in outer scope but lets override or ensure we update existing one
            // Wait, saveTaskChanges is NOT defined in this Chunk?
            // It is typically defined later. I should assume I need to find it and replace it.
            // But for now, I am editing openEditTask.
        }

        function populateEditTaskStatus(currentStatus) {
            const select = document.getElementById('editTaskStatus');
            select.innerHTML = '';

            // Use USER_STATUSES defined at top
            if (!USER_STATUSES || USER_STATUSES.length === 0) {
                // Fallback if empty
                select.innerHTML = '<option value="da_fare">Da Fare</option>';
                return;
            }

            USER_STATUSES.forEach(s => {
                const option = document.createElement('option');
                option.value = s.nome;
                option.textContent = s.nome.replace(/_/g, ' ').toUpperCase();
                if (s.nome === currentStatus) option.selected = true;
                select.appendChild(option);
            });

            // If current status is not in the list (e.g. was deleted), add it temporarily
            const exists = USER_STATUSES.find(s => s.nome === currentStatus);
            if (!exists && currentStatus) {
                const option = document.createElement('option');
                option.value = currentStatus;
                option.textContent = currentStatus.replace(/_/g, ' ') + ' (Archiviato)';
                option.selected = true;
                select.appendChild(option);
            }
        }

        function closeEditTaskModal() {
            editTaskBackdrop.classList.add('opacity-0');
            editTaskContent.classList.remove('scale-100');
            editTaskContent.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                editTaskModal.classList.add('hidden');
            }, 300);
        }

        function saveTaskChanges() {
            const id = editTaskId.value;
            const titolo = editTaskTitle.value.trim();
            const scadenza = editTaskDate.value;
            const descrizione = editTaskDesc.value.trim();
            const status = document.getElementById('editTaskStatus').value;

            if (!titolo) return;

            fetch('update_task.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: id,
                    titolo: titolo,
                    scadenza: scadenza,
                    descrizione: descrizione,
                    stato: status
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert("Errore aggiornamento task");
                    }
                });
        }

        function toggleTask(taskId, isChecked) {
            const taskElement = document.getElementById(`task-${taskId}`);
            const textElement = taskElement.querySelector('span');
            const badgeElement = document.getElementById(`task-badge-${taskId}`);

            // Feedback visivo immediato (Ottimismo UI)
            // Determine new status
            const newStatus = isChecked ? 'completato' : 'da_fare';

            // Find status color
            let color = isChecked ? '#16a34a' : '#64748b'; // defaults
            if (USER_STATUSES) {
                const s = USER_STATUSES.find(st => st.nome === newStatus);
                if (s) color = s.colore;
            }

            // Apply Styles
            badgeElement.textContent = newStatus.replace(/_/g, ' ');
            badgeElement.style.backgroundColor = hex2rgbaJS(color, 0.1);
            badgeElement.style.color = color;
            // Reset classes to ensure base consistency (remove potential old overrides if any, though style does work)
            // Actually, let's just trust style updates.

            if (isChecked) {
                taskElement.classList.add('opacity-50');
                textElement.classList.add('line-through');
            } else {
                taskElement.classList.remove('opacity-50');
                textElement.classList.remove('line-through');
            }

            // Chiamata al database
            fetch('update_task.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: taskId,
                    completato: isChecked
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert("Errore durante l'aggiornamento del task");
                        location.reload(); // In caso di errore ricarica per ripristinare lo stato reale
                    }
                });
        }

        // --- Quick Status Menu ---
        function openStatusMenu(event, taskId) {
            event.stopPropagation();

            // Remove existing menus
            document.querySelectorAll('.status-menu-popover').forEach(el => el.remove());

            if (!USER_STATUSES || USER_STATUSES.length === 0) return;

            const menu = document.createElement('div');
            menu.className = "status-menu-popover absolute z-50 bg-white rounded-lg shadow-xl border border-slate-200 p-2 min-w-[150px] flex flex-col gap-1";

            // Position logic
            const rect = event.currentTarget.getBoundingClientRect();
            menu.style.top = (window.scrollY + rect.bottom + 5) + 'px';
            menu.style.left = (window.scrollX + rect.left) + 'px';

            USER_STATUSES.forEach(s => {
                const btn = document.createElement('button');
                btn.className = "flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded text-left transition-colors";
                btn.innerHTML = `<div class="w-3 h-3 rounded-full" style="background-color: ${s.colore}"></div> ${s.nome.replace(/_/g, ' ')}`;
                btn.onclick = (e) => {
                    e.stopPropagation();
                    changeStatusQuick(taskId, s.nome, s.colore);
                    menu.remove();
                };
                menu.appendChild(btn);
            });

            // Click outside to close
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target)) {
                    menu.remove();
                    document.removeEventListener('click', closeMenu);
                }
            });

            document.body.appendChild(menu);
        }

        function changeStatusQuick(taskId, newStatus, newColor) {
            // UI Optimistic Update
            const badge = document.getElementById(`task-badge-${taskId}`);
            const taskElement = document.getElementById(`task-${taskId}`);
            const textElement = taskElement.querySelector('.task-title');
            const checkbox = taskElement.querySelector('input[type="checkbox"]');

            if (badge) {
                badge.textContent = newStatus.replace(/_/g, ' ');
                badge.style.backgroundColor = hex2rgbaJS(newColor, 0.1);
                badge.style.color = newColor;
            }

            // Handle Visual State for 'completato'
            if (newStatus === 'completato') {
                taskElement.classList.add('opacity-50');
                if (textElement) textElement.classList.add('line-through');
                if (checkbox) checkbox.checked = true;
            } else {
                taskElement.classList.remove('opacity-50');
                if (textElement) textElement.classList.remove('line-through');
                if (checkbox) checkbox.checked = false;
            }

            fetch('update_task.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: taskId,
                    stato: newStatus
                })
            }).then(res => res.json()).then(data => {
                if (!data.success) location.reload();
            });
        }

        function hex2rgbaJS(hex, alpha) {
            let r = 0, g = 0, b = 0;
            if (hex.length === 4) {
                r = parseInt(hex[1] + hex[1], 16);
                g = parseInt(hex[2] + hex[2], 16);
                b = parseInt(hex[3] + hex[3], 16);
            } else if (hex.length === 7) {
                r = parseInt(hex.substring(1, 3), 16);
                g = parseInt(hex.substring(3, 5), 16);
                b = parseInt(hex.substring(5, 7), 16);
            }
            return `rgba(${r},${g},${b},${alpha})`;
        }

        // Funzione di Ricerca (Filtra i task visibili)
        function searchTasks() {
            const input = document.getElementById('taskSearch');
            const filter = input.value.toLowerCase();
            const tasks = document.querySelectorAll('[id^="task-"]');

            tasks.forEach(task => {
                // Ignora il badge e altri elementi che potrebbero avere id che inizia con task-
                if (!task.classList.contains('bg-white')) return;

                const text = task.querySelector('span').textContent.toLowerCase();
                task.style.display = text.includes(filter) ? '' : 'none';
            });
        }

        // Funzione di Eliminazione - Apre Modale
        function deleteTask(taskId) {
            currentDeleteTaskId = taskId;

            modal.classList.remove('hidden');
            // Animation in
            setTimeout(() => {
                backdrop.classList.remove('opacity-0');
                content.classList.remove('opacity-0', 'scale-95');
                content.classList.add('scale-100');
            }, 10);
        }

        function closeModal() {
            currentDeleteTaskId = null;
            // Animation out
            backdrop.classList.add('opacity-0');
            content.classList.remove('scale-100');
            content.classList.add('opacity-0', 'scale-95');

            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function confirmDelete() {
            if (!currentDeleteTaskId) return;

            const taskId = currentDeleteTaskId;

            fetch('delete_task.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: taskId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const taskEl = document.getElementById(`task-${taskId}`);
                        // Animate removal
                        taskEl.style.transform = 'translateX(20px)';
                        taskEl.style.opacity = '0';
                        setTimeout(() => {
                            taskEl.remove();
                        }, 300);
                    }
                    closeModal();
                });
        }

        // PROJECT MANAGEMENT

        // EDIT PROJECT
        function editProject(id, currentName) {
            currentEditProjectId = id;
            editProjectInput.value = currentName;

            editProjectModal.classList.remove('hidden');
            // Animation in
            setTimeout(() => {
                editProjectBackdrop.classList.remove('opacity-0');
                editProjectContent.classList.remove('opacity-0', 'scale-95');
                editProjectContent.classList.add('scale-100');
                editProjectInput.focus();
            }, 10);
        }

        function closeEditProjectModal() {
            currentEditProjectId = null;
            // Animation out
            editProjectBackdrop.classList.add('opacity-0');
            editProjectContent.classList.remove('scale-100');
            editProjectContent.classList.add('opacity-0', 'scale-95');

            setTimeout(() => {
                editProjectModal.classList.add('hidden');
            }, 300);
        }

        function saveProjectName() {
            if (!currentEditProjectId) return;
            const newName = editProjectInput.value.trim();

            if (newName) {
                fetch('edit_project.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: currentEditProjectId, nome: newName })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert("Errore: " + (data.error || "Impossibile aggiornare"));
                        }
                        closeEditProjectModal();
                    });
            }
        }

        // DELETE PROJECT
        function confirmDeleteProject(id) {
            currentDeleteProjectId = id;

            deleteProjectModal.classList.remove('hidden');
            // Animation in
            setTimeout(() => {
                deleteProjectBackdrop.classList.remove('opacity-0');
                deleteProjectContent.classList.remove('opacity-0', 'scale-95');
                deleteProjectContent.classList.add('scale-100');
            }, 10);
        }

        function closeDeleteProjectModal() {
            currentDeleteProjectId = null;
            // Animation out
            deleteProjectBackdrop.classList.add('opacity-0');
            deleteProjectContent.classList.remove('scale-100');
            deleteProjectContent.classList.add('opacity-0', 'scale-95');

            setTimeout(() => {
                deleteProjectModal.classList.add('hidden');
            }, 300);
        }

        function executeDeleteProject() {
            if (!currentDeleteProjectId) return;

            fetch('delete_project.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: currentDeleteProjectId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = "index.php"; // Redirect home
                    } else {
                        alert("Errore: " + (data.error || "Impossibile eliminare"));
                    }
                    closeDeleteProjectModal();
                });
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const desktopHamburger = document.getElementById('desktopHamburger');
            const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');

            // Detect Mobile vs Desktop
            if (window.innerWidth >= 768) {
                // Desktop Logic: Toggle Width
                sidebar.classList.toggle('desktop-closed');

                // Toggle Button Visibility based on state
                if (sidebar.classList.contains('desktop-closed')) {
                    // Sidebar Closed -> Show Hamburger (Remove md:hidden)
                    desktopHamburger.classList.remove('md:hidden');
                } else {
                    // Sidebar Open -> Hide Hamburger (Show Close inside Sidebar)
                    desktopHamburger.classList.add('md:hidden');
                }

            } else {
                // Mobile Logic
                sidebar.classList.toggle('mobile-closed');

                if (sidebar.classList.contains('mobile-closed')) {
                    overlay.classList.add('hidden');
                } else {
                    overlay.classList.remove('hidden');
                }
            }
        }
        // Init Flatpickr & Sortable
        document.addEventListener('DOMContentLoaded', function () {
            // Init Datepicker (fallback/custom)
            const datepickers = document.querySelectorAll('[datepicker]');
            // Flowbite datepicker auto-inits based on attributes, but flatpickr override:
            // If we use Flowbite's JS, we don't need this block unless we want custom Logic.
            // We are using Flowbite CDN which auto-inits.

            // SortableJS Init
            const el = document.getElementById('taskList');
            if (el) {
                Sortable.create(el, {
                    handle: '.drag-handle', // Drag handle selector within list items
                    animation: 150,
                    ghostClass: 'sortable-ghost',  // Class name for the drop placeholder
                    chosenClass: 'sortable-chosen',  // Class name for the chosen item
                    dragClass: 'sortable-drag',  // Class name for the dragging item
                    onEnd: function (evt) {
                        const itemEl = evt.item;  // dragged HTMLElement

                        // Get all task IDs in new order
                        const tasks = document.querySelectorAll('#taskList > div');
                        const order = Array.from(tasks).map(task => task.getAttribute('data-id'));

                        // Send to backend
                        fetch('reorder_tasks.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ order: order })
                        });
                    }
                });
            }
        });

        // --- GEMINI AI & SETTINGS ---
        const settingsModal = document.getElementById('settingsModal');
        const commitModal = document.getElementById('commitModal');
        const geminiKeyInput = document.getElementById('geminiKeyInput');
        const commitTaskList = document.getElementById('commitTaskList');
        const generatedCommitMsg = document.getElementById('generatedCommitMsg');
        const btnGenerate = document.getElementById('btnGenerate');

        // Server-side API KEY passed to JS
        const HAS_API_KEY = <?php echo $apiKey ? 'true' : 'false'; ?>;
        const USER_API_KEY = "<?php echo $apiKey ? $apiKey : ''; ?>";
        // ^ SECURITY NOTE: Outputting key in source is generally okay-ish for private apps but ideally we proxy requests.
        // For this architecture (BYOK), user provides key, sending it back to them is standard.

        function openSettingsModal() {
            geminiKeyInput.value = ''; // Clear input for security/ux
            settingsModal.classList.remove('hidden');
            renderStatusSettings();
        }

        function renderStatusSettings() {
            const listContainer = document.getElementById('statusListSettings');
            if (!USER_STATUSES || USER_STATUSES.length === 0) {
                listContainer.innerHTML = '<p class="text-sm text-slate-400 italic">Nessuno stato personalizzato.</p>';
            } else {
                listContainer.innerHTML = USER_STATUSES.map(s => `
                <div id="status-row-${s.id}" class="flex items-center gap-2 p-2 bg-slate-50 rounded border border-slate-200 transition-colors hover:bg-white hover:shadow-sm">
                    <input type="color" 
                           value="${s.colore}" 
                           onchange="updateStatus(${s.id}, this.value, 'colore')"
                           class="w-6 h-6 rounded border-none cursor-pointer p-0 bg-transparent" 
                           title="Modifica Colore">
                    
                    <input type="text" 
                           value="${s.nome.replace(/_/g, ' ')}" 
                           onchange="updateStatus(${s.id}, this.value, 'nome')"
                           class="flex-1 text-sm text-slate-700 bg-transparent border-none focus:ring-0 px-1 hover:bg-slate-100 rounded"
                           placeholder="Nome stato">
                           
                    <button onclick="deleteStatus(${s.id})" class="text-slate-400 hover:text-red-500 transition px-2" title="Elimina Stato">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            `).join('');
            }
        }

        function updateStatus(id, value, field) {
            const s = USER_STATUSES.find(st => st.id == id);
            if (!s) return;

            // Optimistic update local
            if (field === 'nome') {
                s.nome = value.trim().toLowerCase().replace(/\s+/g, '_');
                // Re-render handled by input value, but let's ensure consistency?
                // Actually, keep input as is, just update model.
            } else if (field === 'colore') {
                s.colore = value;
            }

            statusHasChanged = true;

            // Send to API
            fetch('api_statuses.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: s.id,
                    nome: s.nome,
                    colore: s.colore
                })
            }).then(res => res.json()).then(data => {
                if (!data.success) {
                    showToast('Errore aggiornamento: ' + data.error, 'error');
                } else {
                    // Determine styling for success feedback? Maybe subtle border flash?
                }
            });
        }

        function addNewStatus() {
            const nameInput = document.getElementById('newStatusName');
            const colorInput = document.getElementById('newStatusColor');
            const name = nameInput.value.trim().toLowerCase().replace(/\s+/g, '_');
            const color = colorInput.value;

            if (!name) {
                showToast('Inserisci un nome per lo stato.', 'error');
                return;
            }

            fetch('api_statuses.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nome: name, colore: color })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Stato creato!', 'success');
                        // Local Update
                        USER_STATUSES.push({
                            id: data.id,
                            nome: name,
                            colore: color,
                            ordine: 999
                        });
                        renderStatusSettings();
                        statusHasChanged = true;

                        // Clear inputs
                        nameInput.value = '';
                        // Keep color or reset?
                    } else {
                        showToast('Errore: ' + data.error, 'error');
                    }
                });
        }

        // --- STATUS DELETION MODAL LOGIC ---
        let currentDeleteStatusId = null;
        const deleteStatusModal = document.getElementById('deleteStatusModal');
        const deleteStatusBackdrop = document.getElementById('deleteStatusBackdrop');
        const deleteStatusContent = document.getElementById('deleteStatusContent');

        function deleteStatus(id) {
            // Open Modal instead of confirm()
            currentDeleteStatusId = id;
            deleteStatusModal.classList.remove('hidden');
            setTimeout(() => {
                deleteStatusBackdrop.classList.remove('opacity-0');
                deleteStatusContent.classList.remove('opacity-0', 'scale-95');
                deleteStatusContent.classList.add('scale-100');
            }, 10);
        }

        function closeDeleteStatusModal() {
            currentDeleteStatusId = null;
            deleteStatusBackdrop.classList.add('opacity-0');
            deleteStatusContent.classList.remove('scale-100');
            deleteStatusContent.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                deleteStatusModal.classList.add('hidden');
            }, 300);
        }

        function executeDeleteStatus() {
            if (!currentDeleteStatusId) return;

            fetch('api_statuses.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: currentDeleteStatusId })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Stato eliminato.', 'success');
                        // Local Remove
                        USER_STATUSES = USER_STATUSES.filter(s => s.id != currentDeleteStatusId);
                        renderStatusSettings();
                        statusHasChanged = true;
                    } else {
                        showToast('Errore: ' + data.error, 'error');
                    }
                    closeDeleteStatusModal();
                });
        }

        function closeSettingsModal() {
            settingsModal.classList.add('hidden');
            if (typeof statusHasChanged !== 'undefined' && statusHasChanged) {
                location.reload();
            }
        }

        function saveGeminiKey() {
            const key = geminiKeyInput.value.trim();
            if (key) {
                // Save to DB
                fetch('api_key_manager.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'save', key: key })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Chiave salvata nel tuo account!', 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast('Errore salvataggio: ' + data.error, 'error');
                        }
                    });
            } else {
                showToast('Inserisci una chiave valida.', 'error');
            }
        }

        function deleteGeminiKey() {
            // Open Modal instead of confirm() alert
            const modal = document.getElementById('confirmKeyDeleteModal');
            const content = document.getElementById('confirmKeyDeleteContent');

            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeConfirmKeyDeleteModal() {
            const modal = document.getElementById('confirmKeyDeleteModal');
            const content = document.getElementById('confirmKeyDeleteContent');

            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function executeDeleteKey() {
            fetch('api_key_manager.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete' })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Chiave rimossa.', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Errore: ' + data.error, 'error');
                    }
                    closeConfirmKeyDeleteModal();
                });
        }

        function closeConfirmKeyDeleteModal() {
            const modal = document.getElementById('confirmKeyDeleteModal');
            const content = document.getElementById('confirmKeyDeleteContent');

            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function executeDeleteKey() {
            fetch('api_key_manager.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete' })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Chiave rimossa.', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Errore: ' + data.error, 'error');
                    }
                    closeConfirmKeyDeleteModal();
                });
        }

        function openCommitModal() {
            // Use PHP state check
            if (!HAS_API_KEY) {
                // Simply open settings modal directly
                openSettingsModal();
                // Optional: You could show a specialized message inside settings like "Please set key first"
                return;
            }

            // 1. Gather tasks
            const tasks = [];
            document.querySelectorAll('[id^="task-"]').forEach(el => {
                const id = el.getAttribute('id').replace('task-', '');

                const titleEl = el.querySelector('.task-title');
                if (!titleEl) return;
                const isDone = titleEl.classList.contains('line-through');
                const text = titleEl.innerText.trim();

                // Parse task data from onclick attribute
                const onclickStr = el.getAttribute('onclick');
                let description = '';
                if (onclickStr) {
                    try {
                        // regex to extract JSON between openEditTask( and )
                        const match = onclickStr.match(/openEditTask\((.*)\)/);
                        if (match && match[1]) {
                            const taskData = JSON.parse(match[1]);
                            description = taskData.descrizione || '';
                        }
                    } catch (e) { console.error("Error parsing task data", e); }
                }

                if (isDone) {
                    tasks.push({ id, text, description });
                }
            });

            if (tasks.length === 0) {
                showToast("Nessun task completato trovato nella pagina.", "error");
                return;
            }

            // 2. Populate List
            commitTaskList.innerHTML = tasks.map(t => `
            <label class="flex items-center gap-3 p-2 hover:bg-white rounded cursor-pointer border border-transparent hover:border-indigo-100">
                <input type="checkbox" value="${t.text}" data-description="${t.description.replace(/"/g, '&quot;')}" checked class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500">
                <span class="text-sm text-slate-700 truncate">${t.text}</span>
            </label>
        `).join('');

            generatedCommitMsg.value = '';
            commitModal.classList.remove('hidden');
        }

        function closeCommitModal() {
            commitModal.classList.add('hidden');
        }

        async function getAvailableModel(apiKey) {
            try {
                const listResponse = await fetch(`https://generativelanguage.googleapis.com/v1beta/models?key=${apiKey}`);
                const data = await listResponse.json();

                if (data.error) throw new Error(data.error.message);
                if (!data.models) throw new Error("Nessun modello trovato.");

                // Priority list
                const priorities = [
                    'models/gemini-1.5-flash',
                    'models/gemini-1.5-flash-latest',
                    'models/gemini-1.5-pro',
                    'models/gemini-1.5-pro-latest',
                    'models/gemini-1.0-pro',
                    'models/gemini-pro'
                ];

                // 1. Check for priority match
                for (const preferred of priorities) {
                    if (data.models.find(m => m.name === preferred && m.supportedGenerationMethods.includes('generateContent'))) {
                        return preferred.replace('models/', '');
                    }
                }

                // 2. Fallback: first available gemini model that supports generation
                const fallback = data.models.find(m => m.name.includes('gemini') && m.supportedGenerationMethods.includes('generateContent'));
                if (fallback) return fallback.name.replace('models/', '');

                throw new Error("Nessun modello Gemini compatibile trovato.");

            } catch (e) {
                console.warn("Model discovery failed, falling back to default.", e);
                return 'gemini-1.5-flash'; // Hard fallback
            }
        }

        async function generateCommit() {
            const checkboxes = commitTaskList.querySelectorAll('input[type="checkbox"]:checked');
            const selectedTasks = Array.from(checkboxes).map(cb => {
                return {
                    title: cb.value,
                    description: cb.getAttribute('data-description') || ''
                };
            });

            if (selectedTasks.length === 0) {
                showToast("Seleziona almeno un task.", "error");
                return;
            }

            const apiKey = USER_API_KEY; // Use server-provided key logic

            let taskListStr = selectedTasks.map(t => {
                return `- Title: ${t.title}\n  Description: ${t.description}`;
            }).join('\n');

            const prompt = `Act as a senior developer. Generate a single comprehensive Conventional Commit message (type(scope): description) IN ITALIAN for these completed tasks: \n${taskListStr}\n\nOnly output the git commit command like: git commit -m "..."`;

            btnGenerate.disabled = true;
            btnGenerate.innerHTML = "Ricerca Modello...";
            generatedCommitMsg.value = "Controllo modelli disponibili...";

            try {
                // Dynamic Model Selection
                const modelName = await getAvailableModel(apiKey);

                btnGenerate.innerHTML = "Generazione...";
                generatedCommitMsg.value = `Modello trovato: ${modelName}. Generazione in corso...`;

                const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${modelName}:generateContent?key=${apiKey}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        contents: [{ parts: [{ text: prompt }] }]
                    })
                });

                const data = await response.json();

                if (data.error) {
                    // If 429 (Quota), show clear message
                    if (data.error.code === 429) {
                        throw new Error("Quota esaurita per questo modello. Riprova più tardi.");
                    }
                    throw new Error(data.error.message);
                }

                const result = data.candidates[0].content.parts[0].text;
                generatedCommitMsg.value = result.trim();

            } catch (error) {
                console.error(error);
                generatedCommitMsg.value = "Errore: " + error.message;
            } finally {
                btnGenerate.disabled = false;
                btnGenerate.innerHTML = "✨ Genera";
            }
        }

        function copyToClipboard() {
            const text = generatedCommitMsg.value;
            if (!text) return;
            navigator.clipboard.writeText(text).then(() => {
                showToast("Messaggio copiato!", "success");
            });
        }




        // --- MEMBERS MANAGEMENT ---
        const membersModal = document.getElementById('membersModal');
        const memberSearchInput = document.getElementById('memberSearchInput');
        const userSearchResults = document.getElementById('userSearchResults');
        const projectMembersList = document.getElementById('projectMembersList');

        function openMembersModal() {
            if (!CURRENT_PROJECT_ID) return;
            membersModal.classList.remove('hidden');
            loadProjectMembers();
            memberSearchInput.value = '';
            userSearchResults.classList.add('hidden');
        }

        function closeMembersModal() {
            membersModal.classList.add('hidden');
        }

        function searchUsers(query) {
            if (query.length < 3) {
                userSearchResults.classList.add('hidden');
                return;
            }

            fetch(`api_collaboration.php?action=search_users&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    userSearchResults.innerHTML = '';
                    if (data.data && data.data.length > 0) {
                        data.data.forEach(u => {
                            const div = document.createElement('div');
                            div.className = "p-3 hover:bg-slate-50 cursor-pointer text-sm text-slate-700 border-b last:border-0 border-slate-50";
                            div.textContent = u.username;
                            div.onclick = () => addMember(u.username);
                            userSearchResults.appendChild(div);
                        });
                        userSearchResults.classList.remove('hidden');
                    } else {
                        userSearchResults.classList.add('hidden');
                    }
                });
        }

        function addMember(username) {
            fetch('api_collaboration.php?action=add_member', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: CURRENT_PROJECT_ID, username: username })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast(`Utente ${username} aggiunto!`, 'success');
                        memberSearchInput.value = '';
                        userSearchResults.classList.add('hidden');
                        loadProjectMembers();
                    } else {
                        showToast(data.error, 'error');
                    }
                });
        }

        function removeMember(userId) {
            if (!confirm("Rimuovere questo membro?")) return;

            fetch('api_collaboration.php?action=remove_member', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: CURRENT_PROJECT_ID, user_id: userId })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Membro rimosso.', 'success');
                        loadProjectMembers();
                    } else {
                        showToast(data.error, 'error');
                    }
                });
        }

        function loadProjectMembers() {
            fetch(`api_collaboration.php?action=list_members&project_id=${CURRENT_PROJECT_ID}`)
                .then(res => res.json())
                .then(data => {
                    projectMembersList.innerHTML = '';
                    if (data.data) {
                        data.data.forEach(m => {
                            const div = document.createElement('div');
                            div.className = "flex justify-between items-center p-3 bg-slate-50 rounded-lg border border-slate-100";

                            let roleBadge = m.role === 'owner'
                                ? '<span class="text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-bold uppercase tracking-wide">Owner</span>'
                                : '<span class="text-[10px] bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full font-bold uppercase tracking-wide">Membro</span>';

                            const removeBtn = m.role === 'owner'
                                ? '' // Cannot remove owner
                                : `<button onclick="removeMember(${m.id})" class="text-slate-400 hover:text-red-500 p-1"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>`;

                            div.innerHTML = `
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs border border-indigo-200">
                                    ${m.username.substring(0, 2).toUpperCase()}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-slate-800">${m.username}</div>
                                </div>
                                ${roleBadge}
                            </div>
                            ${removeBtn}
                        `;
                            projectMembersList.appendChild(div);
                        });
                    }
                });
        }
    </script>
</body>

</html>