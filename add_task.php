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