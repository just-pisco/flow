
// State Variables
let currentDeleteTaskId = null;
let currentEditProjectId = null;
let currentDeleteProjectId = null;

// Modal Selectors
const modal = document.getElementById('deleteModal');
const backdrop = document.getElementById('modalBackdrop');
const content = document.getElementById('modalContent');

const editProjectModal = document.getElementById('editProjectModal');
const editProjectBackdrop = document.getElementById('editProjectBackdrop');
const editProjectContent = document.getElementById('editProjectContent');
const editProjectInput = document.getElementById('editProjectInput');

const deleteProjectModal = document.getElementById('deleteProjectModal');
const deleteProjectBackdrop = document.getElementById('deleteProjectBackdrop');
const deleteProjectContent = document.getElementById('deleteProjectContent');

const editTaskModal = document.getElementById('editTaskModal');
const editTaskBackdrop = document.getElementById('editTaskBackdrop');
const editTaskContent = document.getElementById('editTaskContent');
const editTaskId = document.getElementById('editTaskId');
const editTaskTitle = document.getElementById('editTaskTitle');
const editTaskDate = document.getElementById('editTaskDate');
const editTaskDesc = document.getElementById('editTaskDesc');

// --- TASKS ---

function openEditTask(task) {
    editTaskId.value = task.id;
    editTaskTitle.value = task.titolo;

    // Convert YYYY-MM-DD to DD/MM/YYYY for display if needed
    if (task.scadenza) {
        // Native date input expects YYYY-MM-DD, so keep it if input type='date'
        // If text, convert. Assuming input type='date' for modern browsers
        editTaskDate.value = task.scadenza;
    } else {
        editTaskDate.value = '';
    }

    editTaskDesc.value = task.descrizione || '';

    // Populate Status Dropdown
    populateEditTaskStatus(task.stato);

    editTaskModal.classList.remove('hidden');

    // Load Assignees
    const assigneesList = document.getElementById('editTaskAssigneesList');
    if (assigneesList) {
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
    }

    setTimeout(() => {
        editTaskBackdrop.classList.remove('opacity-0');
        editTaskContent.classList.remove('opacity-0', 'scale-95');
        editTaskContent.classList.add('scale-100');
    }, 10);
}

function populateEditTaskStatus(currentStatus) {
    const select = document.getElementById('editTaskStatus');
    if (!select) return;
    select.innerHTML = '';

    // Use USER_STATUSES defined in index.php
    if (typeof USER_STATUSES === 'undefined' || USER_STATUSES.length === 0) {
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

    // Collect Assignees
    const assignees = [];
    const checkboxes = document.querySelectorAll('#editTaskAssigneesList input[type="checkbox"]:checked');
    checkboxes.forEach(cb => {
        assignees.push(cb.value);
    });

    fetch('update_task.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: id,
            titolo: titolo,
            scadenza: scadenza,
            descrizione: descrizione,
            stato: status,
            assignees: assignees // Send assignees array
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert("Errore aggiornamento task: " + (data.error || ''));
            }
        });
}

function toggleTask(taskId, isChecked) {
    const taskElement = document.getElementById(`task-${taskId}`);
    const textElement = taskElement.querySelector('span'); // Might target wrong span if not specific
    const badgeElement = document.getElementById(`task-badge-${taskId}`);

    const newStatus = isChecked ? 'completato' : 'da_fare';

    let color = isChecked ? '#16a34a' : '#64748b';
    if (typeof USER_STATUSES !== 'undefined') {
        const s = USER_STATUSES.find(st => st.nome === newStatus);
        if (s) color = s.colore;
    }

    if (badgeElement) {
        badgeElement.textContent = newStatus.replace(/_/g, ' ');
        badgeElement.style.backgroundColor = hex2rgbaJS(color, 0.1);
        badgeElement.style.color = color;
    }

    if (isChecked) {
        taskElement.classList.add('opacity-50');
        if (textElement) textElement.classList.add('line-through');
    } else {
        taskElement.classList.remove('opacity-50');
        if (textElement) textElement.classList.remove('line-through');
    }

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
                location.reload();
            }
        });
}

