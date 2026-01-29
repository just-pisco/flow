<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$currentUserId = $_SESSION['user_id'];

// Check Permissions
$stmt = $pdo->prepare("SELECT global_role FROM users WHERE id = ?");
$stmt->execute([$currentUserId]);
$userGlobalRole = $stmt->fetchColumn();

// Check Team Admin Role
$stmt = $pdo->prepare("SELECT 1 FROM team_members WHERE user_id = ? AND role = 'admin'");
$stmt->execute([$currentUserId]);
$isTeamAdmin = $stmt->fetchColumn();
$isAnyTeamAdmin = $isTeamAdmin; // For sidebar
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow - Calendario Task</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <!-- FullCalendar v6 -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

    <script src="js/shared.js"></script>
    <style>
        /* Custom FullCalendar Styles */
        .fc-event {
            cursor: pointer;
            border: none;
            padding: 2px 4px;
            font-size: 0.85em;
            border-radius: 4px;
            transition: transform 0.1s, box-shadow 0.1s;
        }

        .fc-event:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            z-index: 10;
        }

        .fc-day-today {
            background-color: #f8fafc !important;
        }

        .fc-toolbar-title {
            font-size: 1.5rem !important;
            font-weight: 800 !important;
            color: #1e293b;
        }

        /* Desktop & Global Button Spacing */
        .fc-button-group {
            display: flex !important;
            gap: 0.2rem !important;
            /* Margine 0.2rem globale */
        }

        .fc-button {
            background-color: white !important;
            border: 1px solid #e2e8f0 !important;
            color: #4f46e5 !important;
            font-weight: 600 !important;
            text-transform: capitalize;
            padding: 0.5rem 1rem !important;
            font-size: 0.875rem !important;
            border-radius: 0.5rem !important;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            margin: 0 !important;
        }

        .fc-button:hover {
            background-color: #eef2ff !important;
            border-color: #c7d2fe !important;
            color: #4338ca !important;
        }

        .fc-button-active {
            background-color: #4f46e5 !important;
            border-color: #4f46e5 !important;
            color: white !important;
        }

        /* Mobile Optimizations */
        @media (max-width: 640px) {
            .fc-header-toolbar {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 1rem !important;
                margin-bottom: 1.5rem !important;
                justify-content: space-between !important;
            }

            .fc-toolbar-chunk:nth-child(2) {
                width: 100%;
                order: -1;
                text-align: center;
                display: flex;
                justify-content: center;
            }

            .fc-toolbar-title {
                font-size: 1.25rem !important;
            }

            /* Reduced gap for Mobile */
            .fc-button-group {
                gap: 0.2rem !important;
                /* Margine 0.2rem anche mobile */
            }

            .fc-button {
                padding: 0.4rem 0.8rem !important;
                font-size: 0.8rem !important;
                flex: 1;
            }

            .fc-button-group>.fc-button {
                border-radius: 0.5rem !important;
            }
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 font-sans">

    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>

        <main class="flex-1 p-4 md:p-8 overflow-y-auto relative flex flex-col h-screen">
            <button onclick="toggleSidebar()" id="desktopHamburger"
                class="absolute top-4 left-4 z-30 p-2 text-slate-600 focus:outline-none transition-transform hover:scale-110 md:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <?php include 'includes/header_profile_widget.php'; ?>

            <div class="max-w-6xl mx-auto w-full mt-12 md:mt-2 flex flex-col flex-1">
                <header class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Calendario Task</h1>
                        <p class="text-slate-500 text-sm">Visualizza le tue scadenze mensili.</p>
                    </div>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 w-full sm:w-auto">
                        <!-- Google Sync Controls -->
                        <div class="flex items-center gap-2" id="googleControls">
                            <button onclick="handleAuthClick()" id="authorize_button"
                                class="bg-white border border-slate-300 text-slate-700 px-3 py-2 rounded-lg text-sm font-bold hover:bg-slate-50 flex items-center gap-2 shadow-sm transition-all whitespace-nowrap">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 48 48">
                                    <path fill="#EA4335"
                                        d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z">
                                    </path>
                                    <path fill="#4285F4"
                                        d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z">
                                    </path>
                                    <path fill="#FBBC05"
                                        d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z">
                                    </path>
                                    <path fill="#34A853"
                                        d="M24 48c6.48 0 11.95-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z">
                                    </path>
                                </svg>
                                <span>Collega Google Calendar</span>
                            </button>
                            <button onclick="handleSignoutClick()" id="signout_button"
                                class="hidden text-slate-500 hover:text-red-500 text-sm font-medium px-2 underline decoration-slate-300 underline-offset-4">Scollega</button>
                        </div>

                        <div
                            class="flex items-center gap-3 bg-white p-2 rounded-lg border border-slate-200 shadow-sm w-full sm:w-auto mt-2 sm:mt-0">
                            <label class="inline-flex items-center cursor-pointer select-none">
                                <input type="checkbox" id="viewToggle" class="sr-only peer">
                                <div
                                    class="relative w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600">
                                </div>
                                <span class="ms-3 text-sm font-medium text-slate-700 whitespace-nowrap"
                                    id="viewLabel">Solo i miei task</span>
                            </label>
                        </div>
                    </div>
                </header>

                <div
                    class="bg-white p-2 md:p-6 rounded-xl shadow-sm border border-slate-200 flex-1 overflow-hidden flex flex-col">
                    <div id='calendar' class="flex-1"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Google Settings Modal -->
    <div id="googleSettingsModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <!-- Modal content removed/unused -->
    </div>

    <script>
        window.GOOGLE_CONFIG = {
            clientId: "<?php echo getenv('GOOGLE_CLIENT_ID'); ?>",
            apiKey: "<?php echo getenv('GOOGLE_API_KEY'); ?>"
        };
    </script>
    <script src="js/google_config.js"></script>
    <script src="js/google_calendar.js"></script>
    <script src="js/google_drive.js"></script>
    <script async defer src="https://apis.google.com/js/api.js" onload="gapiLoaded()"></script>
    <script async defer src="https://accounts.google.com/gsi/client" onload="gisLoaded()"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var calendarEl = document.getElementById('calendar');
            var toggle = document.getElementById('viewToggle');
            var viewLabel = document.getElementById('viewLabel');

            // Check LocalStorage preference
            var savedView = localStorage.getItem('calendarView') || 'mine';
            if (savedView === 'all') {
                toggle.checked = true;
                viewLabel.textContent = "Tutti i task dei progetti";
            }

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'it', // Italian
                headerToolbar: getHeaderToolbar(), // Responsive toolbar
                events: 'api_calendar.php?action=get_events&view=' + savedView,
                eventClick: function (info) {
                    if (info.event.url) {
                        // Let default behavior happen
                    }
                },
                buttonText: {
                    today: 'Oggi',
                    month: 'Mese',
                    week: 'Sett.',
                    day: 'Giorno',
                    list: 'Lista'
                },
                height: '100%',
                contentHeight: 'auto',
                windowResize: function (view) {
                    calendar.setOption('headerToolbar', getHeaderToolbar());
                }
            });
            calendar.render();

            function getHeaderToolbar() {
                if (window.innerWidth < 640) {
                    // Mobile Layout (Compact)
                    return {
                        left: 'prev,next',
                        center: 'title', // Included title! CSS will move it to top.
                        right: 'dayGridMonth,timeGridWeek,listWeek'
                    };
                } else {
                    // Desktop Layout
                    return {
                        left: 'prev,next', // Removed 'today'
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,listWeek'
                    };
                }
            }

            // Toggle Logic
            toggle.addEventListener('change', function () {
                var view = this.checked ? 'all' : 'mine';
                viewLabel.textContent = this.checked ? "Tutti i task dei progetti" : "Solo i miei task";

                // Remove old source and add new
                calendar.removeAllEventSources();
                calendar.addEventSource('api_calendar.php?action=get_events&view=' + view);

                localStorage.setItem('calendarView', view);
            });

            // Expose calendar to window for google sync script if needed
            window.fullCalendarInstance = calendar;
        });
    </script>
</body>

</html>