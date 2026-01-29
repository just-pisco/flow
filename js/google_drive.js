/**
 * Google Drive Integration
 * Gestisce il Google Picker per selezionare file o caricarne di nuovi.
 * Richiede permessi incrementali se non presenti.
 */

let pickerInited = false;
const DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive.file';
const CALENDAR_SCOPE = 'https://www.googleapis.com/auth/calendar.app.created';

// State globali settati dai modali (logic.js)
// window.currentTaskId
// window.currentProjectId

// Funzione principale per Caricare File (sostituisce Picker)
async function openDrivePicker() {
    // 1. Auth Check (Same as before)
    if (!gapiInited || !gisInited) {
        showToast("Google API non ancora pronte. Riprova tra un secondo.", "warning");
        return;
    }

    let tokenObj = gapi.client.getToken();
    if (!tokenObj) {
        // Maybe still loading or expired? Try to fetch from backend
        // Assuming checkAuthStatus is available globally from google_calendar.js
        if (typeof checkAuthStatus === 'function') {
            await checkAuthStatus();
            tokenObj = gapi.client.getToken();
        }
    }

    if (!tokenObj) {
        showDriveAuthModal();
        return;
    }

    const hasDrive = google.accounts.oauth2.hasGrantedAllScopes(tokenObj, DRIVE_SCOPE);

    if (!hasDrive) {
        showDriveAuthModal();
        return;
    }

    // 2. Trigger File Selection
    const input = document.createElement('input');
    input.type = 'file';
    input.style.display = 'none';
    input.onchange = (e) => {
        if (e.target.files.length > 0) {
            handleFileUpload(e.target.files[0]);
        }
    };
    document.body.appendChild(input);
    input.click();
    // Cleanup handled by browser garbage collection eventually or remove logic
    setTimeout(() => input.remove(), 60000);
}

// UI: Drive Auth Modal
function showDriveAuthModal() {
    const modal = document.getElementById('driveAuthModal');
    if (modal) {
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.querySelector('div.bg-white').classList.remove('scale-95', 'opacity-0');
            modal.querySelector('div.bg-white').classList.add('scale-100', 'opacity-100');
        }, 10);
    }
}

function closeDriveAuthModal() {
    const modal = document.getElementById('driveAuthModal');
    if (modal) {
        modal.querySelector('div.bg-white').classList.remove('scale-100', 'opacity-100');
        modal.querySelector('div.bg-white').classList.add('scale-95', 'opacity-0');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }
}

function driveAuthUpgrade() {
    closeDriveAuthModal(); // Close modal first
    const CLIENT_ID = window.GOOGLE_CONFIG.clientId;
    const client = google.accounts.oauth2.initCodeClient({
        client_id: CLIENT_ID,
        scope: `${CALENDAR_SCOPE} ${DRIVE_SCOPE}`,
        include_granted_scopes: true,
        prompt: 'consent', // Force Refresh Token generation
        ux_mode: 'popup',
        callback: async (response) => {
            if (response.code) {
                const result = await exchangeCodeForToken(response.code);
                if (result && result.success) {
                    showToast("Permessi aggiornati! Clicca di nuovo su 'Carica File'.", "success");
                }
            }
        },
    });
    client.requestCode();
}

// Picker logic removed. Renamed to upload logic.

async function handleFileUpload(file) {
    if ((!window.currentTaskId && !window.currentProjectId)) {
        showToast("Errore contesto (Task/Project missing)", "error");
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    if (window.currentTaskId) formData.append('task_id', window.currentTaskId);
    if (window.currentProjectId) formData.append('project_id', window.currentProjectId);

    showToast(`Caricamento "${file.name}" su Drive in corso...`, "info");

    try {
        const res = await fetch('api_drive.php?action=upload_file', {
            method: 'POST',
            body: formData // No Content-Type header (browser sets boundary)
        });

        const data = await res.json();

        if (data.success) {
            showToast("File caricato su Drive (Cartella 'Flow')!", "success");

            // Refresh
            if (window.currentTaskId && typeof loadTaskAttachments === 'function') {
                loadTaskAttachments(window.currentTaskId);
            } else if (window.currentProjectId && typeof loadProjectAttachments === 'function') {
                loadProjectAttachments(window.currentProjectId);
            }

        } else {
            showToast("Errore Upload: " + (data.error || 'Uknown'), "error");
        }

    } catch (e) {
        console.error(e);
        showToast("Errore di rete durante upload", "error");
    }
}


// Project Attachment Loader (Task Loader is in index_logic.js, maybe move here? No leave there for now)
function loadProjectAttachments(projectId) {
    const list = document.getElementById('attachmentsList'); // In project_details_modal.php
    if (!list) return; // Not open or not found

    list.innerHTML = '<p class="text-sm text-slate-400 italic">Caricamento...</p>';

    fetch(`api_drive.php?action=get_attachments&project_id=${projectId}`)
        .then(res => res.json())
        .then(data => {
            list.innerHTML = '';
            if (data.success && data.files && data.files.length > 0) {
                data.files.forEach(file => {
                    const div = document.createElement('div');
                    div.className = "flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-lg hover:shadow-sm transition-all";
                    div.innerHTML = `
                        <div class="p-2 bg-slate-50 rounded flex-shrink-0">
                            <img src="${file.icon_url || 'https://ssl.gstatic.com/docs/doclist/images/icon_10_generic_list.png'}" class="w-6 h-6" alt="icon">
                        </div>
                        <div class="flex-1 min-w-0">
                            <a href="${file.file_url}" target="_blank" class="block text-sm font-medium text-slate-800 truncate hover:text-indigo-600 hover:underline" title="${file.file_name}">${file.file_name}</a>
                            <span class="text-xs text-slate-500">Google Drive • ${new Date(file.created_at).toLocaleDateString()}</span>
                        </div>
                    `;
                    list.appendChild(div);
                });
            } else {
                list.innerHTML = '<p class="text-sm text-slate-400 italic">Nessun allegato.</p>';
            }
        })
        .catch(err => {
            console.error(err);
            list.innerHTML = '<p class="text-sm text-red-400">Errore caricamento.</p>';
        });
}
