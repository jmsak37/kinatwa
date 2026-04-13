<?php
$host = '127.0.0.1';
$dbname = 'kinatwa_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    die("Database connection failed: " . $e->getMessage());
}

function kinatwaEnsureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);

    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function kinatwaEnsureTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role TINYINT NOT NULL DEFAULT 2,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    kinatwaEnsureColumn($pdo, 'admins', 'email', "VARCHAR(150) NULL AFTER `username`");
    kinatwaEnsureColumn($pdo, 'admins', 'deleted_at', "DATETIME NULL AFTER `is_active`");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS deleted_admin_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_admin_id INT NULL,
            username VARCHAR(100) NOT NULL,
            email VARCHAR(150) NULL,
            password_hash VARCHAR(255) NOT NULL,
            role TINYINT NOT NULL DEFAULT 2,
            deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            restore_until DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS security_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(6) NOT NULL,
            generated_by INT NULL,
            intended_username VARCHAR(100) NULL,
            request_id INT NULL,
            status ENUM('pending','used','expired','cancelled') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            used_by INT NULL,
            used_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    kinatwaEnsureColumn($pdo, 'security_codes', 'intended_email', "VARCHAR(150) NULL AFTER `intended_username`");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_code_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requested_username VARCHAR(100) NOT NULL,
            status ENUM('pending','approved','rejected','accepted','expired') NOT NULL DEFAULT 'pending',
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            responded_at DATETIME NULL,
            approved_by INT NULL,
            approved_code VARCHAR(6) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    kinatwaEnsureColumn($pdo, 'admin_code_requests', 'requested_email', "VARCHAR(150) NULL AFTER `requested_username`");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS media_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            preview_path VARCHAR(500) NULL,
            file_ext VARCHAR(20) NULL,
            file_type ENUM('image','video','pdf','docx','pptx','text','other') NOT NULL DEFAULT 'other',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            fit_mode VARCHAR(50) NOT NULL DEFAULT 'fit_both',
            upload_type VARCHAR(50) NOT NULL DEFAULT 'auto_resize',
            uploaded_by INT NULL,
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    kinatwaEnsureColumn($pdo, 'media_files', 'play_seconds', "INT NOT NULL DEFAULT 6 AFTER `uploaded_at`");
    kinatwaEnsureColumn($pdo, 'media_files', 'play_order', "INT NOT NULL DEFAULT 0 AFTER `play_seconds`");
    kinatwaEnsureColumn($pdo, 'media_files', 'show_bottom_messages', "TINYINT(1) NOT NULL DEFAULT 1 AFTER `play_order`");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS display_state (
            id INT PRIMARY KEY,
            forced_file_id INT NULL,
            priority_action VARCHAR(50) NULL,
            play_type VARCHAR(50) NOT NULL DEFAULT 'loop',
            minutes INT NOT NULL DEFAULT 5,
            start_time VARCHAR(10) NULL,
            active_until DATETIME NULL,
            scheduled_file_id INT NULL,
            scheduled_action VARCHAR(50) NULL,
            scheduled_play_type VARCHAR(50) NULL,
            scheduled_minutes INT NULL,
            scheduled_time VARCHAR(10) NULL,
            admin_message TEXT NULL,
            admin_message_until DATETIME NULL,
            version INT NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        INSERT INTO display_state (
            id, forced_file_id, priority_action, play_type, minutes,
            start_time, active_until, scheduled_file_id, scheduled_action,
            scheduled_play_type, scheduled_minutes, scheduled_time,
            admin_message, admin_message_until, version
        ) VALUES (
            1, NULL, 'play_now', 'loop', 5,
            '', NULL, NULL, NULL,
            NULL, NULL, '',
            NULL, NULL, 1
        )
        ON DUPLICATE KEY UPDATE id = id
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            target_role ENUM('all','main_admin','normal_admin') NOT NULL DEFAULT 'all',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    kinatwaEnsureColumn($pdo, 'notifications', 'link_url', "VARCHAR(500) NULL AFTER `message`");
    kinatwaEnsureColumn($pdo, 'notifications', 'is_read', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `link_url`");
}

kinatwaEnsureTable($pdo);