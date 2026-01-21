<?php
require_once 'includes/db.php';

echo "<h1>Flow - Database Setup & Migration</h1>";
echo "<pre>";

try {
    // 1. Core Tables
    echo "Checking core tables...\n";

    // USERS
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Users table checked.\n";

    // PROJECTS
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        titolo VARCHAR(100) NOT NULL,
        descrizione TEXT,
        data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "- Projects table checked.\n";

    // TASKS
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        titolo VARCHAR(255) NOT NULL,
        descrizione TEXT,
        scadenza DATE,
        completato TINYINT(1) DEFAULT 0,
        ordinamento INT DEFAULT 0,
        stato VARCHAR(50) DEFAULT 'da_fare',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");
    echo "- Tasks table checked.\n";

    // 2. Add Missing Columns (Migrations)

    // Users: gemini_api_key
    try {
        $pdo->query("SELECT gemini_api_key FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN gemini_api_key VARCHAR(255) DEFAULT NULL");
        echo "- Added 'gemini_api_key' to users.\n";
    }

    // Projects: user_id (Backfill if needed, but handled by create)
    // Projects: data_modifica
    try {
        $pdo->query("SELECT data_modifica FROM projects LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "- Added 'data_modifica' to projects.\n";
    }

    // Tasks: ordinamento
    try {
        $pdo->query("SELECT ordinamento FROM tasks LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN ordinamento INT DEFAULT 0");
        echo "- Added 'ordinamento' to tasks.\n";
    }

    // Tasks: descrizione
    try {
        $pdo->query("SELECT descrizione FROM tasks LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN descrizione TEXT");
        echo "- Added 'descrizione' to tasks.\n";
    }

    // Tasks: stato (Update from boolean if needed)
    try {
        $pdo->query("SELECT stato FROM tasks LIMIT 1");
        // Ensure it is VARCHAR
        $pdo->exec("ALTER TABLE tasks MODIFY COLUMN stato VARCHAR(50) DEFAULT 'da_fare'");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN stato VARCHAR(50) DEFAULT 'da_fare'");
        echo "- Added 'stato' to tasks.\n";
    }


    // 3. New Tables (Statuses & Collaboration)

    // TASK STATUSES
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_statuses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        nome VARCHAR(50) NOT NULL,
        colore VARCHAR(7) NOT NULL,
        ordine INT DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "- Task Statuses table checked.\n";

    // PROJECT MEMBERS
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        user_id INT NOT NULL,
        role VARCHAR(20) DEFAULT 'member', 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_member (project_id, user_id)
    )");
    echo "- Project Members table checked.\n";

    // TASK ASSIGNMENTS
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_assignment (task_id, user_id)
    )");
    echo "- Task Assignments table checked.\n";

    echo "\n<strong style='color:green'>SUCCESS! Database setup completed.</strong>";
    echo "\nDelete this file (setup_prod.php) from the server after use for security.";

} catch (PDOException $e) {
    echo "\n<strong style='color:red'>ERROR: " . $e->getMessage() . "</strong>";
}
echo "</pre>";
?>