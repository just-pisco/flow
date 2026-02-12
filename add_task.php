<?php
require_once 'includes/db.php';
require_once 'includes/GoogleCalendarHelper.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['titolo']) && !empty($_POST['project_id'])) {
    $titolo = $_POST['titolo'];
    $project_id = $_POST['project_id'];
    $scadenza = !empty($_POST['scadenza']) ? $_POST['scadenza'] : null;

    // Convert dd/mm/yyyy to yyyy-mm-dd if necessary
    if ($scadenza) {
        $dateObj = DateTime::createFromFormat('d/m/Y', $scadenza);
        if ($dateObj) {
            $scadenza = $dateObj->format('Y-m-d');
        }
    }

    $sql = "INSERT INTO tasks (titolo, project_id, scadenza, stato) VALUES (:titolo, :project_id, :scadenza, 'da_fare')";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute(['titolo' => $titolo, 'project_id' => $project_id, 'scadenza' => $scadenza])) {
        $taskId = $pdo->lastInsertId();

        // Sync to Google Calendar (Creator)
        if (isset($_SESSION['user_id'])) {
            try {
                $helper = new GoogleCalendarHelper($pdo);
                $helper->syncTask($_SESSION['user_id'], $taskId);
            } catch (Exception $e) {
                // Ignore sync errors to not block flow
                error_log("Google Sync Error (Add): " . $e->getMessage());
            }
        }

        // Aggiorna data_modifica del progetto
        $upd = $pdo->prepare("UPDATE projects SET data_modifica = NOW() WHERE id = ?");
        $upd->execute([$project_id]);

        // Check for AJAX/JSON request (Headers or explicit param)
        $isAjax = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) 
                  || (!empty($_POST['ajax']) && $_POST['ajax'] === '1');

        if ($isAjax) {
            // Prepare data for partial
            $task = [
                'id' => $taskId,
                'titolo' => $titolo,
                'scadenza' => $scadenza,
                'stato' => 'da_fare',
                'project_id' => $project_id
            ];

            // Helpers needed for task_row.php
            $statusMap = [];
            if (isset($_SESSION['user_id'])) {
                $stmtStatus = $pdo->prepare("SELECT * FROM task_statuses WHERE user_id = ?");
                $stmtStatus->execute([$_SESSION['user_id']]);
                $statuses = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);
                foreach ($statuses as $s) {
                    $statusMap[$s['nome']] = $s['colore'];
                }
            }

            if (!function_exists('hex2rgba')) {
                function hex2rgba($color, $opacity = false) {
                    $default = 'rgb(0,0,0)';
                    if (empty($color)) return $default;
                    if ($color[0] == '#') $color = substr($color, 1);
                    if (strlen($color) == 6) {
                        $hex = array($color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]);
                    } elseif (strlen($color) == 3) {
                        $hex = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
                    } else {
                        return $default;
                    }
                    $rgb = array_map('hexdec', $hex);
                    if ($opacity) {
                        if (abs($opacity) > 1) $opacity = 1.0;
                        $output = 'rgba(' . implode(",", $rgb) . ',' . $opacity . ')';
                    } else {
                        $output = 'rgb(' . implode(",", $rgb) . ')';
                    }
                    return $output;
                }
            }

            // Render Partial
            ob_start();
            include 'includes/task_row.php';
            $taskHtml = ob_get_clean();

            echo json_encode(['success' => true, 'html' => $taskHtml]);
            exit();
        }

        header("Location: index.php?project_id=" . $project_id);
        exit();
    }
} else {
    // Gestione errore input vuoti - reindirizza al progetto se presente, o home
    $redirect = "index.php";
    if (!empty($_POST['project_id'])) {
        $redirect .= "?project_id=" . $_POST['project_id'];
    }
    header("Location: " . $redirect);
    exit();
}