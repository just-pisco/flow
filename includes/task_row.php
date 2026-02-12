<?php
$isDone = ($task['stato'] === 'completato');

// Ensure statusMap and hex2rgba are available (passed from parent or global)
// We assume they are available in scope where this is included.
?>
<div id="task-<?php echo $task['id']; ?>" data-id="<?php echo $task['id']; ?>"
    onclick="openEditTask(<?php echo htmlspecialchars(json_encode($task)); ?>)"
    class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center transition-all cursor-grab active:cursor-grabbing hover:shadow-md hover:border-indigo-300 <?php echo $isDone ? 'opacity-50' : ''; ?>">

    <div class="flex items-center gap-3 overflow-hidden w-full sm:flex-1 min-w-0">
        <!-- Drag Handle -->
        <div class="cursor-grab drag-handle text-slate-300 hover:text-slate-500 flex-shrink-0"
            onclick="event.stopPropagation()">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 8h16M4 16h16" />
            </svg>
        </div>

        <input type="checkbox" onclick="event.stopPropagation()"
            onchange="toggleTask(<?php echo $task['id']; ?>, this.checked)" <?php echo $isDone ? 'checked' : ''; ?>
            class="w-5 h-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer flex-shrink-0">

        <span
            class="task-title text-slate-700 font-medium whitespace-nowrap overflow-x-auto block w-full <?php echo $isDone ? 'line-through' : ''; ?>"
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
