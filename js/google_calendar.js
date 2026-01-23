// Google Calendar Integration

let tokenClient;
let gapiInited = false;
let gisInited = false;

const DISCOVERY_DOC = 'https://www.googleapis.com/discovery/v1/apis/calendar/v3/rest';
const SCOPES = 'https://www.googleapis.com/auth/calendar.events';

function gapiLoaded() {
    gapi.load('client', initializeGapiClient);
}

async function initializeGapiClient() {
    const API_KEY = window.GOOGLE_CONFIG.apiKey;

    await gapi.client.init({
        apiKey: API_KEY,
        discoveryDocs: [DISCOVERY_DOC],
    });
    gapiInited = true;
    maybeEnableButtons();
}

function gisLoaded() {
    // Show button anyway so user knows feature exists
    document.getElementById('authorize_button').classList.remove('hidden');

    const CLIENT_ID = window.GOOGLE_CONFIG.clientId;
    if (!CLIENT_ID || CLIENT_ID.includes('YOUR_CLIENT_ID')) {
        // Valid config check delayed to click time
        return;
    }

    tokenClient = google.accounts.oauth2.initTokenClient({
        client_id: CLIENT_ID,
        scope: SCOPES,
        callback: '',
    });
    gisInited = true;
}

function maybeEnableButtons() {
    if (gapiInited && gisInited) {
        document.getElementById('authorize_button').classList.remove('hidden');
    }
}

function handleAuthClick() {
    const CLIENT_ID = window.GOOGLE_CONFIG.clientId;
    if (!CLIENT_ID || CLIENT_ID.includes('YOUR_CLIENT_ID')) {
        alert("Devi configurare il tuo GOOGLE_CLIENT_ID nel file js/google_config.js prima di procedere!");
        return;
    }

    if (!tokenClient) {
        // Retry init if it failed/skipped before (though gisLoaded runs once)
        // If here, likely GIS loaded but skipped init. 
        // Re-init:
        tokenClient = google.accounts.oauth2.initTokenClient({
            client_id: CLIENT_ID,
            scope: SCOPES,
            callback: '',
        });
    }

    tokenClient.callback = async (resp) => {
        if (resp.error !== undefined) {
            throw (resp);
        }
        document.getElementById('signout_button').classList.remove('hidden');
        document.getElementById('authorize_button').innerText = '🔄 Syncing...';
        await syncToGoogle();
        document.getElementById('authorize_button').innerText = '✅ Synced';
        setTimeout(() => {
            document.getElementById('authorize_button').innerText = 'Sync Google';
        }, 3000);
    };

    if (gapi.client.getToken() === null) {
        // Prompt the user to select a Google Account and ask for consent to share their data
        tokenClient.requestAccessToken({ prompt: 'consent' });
    } else {
        // Skip display of account chooser and consent dialog for an existing session.
        tokenClient.requestAccessToken({ prompt: '' });
    }
}

function handleSignoutClick() {
    const token = gapi.client.getToken();
    if (token !== null) {
        google.accounts.oauth2.revoke(token.access_token);
        gapi.client.setToken('');
        document.getElementById('signout_button').classList.add('hidden');
        document.getElementById('authorize_button').classList.add('hidden'); // Until reloaded? Or just reset state
        showToast("Disconnesso da Google.", "success");
    }
}

async function syncToGoogle() {
    try {
        // 1. Fetch Flow Tasks (My Tasks only usually)
        const savedView = localStorage.getItem('calendarView') || 'mine';
        const res = await fetch('api_calendar.php?action=get_events&view=' + savedView);
        const flowEvents = await res.json();

        if (!flowEvents || flowEvents.length === 0) {
            showToast("Nessun task da sincronizzare.", "info");
            return;
        }

        // 2. Fetch Google Events (Future)
        const now = new Date();
        const request = {
            'calendarId': 'primary',
            'timeMin': now.toISOString(),
            'showDeleted': false,
            'singleEvents': true,
            'maxResults': 100,
            'orderBy': 'startTime'
        };
        const response = await gapi.client.calendar.events.list(request);
        const googleEvents = response.result.items;

        let addedCount = 0;

        // 3. Compare and Insert
        for (const task of flowEvents) {
            // Task: { id, title, start (YYYY-MM-DD), ... }
            // Filter only future tasks or today
            const taskDate = new Date(task.start);
            if (taskDate < new Date().setHours(0, 0, 0, 0)) continue; // Skip past

            // Check if exists (Naive check: Title contains "[Flow] TaskTitle" and Date matches)
            const targetTitle = `[Flow] ${task.title}`;

            const exists = googleEvents.some(gEvent => {
                // gEvent.start.date (YYYY-MM-DD) or gEvent.start.dateTime
                const gDate = gEvent.start.date || (gEvent.start.dateTime ? gEvent.start.dateTime.split('T')[0] : '');
                return gEvent.summary === targetTitle && gDate === task.start;
            });

            if (!exists) {
                // Insert
                const event = {
                    'summary': targetTitle,
                    'description': `Task Flow ID: ${task.id}\nLink: ${window.location.origin}/index.php?project_id=...`,
                    'start': {
                        'date': task.start, // All day
                    },
                    'end': {
                        'date': task.start, // Google requires end date. For all day, usually end is start+1 day? 
                        // Actually for single day all-day event, end is next day.
                    }
                };

                // Fix End Date for All Day
                const endDate = new Date(task.start);
                endDate.setDate(endDate.getDate() + 1);
                event.end.date = endDate.toISOString().split('T')[0];

                await gapi.client.calendar.events.insert({
                    'calendarId': 'primary',
                    'resource': event
                });
                addedCount++;
            }
        }

        if (addedCount > 0) {
            showToast(`Sincronizzati ${addedCount} nuovi task!`, "success");
        } else {
            showToast("Tutti i task sono già sincronizzati.", "info");
        }

    } catch (err) {
        console.error(err);
        showToast("Errore Sync: " + err.message, "error");
    }
}
