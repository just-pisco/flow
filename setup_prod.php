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
        email VARCHAR(100) NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Users table checked.\n";

    // MIGRATION: Ensure created_at exists (it might be missing if table created long ago)
    // REVERTED: User has data_creazione column.
    /*
    try {
        $pdo->query("SELECT created_at FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "- Added 'created_at' to users.\n";
    }
    */

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

    // Users: email (Migration if missing)
    try {
        $pdo->query("SELECT email FROM users LIMIT 1");
        // Check if we need to modify it to be nullable (simple brute force modify)
        $pdo->exec("ALTER TABLE users MODIFY COLUMN email VARCHAR(100) NULL UNIQUE");
        echo "- Checked/Modified 'email' in users to be NULL UNIQUE.\n";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(100) NULL UNIQUE");
        echo "- Added 'email' to users.\n";
    }

    // Users: gemini_api_key
    try {
        $pdo->query("SELECT gemini_api_key FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN gemini_api_key VARCHAR(255) DEFAULT NULL");
        echo "- Added 'gemini_api_key' to users.\n";
    }

    // Users: global_role
    try {
        $pdo->query("SELECT global_role FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN global_role ENUM('superadmin', 'user') DEFAULT 'user'");
        echo "- Added 'global_role' to users.\n";
    }

    // Users: nome and cognome
    try {
        $pdo->query("SELECT nome FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN nome VARCHAR(50) DEFAULT NULL");
        echo "- Added 'nome' to users.\n";
    }

    try {
        $pdo->query("SELECT cognome FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN cognome VARCHAR(50) DEFAULT NULL");
        echo "- Added 'cognome' to users.\n";
    }

    // Users: profile_image
    try {
        $pdo->query("SELECT profile_image FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL");
        echo "- Added 'profile_image' to users.\n";
    }

    // Users: google_calendar_id
    try {
        $pdo->query("SELECT google_calendar_id FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_calendar_id VARCHAR(255) DEFAULT NULL");
        echo "- Added 'google_calendar_id' to users.\n";
    }

    // Users: google_access_token
    try {
        $pdo->query("SELECT google_access_token FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_access_token TEXT DEFAULT NULL");
        echo "- Added 'google_access_token' to users.\n";
    }

    // Users: google_refresh_token
    try {
        $pdo->query("SELECT google_refresh_token FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_refresh_token TEXT DEFAULT NULL");
        echo "- Added 'google_refresh_token' to users.\n";
    }

    // Users: google_token_expires_at
    try {
        $pdo->query("SELECT google_token_expires_at FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_token_expires_at INT DEFAULT NULL");
        echo "- Added 'google_token_expires_at' to users.\n";
    }


    // Projects: user_id (Backfill if needed, but handled by create)
    // Projects: data_modifica
    try {
        $pdo->query("SELECT data_modifica FROM projects LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "- Added 'data_modifica' to projects.\n";
    }

    // Projects: descrizione
    try {
        $pdo->query("SELECT descrizione FROM projects LIMIT 1");
    } catch (Exception $e) {
        // If 'description' exists, rename it. If neither, add 'descrizione'
        try {
            $pdo->query("SELECT description FROM projects LIMIT 1");
            $pdo->exec("ALTER TABLE projects CHANGE COLUMN description descrizione TEXT DEFAULT NULL");
            echo "- Renamed 'description' to 'descrizione' in projects.\n";
        } catch (Exception $e2) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN descrizione TEXT DEFAULT NULL");
            echo "- Added 'descrizione' to projects.\n";
        }
        }
    // Projects: ordinamento
    try {
        $pdo->query("SELECT ordinamento FROM projects LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN ordinamento INT DEFAULT 0");
        echo "- Added 'ordinamento' to projects.\n";
    }

    // Projects: colore
    try {
        $pdo->query("SELECT colore FROM projects LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN colore VARCHAR(7) DEFAULT '#6366f1'"); // Default indigo-500
        echo "- Added 'colore' to projects.\n";
    }

    // 2b. Project Attachments
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL, -- 'link', 'drive_file'
        name VARCHAR(255) NOT NULL,
        url TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "- Project Attachments table checked.\n";

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

    // TEAMS
    $pdo->exec("CREATE TABLE IF NOT EXISTS teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "- Teams table checked.\n";

    // TEAM MEMBERS
    $pdo->exec("CREATE TABLE IF NOT EXISTS team_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_id INT NOT NULL,
        user_id INT NOT NULL,
        role ENUM('admin', 'member') DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_team_member (team_id, user_id)
    )");
    echo "- Team Members table checked.\n";

    // NOTIFICATIONS
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL, -- 'project_add', 'task_assign', 'friend_req'
        message TEXT NOT NULL,
        link VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "- Notifications table checked.\n";

    // FRIENDSHIPS
    // COLLABORATIONS (ex Friendships)
    // Check if old table exists
    try {
        $result = $pdo->query("SHOW TABLES LIKE 'friendships'");
        if ($result->rowCount() > 0) {
            $pdo->exec("RENAME TABLE friendships TO collaborations");
            echo "- Renamed 'friendships' to 'collaborations'.\n";
        }
    } catch (Exception $e) {
        // Ignore
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS collaborations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requester_id INT NOT NULL,
        receiver_id INT NOT NULL,
        status ENUM('pending', 'accepted') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_collaboration (requester_id, receiver_id)
    )");
    echo "- Collaborations table checked.\n";

    // 4. Backfill Project Owners into project_members
    echo "Backfilling project owners...\n";
    // Insert owners into project_members if they don't exist
    $sqlBackfill = "INSERT IGNORE INTO project_members (project_id, user_id, role)
                    SELECT id, user_id, 'owner' FROM projects";
    $pdo->exec($sqlBackfill);
    echo "- Project owners backfilled.\n";

    echo "\n<strong style='color:green'>SUCCESS! Database setup completed.</strong>";
    echo "\nDelete this file (setup_prod.php) from the server after use for security.";

    // 4. Integrations Tables
    echo "Checking Integration tables...\n";

    // ATTACHMENTS (Tasks & Projects)
    // Check if old table exists and rename it if needed
    try {
        $result = $pdo->query("SHOW TABLES LIKE 'task_attachments'");
        if ($result->rowCount() > 0) {
            $pdo->exec("RENAME TABLE task_attachments TO attachments");
            $pdo->exec("ALTER TABLE attachments MODIFY COLUMN task_id INT NULL");
            $pdo->exec("ALTER TABLE attachments ADD COLUMN project_id INT NULL AFTER task_id");
            $pdo->exec("ALTER TABLE attachments ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE");
            echo "- Renamed task_attachments to attachments and updated schema.\n";
        }
    } catch (Exception $e) {
        // Ignore rename error if table doesn't exist etc.
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NULL,
        project_id INT NULL,
        user_id INT NOT NULL, 
        file_provider ENUM('google_drive', 'local') DEFAULT 'google_drive',
        external_file_id VARCHAR(255), 
        file_name VARCHAR(255),
        file_url TEXT, 
        mime_type VARCHAR(100),
        icon_url TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "- Attachments table checked.\n";

} catch (PDOException $e) {
    echo "\n<strong style='color:red'>ERROR: " . $e->getMessage() . "</strong>";
}
echo "</pre>";
?>