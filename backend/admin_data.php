<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!refreshAdminSession($pdo)) {
    echo json_encode([
        "ok" => false,
        "message" => "Please login first."
    ]);
    exit;
}

try {
    cleanupExpiredCodes($pdo);
    $pdo->exec("DELETE FROM deleted_admin_accounts WHERE restore_until < NOW()");

    $uploadRoot = __DIR__ . '/../uploads/KINAT';
    if (!is_dir($uploadRoot)) {
        @mkdir($uploadRoot, 0777, true);
    }

    $realUploadRoot = realpath($uploadRoot);
    if ($realUploadRoot && is_dir($realUploadRoot)) {
        $existingFiles = [];

        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realUploadRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($rii as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $fullPath = $file->getPathname();
            $relativePath = str_replace('\\', '/', substr($fullPath, strlen($realUploadRoot) + 1));
            $existingFiles[$relativePath] = $fullPath;

            $ext = '.' . strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $type = 'other';

            if (in_array($ext, ['.png', '.jpg', '.jpeg', '.webp', '.bmp', '.gif'], true)) {
                $type = 'image';
            } elseif (in_array($ext, ['.mp4', '.mov', '.mkv', '.webm', '.avi', '.m4v'], true)) {
                $type = 'video';
            } elseif ($ext === '.pdf') {
                $type = 'pdf';
            } elseif ($ext === '.docx') {
                $type = 'docx';
            } elseif ($ext === '.pptx') {
                $type = 'pptx';
            } elseif (in_array($ext, ['.txt', '.csv', '.log'], true)) {
                $type = 'text';
            }

            $check = $pdo->prepare("SELECT id FROM media_files WHERE file_name = ? LIMIT 1");
            $check->execute([$relativePath]);
            $row = $check->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $upd = $pdo->prepare("
                    UPDATE media_files
                    SET original_name = ?, file_path = ?, file_ext = ?, file_type = ?
                    WHERE id = ?
                ");
                $upd->execute([
                    basename($relativePath),
                    $fullPath,
                    $ext,
                    $type,
                    $row['id']
                ]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO media_files (
                        file_name, original_name, file_path, file_ext, file_type,
                        enabled, fit_mode, upload_type, uploaded_by, play_seconds, play_order, show_bottom_messages
                    ) VALUES (?, ?, ?, ?, ?, 1, 'fit_both', 'auto_resize', ?, 6, 0, 1)
                ");
                $ins->execute([
                    $relativePath,
                    basename($relativePath),
                    $fullPath,
                    $ext,
                    $type,
                    currentAdminId()
                ]);
            }
        }

        $dbFiles = $pdo->query("SELECT id, file_name FROM media_files")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($dbFiles as $dbFile) {
            if (!isset($existingFiles[$dbFile['file_name']])) {
                $del = $pdo->prepare("DELETE FROM media_files WHERE id = ?");
                $del->execute([$dbFile['id']]);
            }
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM display_state WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $display = $stmt->fetch(PDO::FETCH_ASSOC);

    $forcedFileName = null;
    $scheduledFileName = null;

    if (!empty($display['forced_file_id'])) {
        $f = $pdo->prepare("SELECT file_name FROM media_files WHERE id = ? LIMIT 1");
        $f->execute([$display['forced_file_id']]);
        $forcedFileName = $f->fetchColumn() ?: null;
    }

    if (!empty($display['scheduled_file_id'])) {
        $f = $pdo->prepare("SELECT file_name FROM media_files WHERE id = ? LIMIT 1");
        $f->execute([$display['scheduled_file_id']]);
        $scheduledFileName = $f->fetchColumn() ?: null;
    }

    $admins = $pdo->query("
        SELECT id, username, COALESCE(email, '') AS email, role, created_at
        FROM admins
        WHERE is_active = 1
        ORDER BY role ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $activeCodes = [];
    $pendingRequests = [];
    $deletedAccounts = [];

    if (isMainAdmin()) {
        $activeCodes = $pdo->query("
            SELECT id, code, intended_username, COALESCE(intended_email, '') AS intended_email, expires_at
            FROM security_codes
            WHERE status = 'pending' AND expires_at > NOW()
            ORDER BY id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $pendingRequests = $pdo->query("
            SELECT id, requested_username, COALESCE(requested_email, '') AS requested_email, requested_at
            FROM admin_code_requests
            WHERE status = 'pending'
            ORDER BY id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $deletedAccounts = $pdo->query("
            SELECT id, username, COALESCE(email, '') AS email, deleted_at, restore_until
            FROM deleted_admin_accounts
            ORDER BY id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    $files = $pdo->query("
        SELECT id, file_name, enabled, fit_mode, upload_type, file_ext, file_type, play_seconds, play_order, show_bottom_messages
        FROM media_files
        ORDER BY play_order ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $notifications = $pdo->query("
        SELECT id, title, message, COALESCE(link_url, '') AS link_url, is_read, created_at
        FROM notifications
        WHERE target_role = 'all'
           OR (target_role = 'main_admin' AND " . (isMainAdmin() ? "1=1" : "1=0") . ")
           OR (target_role = 'normal_admin' AND " . (!isMainAdmin() ? "1=1" : "1=0") . ")
        ORDER BY id DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);

    $unreadStmt = $pdo->query("
        SELECT COUNT(*)
        FROM notifications
        WHERE is_read = 0
          AND (
            target_role = 'all'
            OR (target_role = 'main_admin' AND " . (isMainAdmin() ? "1=1" : "1=0") . ")
            OR (target_role = 'normal_admin' AND " . (!isMainAdmin() ? "1=1" : "1=0") . ")
          )
    ");
    $unreadCount = (int)$unreadStmt->fetchColumn();

    $meStmt = $pdo->prepare("SELECT COALESCE(email, '') AS email FROM admins WHERE id = ? LIMIT 1");
    $meStmt->execute([currentAdminId()]);
    $myRow = $meStmt->fetch(PDO::FETCH_ASSOC);
    $myEmail = $myRow ? (string)$myRow['email'] : '';

    echo json_encode([
        "ok" => true,
        "admin_id" => currentAdminId(),
        "admin_username" => $_SESSION['admin_username'] ?? '',
        "admin_role" => $_SESSION['admin_role'] ?? 2,
        "admin_email" => $myEmail,
        "email_missing" => trim($myEmail) === '',
        "version" => (int)($display['version'] ?? 1),
        "files_hash" => md5(json_encode($files)),
        "requests_hash" => md5(json_encode($pendingRequests)),
        "admins_hash" => md5(json_encode($admins)),
        "notifications_hash" => md5(json_encode($notifications)),
        "admins" => $admins,
        "active_codes" => $activeCodes,
        "pending_requests" => $pendingRequests,
        "files" => $files,
        "notifications" => $notifications,
        "unread_notification_count" => $unreadCount,
        "deleted_accounts" => $deletedAccounts,
        "display_state_info" => [
            "forced_file_name" => $forcedFileName,
            "scheduled_file_name" => $scheduledFileName,
            "play_type" => $display['play_type'] ?? 'loop',
            "minutes" => (int)($display['minutes'] ?? 5),
            "active_until" => $display['active_until'] ?? null,
            "scheduled_time" => $display['scheduled_time'] ?? '',
            "scheduled_minutes" => $display['scheduled_minutes'] ?? null,
            "admin_message" => $display['admin_message'] ?? null,
            "admin_message_until" => $display['admin_message_until'] ?? null
        ]
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}