function openStatusMenu(event, taskId) {
    event.stopPropagation();
    document.querySelectorAll('.status-menu-popover').forEach(el => el.remove());

    if (typeof USER_STATUSES === 'undefined' || USER_STATUSES.length === 0) return;

    const menu = document.createElement('div');
    menu.className = "status-menu-popover absolute z-50 bg-white rounded-lg shadow-xl border border-slate-200 p-2 min-w-[150px] flex flex-col gap-1";

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

    document.addEventListener('click', function closeMenu(e) {
        if (!menu.contains(e.target)) {
            menu.remove();
            document.removeEventListener('click', closeMenu);
        }
    });

    document.body.appendChild(menu);
}

function changeStatusQuick(taskId, newStatus, newColor) {
    const badge = document.getElementById(`task-badge-${taskId}`);
    const taskElement = document.getElementById(`task-${taskId}`);
    const textElement = taskElement.querySelector('.task-title'); // Better selector
    const checkbox = taskElement.querySelector('input[type="checkbox"]');

    if (badge) {
        badge.textContent = newStatus.replace(/_/g, ' ');
        badge.style.backgroundColor = hex2rgbaJS(newColor, 0.1);
        badge.style.color = newColor;
    }

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

function searchTasks() {
    const input = document.getElementById('taskSearch');
    const filter = input.value.toLowerCase();
    const tasks = document.querySelectorAll('[id^="task-"]');

    tasks.forEach(task => {
        if (!task.classList.contains('bg-white')) return; // Simple check for task row
        const textElement = task.querySelector('.task-title');
        const text = textElement ? textElement.textContent.toLowerCase() : '';
        task.style.display = text.includes(filter) ? '' : 'none';
    });
}

function deleteTask(taskId) {
    currentDeleteTaskId = taskId;
    modal.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0');
        content.classList.remove('opacity-0', 'scale-95');
        content.classList.add('scale-100');
    }, 10);
}

function closeModal() {
    currentDeleteTaskId = null;
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
                if (taskEl) {
                    taskEl.style.transform = 'translateX(20px)';
                    taskEl.style.opacity = '0';
                    setTimeout(() => taskEl.remove(), 300);
                }
            }
            closeModal();
        });
}

// --- PROJECTS ---

function editProject(id, currentName) {
    currentEditProjectId = id;
    if (editProjectInput) editProjectInput.value = currentName;
    editProjectModal.classList.remove('hidden');
    setTimeout(() => {
        editProjectBackdrop.classList.remove('opacity-0');
        editProjectContent.classList.remove('opacity-0', 'scale-95');
        editProjectContent.classList.add('scale-100');
        if (editProjectInput) editProjectInput.focus();
    }, 10);
}

function closeEditProjectModal() {
    currentEditProjectId = null;
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
                if (data.success) location.reload();
                else alert("Errore: " + (data.error || "Impossibile aggiornare"));
                closeEditProjectModal();
            });
    }
}

function confirmDeleteProject(id) {
    currentDeleteProjectId = id;
    deleteProjectModal.classList.remove('hidden');
    setTimeout(() => {
        deleteProjectBackdrop.classList.remove('opacity-0');
        deleteProjectContent.classList.remove('opacity-0', 'scale-95');
        deleteProjectContent.classList.add('scale-100');
    }, 10);
}

function closeDeleteProjectModal() {
    currentDeleteProjectId = null;
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
            if (data.success) window.location.href = "index.php";
            else alert("Errore: " + (data.error || "Impossibile eliminare"));
            closeDeleteProjectModal();
        });
}

// --- SETTINGS (GEMINI & STATUSES) ---

const settingsModal = document.getElementById('settingsModal');
const commitModal = document.getElementById('commitModal');
const geminiKeyInput = document.getElementById('geminiKeyInput');
const commitTaskList = document.getElementById('commitTaskList');
const generatedCommitMsg = document.getElementById('generatedCommitMsg');
const btnGenerate = document.getElementById('btnGenerate');

function openSettingsModal() {
    if (geminiKeyInput) geminiKeyInput.value = '';
    settingsModal.classList.remove('hidden');
    renderStatusSettings();
}

function renderStatusSettings() {
    const listContainer = document.getElementById('statusListSettings');
    if (!listContainer) return;
    if (typeof USER_STATUSES === 'undefined' || USER_STATUSES.length === 0) {
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

    if (field === 'nome') {
        s.nome = value.trim().toLowerCase().replace(/\s+/g, '_');
    } else if (field === 'colore') {
        s.colore = value;
    }

    // statusHasChanged is global
    if (typeof statusHasChanged !== 'undefined') statusHasChanged = true;

    fetch('api_statuses.php?action=update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: s.id,
            nome: s.nome,
            colore: s.colore
        })
    }).then(res => res.json()).then(data => {
        if (!data.success) showToast('Errore aggiornamento: ' + data.error, 'error');
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
                USER_STATUSES.push({
                    id: data.id,
                    nome: name,
                    colore: color,
                    ordine: 999
                });
                renderStatusSettings();
                statusHasChanged = true;
                nameInput.value = '';
            } else {
                showToast('Errore: ' + data.error, 'error');
            }
        });
}

