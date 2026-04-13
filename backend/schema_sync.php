<?php
function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function syncKinatwaSchema(PDO $pdo): void
{
    if (!tableExists($pdo, 'admins')) {
        $pdo->exec("
            CREATE TABLE admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                email VARCHAR(255) NULL,
                password_hash VARCHAR(255) NOT NULL,
                role TINYINT NOT NULL DEFAULT 2,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                deleted_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        if (!columnExists($pdo, 'admins', 'email')) {
            $pdo->exec("ALTER TABLE admins ADD COLUMN email VARCHAR(255) NULL AFTER username");
        }
        if (!columnExists($pdo, 'admins', 'deleted_at')) {
            $pdo->exec("ALTER TABLE admins ADD COLUMN deleted_at DATETIME NULL AFTER is_active");
        }
    }

    if (!tableExists($pdo, 'security_codes')) {
        $pdo->exec("
            CREATE TABLE security_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(6) NOT NULL,
                generated_by INT NULL,
                intended_username VARCHAR(100) NULL,
                intended_email VARCHAR(255) NULL,
                request_id INT NULL,
                status ENUM('pending','used','expired','cancelled') NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                used_by INT NULL,
                used_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        if (!columnExists($pdo, 'security_codes', 'intended_email')) {
            $pdo->exec("ALTER TABLE security_codes ADD COLUMN intended_email VARCHAR(255) NULL AFTER intended_username");
        }
        if (!columnExists($pdo, 'security_codes', 'request_id')) {
            $pdo->exec("ALTER TABLE security_codes ADD COLUMN request_id INT NULL AFTER intended_email");
        }
    }

    if (!tableExists($pdo, 'admin_code_requests')) {
        $pdo->exec("
            CREATE TABLE admin_code_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                requested_username VARCHAR(100) NOT NULL,
                requested_email VARCHAR(255) NOT NULL,
                status ENUM('pending','approved','rejected','accepted','expired') NOT NULL DEFAULT 'pending',
                requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                responded_at DATETIME NULL,
                approved_by INT NULL,
                approved_code VARCHAR(6) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        if (!columnExists($pdo, 'admin_code_requests', 'requested_email')) {
            $pdo->exec("ALTER TABLE admin_code_requests ADD COLUMN requested_email VARCHAR(255) NOT NULL DEFAULT '' AFTER requested_username");
        }
    }

    if (!tableExists($pdo, 'media_files')) {
        $pdo->exec("
            CREATE TABLE media_files (
                id INT AUTO_INCREMENT PRIMARY KEY,
                file_name VARCHAR(255) NOT NULL UNIQUE,
                original_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                preview_path VARCHAR(500) NULL,
                file_ext VARCHAR(20) NULL,
                file_type ENUM('image','video','pdf','docx','pptx','text','other') NOT NULL DEFAULT 'other',
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                fit_mode VARCHAR(50) NOT NULL DEFAULT 'fit_both',
                upload_type VARCHAR(50) NOT NULL DEFAULT 'auto_resize',
                uploaded_by INT NULL,
                uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                play_seconds INT NOT NULL DEFAULT 6,
                play_order INT NOT NULL DEFAULT 0,
                show_bottom_messages TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        if (!columnExists($pdo, 'media_files', 'play_seconds')) {
            $pdo->exec("ALTER TABLE media_files ADD COLUMN play_seconds INT NOT NULL DEFAULT 6 AFTER uploaded_at");
        }
        if (!columnExists($pdo, 'media_files', 'play_order')) {
            $pdo->exec("ALTER TABLE media_files ADD COLUMN play_order INT NOT NULL DEFAULT 0 AFTER play_seconds");
        }
        if (!columnExists($pdo, 'media_files', 'show_bottom_messages')) {
            $pdo->exec("ALTER TABLE media_files ADD COLUMN show_bottom_messages TINYINT(1) NOT NULL DEFAULT 1 AFTER play_order");
        }
    }

    if (!tableExists($pdo, 'display_state')) {
        $pdo->exec("
            CREATE TABLE display_state (
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
    }

    $checkDisplay = $pdo->query("SELECT COUNT(*) FROM display_state WHERE id = 1")->fetchColumn();
    if ((int)$checkDisplay === 0) {
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
        ");
    }

    if (!tableExists($pdo, 'notifications')) {
        $pdo->exec("
            CREATE TABLE notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                link_url VARCHAR(500) NULL,
                target_role ENUM('all','main_admin','normal_admin') NOT NULL DEFAULT 'all',
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        if (!columnExists($pdo, 'notifications', 'link_url')) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN link_url VARCHAR(500) NULL AFTER message");
        }
        if (!columnExists($pdo, 'notifications', 'is_read')) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER link_url");
        }
    }

    if (!tableExists($pdo, 'deleted_admin_accounts')) {
        $pdo->exec("
            CREATE TABLE deleted_admin_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                original_admin_id INT NULL,
                username VARCHAR(100) NOT NULL,
                email VARCHAR(255) NULL,
                password_hash VARCHAR(255) NOT NULL,
                role TINYINT NOT NULL,
                created_by INT NULL,
                deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                restore_until DATETIME NOT NULL,
                deleted_by INT NULL,
                delete_code VARCHAR(50) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        if (!columnExists($pdo, 'deleted_admin_accounts', 'original_admin_id')) {
            $pdo->exec("ALTER TABLE deleted_admin_accounts ADD COLUMN original_admin_id INT NULL AFTER id");
        }
        if (!columnExists($pdo, 'deleted_admin_accounts', 'email')) {
            $pdo->exec("ALTER TABLE deleted_admin_accounts ADD COLUMN email VARCHAR(255) NULL AFTER username");
        }
        if (!columnExists($pdo, 'deleted_admin_accounts', 'created_by')) {
            $pdo->exec("ALTER TABLE deleted_admin_accounts ADD COLUMN created_by INT NULL AFTER role");
        }
        if (!columnExists($pdo, 'deleted_admin_accounts', 'deleted_by')) {
            $pdo->exec("ALTER TABLE deleted_admin_accounts ADD COLUMN deleted_by INT NULL AFTER restore_until");
        }
        if (!columnExists($pdo, 'deleted_admin_accounts', 'delete_code')) {
            $pdo->exec("ALTER TABLE deleted_admin_accounts ADD COLUMN delete_code VARCHAR(50) NULL AFTER deleted_by");
        }
    }

    if (tableExists($pdo, 'deleted_admin_accounts') && columnExists($pdo, 'deleted_admin_accounts', 'admin_id') && columnExists($pdo, 'deleted_admin_accounts', 'original_admin_id')) {
        $pdo->exec("
            UPDATE deleted_admin_accounts
            SET original_admin_id = admin_id
            WHERE original_admin_id IS NULL AND admin_id IS NOT NULL
        ");
    }
}