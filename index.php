<?php
//header("Cross-Origin-Opener-Policy: same-origin-allow-popups");

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

// Fetch Global Role and Team Admin status
$stmt = $pdo->prepare("SELECT global_role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userGlobalRole = $stmt->fetchColumn();

// Check if is admin of ANY team
$stmt = $pdo->prepare("SELECT 1 FROM team_members WHERE user_id = ? AND role = 'admin' LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$isAnyTeamAdmin = $stmt->fetchColumn();

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


        <?php include 'includes/sidebar.php'; ?>


        <main class="flex-1 p-8 overflow-y-auto relative">

            <button onclick="toggleSidebar()" id="desktopHamburger"
                class="absolute top-4 left-4 z-30 p-2 text-slate-600 focus:outline-none transition-transform hover:scale-110 md:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </button>
            <?php include 'includes/header_profile_widget.php'; ?>
            <?php include 'includes/project_details_modal.php'; ?>
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
                    <h2 class="text-3xl font-bold text-slate-800 tracking-tight cursor-pointer hover:text-indigo-600 transition-colors"
                        id="projectTitleDisplay" onclick="openProjectDetails('info')"
                        title="Clicca per i dettagli del progetto">
                        <?php echo htmlspecialchars($project_name); ?>
                    </h2>
                    <?php if ($project_id): ?>
                        <div class="flex items-center gap-2">
                            <!-- Title is now the Edit trigger -->
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
                        <button onclick="openProjectDetails('members')"
                            class="ml-4 flex items-center gap-2 text-slate-500 hover:text-indigo-600 transition-colors bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-sm text-sm"
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

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between items-center">
                        <span>Allegati</span>
                        <button onclick="openDrivePicker()" type="button"
                            class="text-indigo-600 hover:text-indigo-800 text-xs font-bold flex items-center gap-1 bg-indigo-50 px-2 py-1 rounded hover:bg-indigo-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10.5 3.75a6 6 0 0 0-5.98 6.496A5.25 5.25 0 0 0 6.75 20.25H18a4.5 4.5 0 0 0 2.206-8.423 3.75 3.75 0 0 0-4.133-4.303A6.001 6.001 0 0 0 10.5 3.75Zm2.03 5.47a.75.75 0 0 0-1.06 0l-3 3a.75.75 0 1 0 1.06 1.06l1.72-1.72v4.94a.75.75 0 0 0 1.5 0v-4.94l1.72 1.72a.75.75 0 1 0 1.06-1.06l-3-3Z"
                                    clip-rule="evenodd" />
                            </svg>
                            Carica File
                        </button>
                    </label>

                    <div id="editTaskAttachmentsList" class="flex flex-col gap-2">
                        <!-- Populated via JS -->
                        <p class="text-xs text-slate-400 italic" id="noAttachmentsMsg">Nessun allegato.</p>
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

    <!-- Drive Auth Modal -->
    <div id="driveAuthModal" class="fixed inset-0 z-[60] flex items-center justify-center hidden" aria-modal="true">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm" onclick="closeDriveAuthModal()"></div>
        <div
            class="bg-white rounded-xl shadow-2xl w-full max-w-sm relative z-10 p-6 flex flex-col items-center text-center">
            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" viewBox="0 0 24 24"
                    fill="currentColor">
                    <path
                        d="M23.64 7l-3.32-5.76a1 1 0 0 0-.86-.49H8.54a1 1 0 0 0-.86.49L4.36 7h19.28zM12 13.5l-2.93-5.07H3.07a1 1 0 0 0 .86 1.5l8.07 13.98c.2.35.58.58.98.6a1 1 0 0 0 .88-.5l2.93-5.07h-4.79zM15.4 12.07l2.92 5.06 3.32-5.75a1 1 0 0 0 0-1L18.32 5.07a1 1 0 0 0-.86-.5h-5.85l3.79 7.5z" />
                </svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900 mb-2">Connetti Google Drive</h3>
            <p class="text-slate-500 text-sm mb-6">
                Per allegare file è necessario l'accesso a Google Drive. Clicca su "Concedi Permesso" per abilitare
                l'integrazione.
            </p>
            <div class="flex gap-3 w-full">
                <button onclick="closeDriveAuthModal()"
                    class="flex-1 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 font-medium">Annulla</button>
                <button onclick="driveAuthUpgrade()"
                    class="flex-1 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-bold shadow-md">Concedi
                    Permesso</button>
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

    <!-- Members Modal removed (replaced by project_details_modal) -->

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

        // Inject API Key State
        const HAS_API_KEY = <?php echo !empty($apiKey) ? 'true' : 'false'; ?>;
        const USER_API_KEY = "<?php echo $apiKey ?? ''; ?>";
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

        /* Sidebar CSS moved to includes/sidebar.php */


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
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="js/shared.js?v=<?php echo time(); ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/datepicker.min.js"></script>
    <script src="js/index_logic.js"></script>

    <script>
        window.GOOGLE_CONFIG = {
            clientId: "<?php echo getenv('GOOGLE_CLIENT_ID'); ?>",
            apiKey: "<?php echo getenv('GOOGLE_API_KEY'); ?>"
        };
    </script>
    <!-- Google Auto-Sync Logic (Headless) -->
    <script src="js/google_config.js"></script>
    <script src="js/google_calendar.js"></script>
    <script src="js/google_drive.js"></script>
    <script async defer src="https://apis.google.com/js/api.js" onload="gapiLoaded()"></script>
    <script async defer src="https://accounts.google.com/gsi/client" onload="gisLoaded()"></script>
</body>

</html>