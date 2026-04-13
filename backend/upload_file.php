<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

requireLogin();
cleanupExpiredCodes($pdo);

$uploadRoot = __DIR__ . '/../uploads/KINAT';
if (!is_dir($uploadRoot)) {
    mkdir($uploadRoot, 0777, true);
}

if (!isset($_FILES['document']) || empty($_FILES['document']['name'])) {
    echo json_encode([
        "ok" => false,
        "message" => "Please choose a file."
    ]);
    exit;
}

try {
    $name = basename($_FILES['document']['name']);
    $safeName = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $name);
    $tmp = $_FILES['document']['tmp_name'];
    $dest = $uploadRoot . '/' . $safeName;

    if (!move_uploaded_file($tmp, $dest)) {
        echo json_encode([
            "ok" => false,
            "message" => "Upload failed."
        ]);
        exit;
    }

    $ext = '.' . strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
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

    $fitMode = trim($_POST['fit_mode'] ?? 'fit_both');
    $uploadType = trim($_POST['upload_type'] ?? 'auto_resize');
    $displayMode = trim($_POST['display_mode'] ?? 'normal');
    $playType = trim($_POST['play_type'] ?? 'loop');
    $playMinutes = max(1, (int)($_POST['play_minutes'] ?? 5));
    $startTime = trim($_POST['start_time'] ?? '');

    $check = $pdo->prepare("SELECT id FROM media_files WHERE file_name = ? LIMIT 1");
    $check->execute([$safeName]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $upd = $pdo->prepare("
            UPDATE media_files
            SET original_name = ?,
                file_path = ?,
                file_ext = ?,
                file_type = ?,
                fit_mode = ?,
                upload_type = ?,
                enabled = 1,
                uploaded_by = ?
            WHERE id = ?
        ");
        $upd->execute([
            $safeName,
            realpath($dest) ?: $dest,
            $ext,
            $type,
            $fitMode,
            $uploadType,
            currentAdminId(),
            $existing['id']
        ]);
        $fileId = (int)$existing['id'];
    } else {
        $ins = $pdo->prepare("
            INSERT INTO media_files
            (file_name, original_name, file_path, file_ext, file_type, enabled, fit_mode, upload_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)
        ");
        $ins->execute([
            $safeName,
            $safeName,
            realpath($dest) ?: $dest,
            $ext,
            $type,
            $fitMode,
            $uploadType,
            currentAdminId()
        ]);
        $fileId = (int)$pdo->lastInsertId();
    }

    if ($displayMode === 'priority_now') {
        $pdo->prepare("
            UPDATE display_state
            SET forced_file_id = ?,
                priority_action = 'play_now',
                play_type = ?,
                minutes = ?,
                active_until = CASE
                    WHEN ? = 'show_once' THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                    ELSE NULL
                END,
                version = version + 1
            WHERE id = 1
        ")->execute([$fileId, $playType, $playMinutes, $playType, $playMinutes]);
    } elseif ($displayMode === 'priority_time') {
        if ($startTime === '') {
            echo json_encode([
                "ok" => false,
                "message" => "Start time is required for scheduled play."
            ]);
            exit;
        }

        $pdo->prepare("
            UPDATE display_state
            SET scheduled_file_id = ?,
                scheduled_action = 'schedule',
                scheduled_play_type = ?,
                scheduled_minutes = ?,
                scheduled_time = ?,
                version = version + 1
            WHERE id = 1
        ")->execute([$fileId, $playType, $playMinutes, $startTime]);
    } else {
        $pdo->prepare("UPDATE display_state SET version = version + 1 WHERE id = 1")->execute();
    }

    addNotification(
        $pdo,
        'File Uploaded',
        "A file was uploaded: {$safeName}.",
        'all',
        '/kinatwa/admin.html'
    );

    $stmt = $pdo->prepare("SELECT version FROM display_state WHERE id = 1");
    $stmt->execute();
    $ver = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok" => true,
        "message" => "Uploaded successfully.",
        "version" => (int)($ver['version'] ?? 1)
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}