// Status Delete Modal
let currentDeleteStatusId = null;
const deleteStatusModal = document.getElementById('deleteStatusModal');
const deleteStatusBackdrop = document.getElementById('deleteStatusBackdrop');
const deleteStatusContent = document.getElementById('deleteStatusContent');

function deleteStatus(id) {
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

// --- GEMINI COMMIT ---

function openCommitModal() {
    if (typeof HAS_API_KEY === 'undefined' || !HAS_API_KEY) {
        openSettingsModal();
        return;
    }

    const tasks = [];
    document.querySelectorAll('[id^="task-"]').forEach(el => {
        const id = el.getAttribute('id').replace('task-', '');
        const titleEl = el.querySelector('.task-title');
        if (!titleEl) return;
        const isDone = titleEl.classList.contains('line-through');
        const text = titleEl.innerText.trim();

        const onclickStr = el.getAttribute('onclick');
        let description = '';
        if (onclickStr) {
            try {
                const match = onclickStr.match(/openEditTask\((.*)\)/);
                if (match && match[1]) {
                    const taskData = JSON.parse(match[1]);
                    description = taskData.descrizione || '';
                }
            } catch (e) { }
        }

        if (isDone) tasks.push({ id, text, description });
    });

    if (tasks.length === 0) {
        showToast("Nessun task completato trovato nella pagina.", "error");
        return;
    }

    if (commitTaskList) {
        commitTaskList.innerHTML = tasks.map(t => `
        <label class="flex items-center gap-3 p-2 hover:bg-white rounded cursor-pointer border border-transparent hover:border-indigo-100">
            <input type="checkbox" value="${t.text}" data-description="${t.description.replace(/"/g, '&quot;')}" checked class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500">
            <span class="text-sm text-slate-700 truncate">${t.text}</span>
        </label>
    `).join('');
    }

    if (generatedCommitMsg) generatedCommitMsg.value = '';
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

        const priorities = [
            'models/gemini-1.5-flash',
            'models/gemini-1.5-flash-latest',
            'models/gemini-1.5-pro',
            'models/gemini-1.5-pro-latest',
            'models/gemini-1.0-pro',
            'models/gemini-pro'
        ];

        for (const preferred of priorities) {
            if (data.models.find(m => m.name === preferred && m.supportedGenerationMethods.includes('generateContent'))) {
                return preferred.replace('models/', '');
            }
        }
        const fallback = data.models.find(m => m.name.includes('gemini') && m.supportedGenerationMethods.includes('generateContent'));
        if (fallback) return fallback.name.replace('models/', '');
        throw new Error("Nessun modello Gemini compatibile trovato.");
    } catch (e) {
        return 'gemini-1.5-flash';
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

    if (typeof USER_API_KEY === 'undefined') {
        showToast('Chiave API mancante', 'error');
        return;
    }
    const apiKey = USER_API_KEY;

    let taskListStr = selectedTasks.map(t => {
        return `- Title: ${t.title}\n  Description: ${t.description}`;
    }).join('\n');

    const prompt = `Act as a senior developer. Generate a single comprehensive Conventional Commit message (type(scope): description) IN ITALIAN for these completed tasks: \n${taskListStr}\n\nOnly output the git commit command like: git commit -m "..."`;

    btnGenerate.disabled = true;
    btnGenerate.innerHTML = "Ricerca Modello...";
    generatedCommitMsg.value = "Controllo modelli disponibili...";

    try {
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
        if (data.error) throw new Error(data.error.message);

        const result = data.candidates[0].content.parts[0].text;
        generatedCommitMsg.value = result.trim();

    } catch (error) {
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

// Init
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('taskList');
    if (el && typeof Sortable !== 'undefined') {
        Sortable.create(el, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: function (evt) {
                const tasks = document.querySelectorAll('#taskList > div');
                const order = Array.from(tasks).map(task => task.getAttribute('data-id'));
                fetch('reorder_tasks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order: order })
                });
            }
        });
    }
});
