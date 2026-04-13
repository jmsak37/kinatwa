<?php
require_once __DIR__ . '/../kinatwadb.php';
require_once __DIR__ . '/schema_sync.php';

header('Content-Type: application/json');

try {
    syncKinatwaSchema($pdo);

    function kinatwa_file_url(string $fileName): string
    {
        $parts = array_map('rawurlencode', explode('/', str_replace('\\', '/', $fileName)));
        return '/kinatwa/uploads/KINAT/' . implode('/', $parts);
    }

    function kinatwa_file_ext_type(string $ext): string
    {
        $ext = strtolower($ext);

        if (in_array($ext, ['.png', '.jpg', '.jpeg', '.webp', '.bmp', '.gif'], true)) {
            return 'image';
        }
        if (in_array($ext, ['.mp4', '.mov', '.mkv', '.webm', '.avi', '.m4v'], true)) {
            return 'video';
        }
        if ($ext === '.pdf') {
            return 'pdf';
        }
        if ($ext === '.docx') {
            return 'docx';
        }
        if ($ext === '.pptx') {
            return 'pptx';
        }
        if (in_array($ext, ['.txt', '.csv', '.log'], true)) {
            return 'text';
        }

        return 'other';
    }

    function kinatwa_safe_text_from_file(string $path): string
    {
        if (!is_file($path)) {
            return 'File not found.';
        }

        $ext = '.' . strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, ['.txt', '.csv', '.log'], true)) {
            $text = @file_get_contents($path);
            if ($text === false || trim($text) === '') {
                return 'No readable content found.';
            }
            return mb_substr(trim($text), 0, 5000);
        }

        return 'No readable content found.';
    }

    function kinatwa_sync_media_files(PDO $pdo): void
    {
        $uploadRoot = realpath(__DIR__ . '/../uploads/KINAT');
        if (!$uploadRoot || !is_dir($uploadRoot)) {
            return;
        }

        $existingFiles = [];

        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($rii as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $fullPath = $file->getPathname();
            $relativePath = str_replace('\\', '/', substr($fullPath, strlen($uploadRoot) + 1));
            $existingFiles[$relativePath] = $fullPath;

            $check = $pdo->prepare("SELECT id FROM media_files WHERE file_name = ? LIMIT 1");
            $check->execute([$relativePath]);
            $found = $check->fetch(PDO::FETCH_ASSOC);

            $ext = '.' . strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $type = kinatwa_file_ext_type($ext);

            if ($found) {
                $upd = $pdo->prepare("
                    UPDATE media_files
                    SET original_name = ?,
                        file_path = ?,
                        file_ext = ?,
                        file_type = ?
                    WHERE id = ?
                ");
                $upd->execute([
                    basename($relativePath),
                    $fullPath,
                    $ext,
                    $type,
                    $found['id']
                ]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO media_files
                    (file_name, original_name, file_path, file_ext, file_type, enabled, fit_mode, upload_type, uploaded_by, play_seconds, play_order, show_bottom_messages)
                    VALUES (?, ?, ?, ?, ?, 1, 'fit_both', 'auto_resize', NULL, 6, 0, 1)
                ");
                $ins->execute([
                    $relativePath,
                    basename($relativePath),
                    $fullPath,
                    $ext,
                    $type
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

    kinatwa_sync_media_files($pdo);

    $pdo->exec("
        UPDATE security_codes
        SET status = 'expired'
        WHERE status = 'pending' AND expires_at <= NOW()
    ");

    $displayStmt = $pdo->prepare("SELECT * FROM display_state WHERE id = 1 LIMIT 1");
    $displayStmt->execute();
    $display = $displayStmt->fetch(PDO::FETCH_ASSOC);

    if (!$display) {
        echo json_encode([
            "playlist" => [],
            "errors" => ["Display state not found."],
            "refresh_token" => 1
        ]);
        exit;
    }

    $now = new DateTime();

    if (!empty($display['scheduled_file_id']) && !empty($display['scheduled_time'])) {
        $targetToday = DateTime::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $display['scheduled_time']);

        if ($targetToday && $now >= $targetToday) {
            $activate = $pdo->prepare("
                UPDATE display_state
                SET forced_file_id = scheduled_file_id,
                    priority_action = 'play_now',
                    play_type = COALESCE(scheduled_play_type, 'loop'),
                    minutes = COALESCE(scheduled_minutes, 5),
                    active_until = DATE_ADD(NOW(), INTERVAL COALESCE(scheduled_minutes, 5) MINUTE),
                    scheduled_file_id = NULL,
                    scheduled_action = NULL,
                    scheduled_play_type = NULL,
                    scheduled_minutes = NULL,
                    scheduled_time = NULL,
                    admin_message = NULL,
                    admin_message_until = NULL,
                    version = version + 1
                WHERE id = 1
            ");
            $activate->execute();

            $displayStmt->execute();
            $display = $displayStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!empty($display['active_until'])) {
        $activeUntil = new DateTime($display['active_until']);
        if ($now >= $activeUntil) {
            $clear = $pdo->prepare("
                UPDATE display_state
                SET forced_file_id = NULL,
                    priority_action = NULL,
                    play_type = 'loop',
                    minutes = 5,
                    active_until = NULL,
                    version = version + 1
                WHERE id = 1
            ");
            $clear->execute();

            $displayStmt->execute();
            $display = $displayStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!empty($display['admin_message_until'])) {
        $msgUntil = new DateTime($display['admin_message_until']);
        if ($now >= $msgUntil) {
            $clearMsg = $pdo->prepare("
                UPDATE display_state
                SET admin_message = NULL,
                    admin_message_until = NULL,
                    version = version + 1
                WHERE id = 1
            ");
            $clearMsg->execute();

            $displayStmt->execute();
            $display = $displayStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    $playlist = [];
    $errors = [];
    $isForcedMode = false;

    if (!empty($display['forced_file_id']) && !empty($display['active_until'])) {
        $forcedStmt = $pdo->prepare("
            SELECT *
            FROM media_files
            WHERE id = ? AND enabled = 1
            LIMIT 1
        ");
        $forcedStmt->execute([$display['forced_file_id']]);
        $forced = $forcedStmt->fetch(PDO::FETCH_ASSOC);

        if ($forced) {
            $isForcedMode = true;
            $fileUrl = kinatwa_file_url($forced['file_name']);
            $fit = $forced['fit_mode'] ?: 'fit_both';
            $playSeconds = max(1, (int)($forced['play_seconds'] ?? 6));
            $bottomMessages = (int)($forced['show_bottom_messages'] ?? 1) === 1;

            $items = [];

            if ($forced['file_type'] === 'image') {
                $items[] = [
                    "type" => "image",
                    "src" => $fileUrl,
                    "fit" => $fit,
                    "seconds" => $playSeconds,
                    "show_bottom_messages" => $bottomMessages
                ];
            } elseif ($forced['file_type'] === 'video') {
                $items[] = [
                    "type" => "video",
                    "src" => $fileUrl,
                    "fit" => $fit,
                    "seconds" => $playSeconds,
                    "show_bottom_messages" => $bottomMessages
                ];
            } elseif ($forced['file_type'] === 'pdf') {
                $items[] = [
                    "type" => "iframe",
                    "src" => $fileUrl,
                    "fit" => $fit,
                    "seconds" => $playSeconds,
                    "show_bottom_messages" => $bottomMessages
                ];
            } else {
                $items[] = [
                    "type" => "text",
                    "content" => kinatwa_safe_text_from_file($forced['file_path']),
                    "seconds" => $playSeconds,
                    "show_bottom_messages" => $bottomMessages
                ];
            }

            $playlist[] = [
                "filename" => $forced['file_name'],
                "forced" => true,
                "items" => $items
            ];
        } else {
            $errors[] = "Forced file was not found.";
        }
    }

    if (!$isForcedMode && !empty($display['admin_message']) && !empty($display['admin_message_until'])) {
        $secondsLeft = max(1, (int)floor((strtotime($display['admin_message_until']) - time())));
        $playlist[] = [
            "filename" => "__ADMIN_MESSAGE__",
            "forced" => true,
            "items" => [[
                "type" => "admin_message",
                "content" => $display['admin_message'],
                "html" => $display['admin_message'],
                "seconds" => $secondsLeft,
                "show_bottom_messages" => false
            ]]
        ];
        $isForcedMode = true;
    }

    if (!$isForcedMode) {
        $files = $pdo->query("
            SELECT *
            FROM media_files
            WHERE enabled = 1
            ORDER BY play_order ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($files as $file) {
            $fileUrl = kinatwa_file_url($file['file_name']);
            $fit = $file['fit_mode'] ?: 'fit_both';
            $playSeconds = max(1, (int)($file['play_seconds'] ?? 6));
            $bottomMessages = (int)($file['show_bottom_messages'] ?? 1) === 1;

            $items = [];

            if ($file['file_type'] === 'image') {
                $items[] = [
                    "type" => "image",
                    "src" => $fileUrl,
                    "fit" => $fit,
                    "seconds" => $playSeconds,
                    "show_bottom_messages" => $bottomMessages
                ];
            } elseif ($file['file_type'] === 'video') {
                $items[] = [
                    "type" => "video",
                    "src" => $fileUrl,
                    "fit" => $fit,
                    "seconds" => $playSeconds,
                    "show_bottom_messages" => $bottomMessages
                ];
            } elseif ($file['file_type'] === 'pdf') {
                $items[] = [
                    "type" => "iframe",
                    "src" => $fileUrl,
                    "fit" => $fit,
                    "seconds" => $playSeconds,
                    "show_bottom_messages" => $bottomMessages
                ];
            } else {
                $items[] = [
                    "type" => "text",
                    "content" => kinatwa_safe_text_from_file($file['file_path']),
                    "seconds" => $playSeconds,
                    "show_bottom_messages" => $bottomMessages
                ];
            }

            $playlist[] = [
                "filename" => $file['file_name'],
                "forced" => false,
                "items" => $items
            ];
        }
    }

    echo json_encode([
        "playlist" => $playlist,
        "errors" => $errors,
        "refresh_token" => (int)($display['version'] ?? 1)
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "playlist" => [],
        "errors" => [$e->getMessage()],
        "refresh_token" => 1
    ]);
}