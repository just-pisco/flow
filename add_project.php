<?php
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['nome_progetto'])) {
    $nome = $_POST['nome_progetto'];

    $sql = "INSERT INTO projects (nome) VALUES (:nome)";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute(['nome' => $nome])) {
        header("Location: index.php"); // Torna alla home dopo il salvataggio
        exit();
    }
}
?>