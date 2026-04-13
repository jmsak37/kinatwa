<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/schema_sync.php';

header('Content-Type: application/json');

requireLogin();

try {
    syncKinatwaSchema($pdo);

    $fileName = trim($_POST['selected_priority_file'] ?? '');
    $priorityAction = trim($_POST['priority_action'] ?? 'play_now');
    $priorityPlayType = trim($_POST['priority_play_type'] ?? 'loop');
    $priorityMinutes = max(1, (int)($_POST['priority_minutes'] ?? 5));
    $priorityTime = trim($_POST['priority_time'] ?? '');

    if ($fileName === '') {
        echo json_encode([
            "ok" => false,
            "message" => "Please select a file first."
        ]);
        exit;
    }

    if (!in_array($priorityAction, ['play_now', 'schedule'], true)) {
        $priorityAction = 'play_now';
    }

    if (!in_array($priorityPlayType, ['loop', 'show_once'], true)) {
        $priorityPlayType = 'loop';
    }

    if ($priorityAction === 'schedule' && $priorityTime === '') {
        echo json_encode([
            "ok" => false,
            "message" => "Please choose the scheduled time."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, file_name, enabled
        FROM media_files
        WHERE file_name = ?
        LIMIT 1
    ");
    $stmt->execute([$fileName]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        echo json_encode([
            "ok" => false,
            "message" => "Selected file was not found."
        ]);
        exit;
    }

    if ((int)$file['enabled'] !== 1) {
        echo json_encode([
            "ok" => false,
            "message" => "This file is currently switched off. Turn it on first."
        ]);
        exit;
    }

    if ($priorityAction === 'play_now') {
        $pdo->prepare("
            UPDATE display_state
            SET forced_file_id = ?,
                priority_action = 'play_now',
                play_type = ?,
                minutes = ?,
                active_until = DATE_ADD(NOW(), INTERVAL ? MINUTE),

                scheduled_file_id = NULL,
                scheduled_action = NULL,
                scheduled_play_type = NULL,
                scheduled_minutes = NULL,
                scheduled_time = NULL,

                admin_message = NULL,
                admin_message_until = NULL,

                version = version + 1
            WHERE id = 1
        ")->execute([
            $file['id'],
            $priorityPlayType,
            $priorityMinutes,
            $priorityMinutes
        ]);

        $pdo->prepare("
            INSERT INTO notifications (title, message, target_role, link_url)
            VALUES ('Priority Play Started', ?, 'all', '')
        ")->execute([
            "File set to play now: {$fileName}, play type: {$priorityPlayType}, duration: {$priorityMinutes} minute(s)."
        ]);

        $message = "Display updated for: {$fileName}. It will control the display for {$priorityMinutes} minute(s).";
    } else {
        $pdo->prepare("
            UPDATE display_state
            SET scheduled_file_id = ?,
                scheduled_action = 'schedule',
                scheduled_play_type = ?,
                scheduled_minutes = ?,
                scheduled_time = ?,

                version = version + 1
            WHERE id = 1
        ")->execute([
            $file['id'],
            $priorityPlayType,
            $priorityMinutes,
            $priorityTime
        ]);

        $pdo->prepare("
            INSERT INTO notifications (title, message, target_role, link_url)
            VALUES ('Priority Play Scheduled', ?, 'all', '')
        ")->execute([
            "File scheduled: {$fileName}, time: {$priorityTime}, play type: {$priorityPlayType}, duration: {$priorityMinutes} minute(s)."
        ]);

        $message = "Scheduled priority play saved for: {$fileName}.";
    }

    $verStmt = $pdo->prepare("
        SELECT version
        FROM display_state
        WHERE id = 1
        LIMIT 1
    ");
    $verStmt->execute();
    $ver = $verStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok" => true,
        "message" => $message,
        "version" => (int)($ver['version'] ?? 1)
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}