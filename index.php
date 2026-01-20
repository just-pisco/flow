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
    $stmt = $pdo->query("SELECT id FROM projects ORDER BY data_modifica DESC LIMIT 1");
    $lastProject = $stmt->fetch();
    if ($lastProject) {
        header("Location: index.php?project_id=" . $lastProject['id']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow - Gestione Progetti</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 text-gray-900">
    <div class="flex h-screen">
        <?php require_once 'includes/db.php'; ?>
        <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden glass"></div>
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white p-6 transform -translate-x-full md:translate-x-0 md:relative transition-transform duration-300 ease-in-out shadow-xl">

            <div class="flex justify-between items-center md:block">
                <a href="index.php" class="text-2xl font-bold tracking-tight text-indigo-400 no-underline hover:text-indigo-300 transition block">Flow.</a>
                <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                    // Recuperiamo i progetti dal DB
                    $stmt = $pdo->query("SELECT * FROM projects ORDER BY data_modifica DESC");
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
                <a href="export_data.php" class="flex items-center gap-3 text-slate-400 hover:text-indigo-400 transition-colors p-2 rounded-md hover:bg-slate-800" title="Scarica Backup JSON">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span class="font-medium text-sm">Backup Dati</span>
                </a>
                <a href="logout.php" class="flex items-center gap-3 text-slate-400 hover:text-white transition-colors p-2 rounded-md hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="font-medium text-sm">Esci</span>
                </a>
            </div>
        </aside>

        <main class="flex-1 p-8 overflow-y-auto relative">

            <button onclick="toggleSidebar()" class="md:hidden absolute top-4 left-4 z-30 p-2 text-slate-600 focus:outline-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <?php
            $project_id = $_GET['project_id'] ?? null;
            $project_name = "Seleziona un progetto";

            if ($project_id) {
                // Recupera nome progetto
                $stmt = $pdo->prepare("SELECT nome FROM projects WHERE id = ?");
                $stmt->execute([$project_id]);
                $project = $stmt->fetch();
                $project_name = $project['nome'] ?? "Progetto non trovato";
            }
            ?>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b pb-6 pt-10 md:pt-0">
                <div class="flex items-center gap-4">
                    <h2 class="text-2xl md:text-3xl font-extrabold text-slate-800" id="projectTitleDisplay"><?php echo htmlspecialchars($project_name); ?></h2>
                    <?php if ($project_id): ?>
                        <div class="flex items-center gap-2">
                             <button onclick="editProject(<?php echo $project_id; ?>, '<?php echo addslashes($project_name); ?>')" class="text-slate-400 hover:text-indigo-600 transition-colors p-1" title="Rinomina Progetto">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                            <button onclick="confirmDeleteProject(<?php echo $project_id; ?>)" class="text-slate-400 hover:text-red-600 transition-colors p-1" title="Elimina Progetto">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="relative w-full md:w-64">
                    <input type="text" id="taskSearch" placeholder="Cerca task..." 
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
                                <svg class="w-4 h-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z"/>
                                </svg>
                            </div>
                            <input type="text" name="scadenza" datepicker datepicker-autohide datepicker-format="dd/mm/yyyy" datepicker-orientation="bottom right"
                                class="bg-white border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full md:w-40 ps-10 p-3" 
                                placeholder="Scadenza">
                        </div>
                    </div>
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-indigo-700 transition w-full sm:w-auto">Aggiungi</button>
                </form>

                <div class="mt-8 space-y-3">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE project_id = ? ORDER BY data_creazione DESC");
                    $stmt->execute([$project_id]);
                    while ($task = $stmt->fetch()): 
                        $isDone = ($task['stato'] === 'completato');
                    ?>
                        <div id="task-<?php echo $task['id']; ?>" 
                            onclick="openEditTask(<?php echo htmlspecialchars(json_encode($task)); ?>)"
                            class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex justify-between items-center transition-all cursor-pointer hover:shadow-md hover:border-indigo-300 <?php echo $isDone ? 'opacity-50' : ''; ?>">
                            
                            <div class="flex items-center gap-3">
                                <input type="checkbox" 
                                    onclick="event.stopPropagation()"
                                    onchange="toggleTask(<?php echo $task['id']; ?>, this.checked)"
                                    <?php echo $isDone ? 'checked' : ''; ?>
                                    class="w-5 h-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                                
                                <span class="text-slate-700 font-medium <?php echo $isDone ? 'line-through' : ''; ?>">
                                    <?php echo htmlspecialchars($task['titolo']); ?>
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <?php if (!empty($task['scadenza'])): 
                                    $scadenza = new DateTime($task['scadenza']);
                                    $oggi = new DateTime('today');
                                    $differenza = $oggi->diff($scadenza);
                                    $isScaduto = $scadenza < $oggi && !$isDone;
                                    $isOggi = $scadenza == $oggi && !$isDone;
                                    
                                    $dateClass = 'text-slate-400';
                                    if ($isScaduto) $dateClass = 'text-red-500 font-bold';
                                    if ($isOggi) $dateClass = 'text-orange-500 font-bold';
                                ?>
                                    <span class="text-xs <?php echo $dateClass; ?> mr-2 flex items-center gap-1" title="Scadenza: <?php echo $scadenza->format('d/m/Y'); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <?php echo $scadenza->format('d M'); ?>
                                    </span>
                                <?php endif; ?>

                                <div id="task-badge-<?php echo $task['id']; ?>" class="text-xs font-bold uppercase px-2 py-1 rounded <?php echo $isDone ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'; ?>">
                                    <?php echo str_replace('_', ' ', $task['stato']); ?>
                                </div>
                                <button onclick="event.stopPropagation(); deleteTask(<?php echo $task['id']; ?>)" class="text-slate-400 hover:text-red-500 p-1 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
    <div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop"></div>
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm relative z-10 transform transition-all scale-95 opacity-0" id="modalContent">
            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2" id="modal-title">Elimina Task</h3>
            <p class="text-slate-500 mb-6 text-sm">Sei sicuro di voler eliminare questo task? Questa azione non può essere annullata.</p>
            <div class="flex flex-col sm:flex-row justify-end gap-3">
                <button onclick="closeModal()" class="w-full sm:w-auto px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium transition-colors border border-slate-300">Annulla</button>
                <button onclick="confirmDelete()" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-bold shadow-lg shadow-red-200 transition-all">Elimina</button>
            </div>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div id="editProjectModal" class="fixed inset-0 z-50 flex items-center justify-center hidden" aria-labelledby="modal-title-edit" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity opacity-0" id="editProjectBackdrop"></div>
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm relative z-10 transform transition-all scale-95 opacity-0" id="editProjectContent">
            <h3 class="text-lg font-bold text-slate-900 mb-4">Rinomina Progetto</h3>
            <input type="text" id="editProjectInput" class="w-full border border-slate-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-6" placeholder="Nome progetto...">
            <div class="flex flex-col sm:flex-row justify-end gap-3">
                <button onclick="closeEditProjectModal()" class="w-full sm:w-auto px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium transition-colors border border-slate-300">Annulla</button>
                <button onclick="saveProjectName()" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-bold shadow-lg shadow-indigo-200 transition-all">Salva</button>
            </div>
        </div>
    </div>

    <!-- Delete Project Modal -->
    <div id="deleteProjectModal" class="fixed inset-0 z-50 flex items-center justify-center hidden" aria-labelledby="modal-title-delete-project" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity opacity-0" id="deleteProjectBackdrop"></div>
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm relative z-10 transform transition-all scale-95 opacity-0" id="deleteProjectContent">
            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Elimina Progetto</h3>
            <p class="text-slate-500 mb-6 text-sm">Sei sicuro di voler eliminare questo progetto e <strong>tutti i task associati</strong>? Questa azione è irreversibile.</p>
            <div class="flex flex-col sm:flex-row justify-end gap-3">
                <button onclick="closeDeleteProjectModal()" class="w-full sm:w-auto px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium transition-colors border border-slate-300">Annulla</button>
                <button onclick="executeDeleteProject()" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-bold shadow-lg shadow-red-200 transition-all">Elimina Tutto</button>
            </div>
        </div>
    </div>

    <!-- Edit Task Details Modal -->
    <div id="editTaskModal" class="fixed inset-0 z-50 flex items-center justify-center hidden" aria-labelledby="modal-title-edit-task" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity opacity-0" id="editTaskBackdrop"></div>
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-lg md:max-w-3xl relative z-10 transform transition-all scale-95 opacity-0" id="editTaskContent">
            <h3 class="text-xl font-bold text-slate-900 mb-4">Modifica Task</h3>
            
            <input type="hidden" id="editTaskId">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Titolo</label>
                <input type="text" id="editTaskTitle" class="w-full border border-slate-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="mb-4 flex gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Scadenza</label>
                    <div class="relative max-w-sm">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z"/>
                            </svg>
                        </div>
                        <input type="text" id="editTaskDate" datepicker datepicker-autohide datepicker-format="dd/mm/yyyy"  datepicker-orientation="bottom left"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full ps-10 p-3" 
                            placeholder="Seleziona data">
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-1">Descrizione</label>
                <textarea id="editTaskDesc" rows="4" class="w-full border border-slate-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Aggiungi dettagli..."></textarea>
            </div>

            <div class="flex flex-col sm:flex-row justify-end gap-3">
                <button onclick="closeEditTaskModal()" class="w-full sm:w-auto px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium transition-colors border border-slate-300">Annulla</button>
                <button onclick="saveTaskChanges()" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-bold shadow-lg shadow-indigo-200 transition-all">Salva Modifiche</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/datepicker.min.js"></script>
    <script>
    
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
        
        editTaskModal.classList.remove('hidden');
        setTimeout(() => {
            editTaskBackdrop.classList.remove('opacity-0');
            editTaskContent.classList.remove('opacity-0', 'scale-95');
            editTaskContent.classList.add('scale-100');
        }, 10);
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

        if (!titolo) return;

        fetch('update_task.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: id,
                titolo: titolo,
                scadenza: scadenza,
                descrizione: descrizione
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
        if (isChecked) {
            taskElement.classList.add('opacity-50');
            textElement.classList.add('line-through');
            
            // Aggiorna Badge
            badgeElement.className = "text-xs font-bold uppercase px-2 py-1 rounded bg-green-100 text-green-700";
            badgeElement.textContent = "completato";
        } else {
            taskElement.classList.remove('opacity-50');
            textElement.classList.remove('line-through');

             // Aggiorna Badge
             badgeElement.className = "text-xs font-bold uppercase px-2 py-1 rounded bg-slate-100 text-slate-500";
             badgeElement.textContent = "da fare";
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
        
        sidebar.classList.toggle('-translate-x-full');
        
        if (sidebar.classList.contains('-translate-x-full')) {
             overlay.classList.add('hidden');
        } else {
             overlay.classList.remove('hidden');
        }
    }
    </script>
</body>
</html>